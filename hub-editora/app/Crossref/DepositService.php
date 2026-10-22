<?php

namespace App\Crossref;

use App\Models\Deposit;
use App\Models\DepositItem;
use App\Models\SyncLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Ciclo de vida de um depósito na Crossref:
 *
 *   create()  payload do contrato + book_id → XML gerado, validado no XSD local e
 *             gravado em storage/app/private/crossref/; `deposits` em `queued`,
 *             um `deposit_items` (`pending`) por DOI.
 *   send()    POST na Crossref → `sent` (aceito) ou `failed` (recusado / sem rede).
 *   refresh() lê o relatório → itens `registered`/`failed`; depósito `processing`,
 *             `done` (todos registrados) ou `failed` (algum falhou).
 *
 * Quem liga o estado do livro/capítulo (`doi_status`) a isso é o integrador: os
 * itens guardam `target_type`/`target_id` para esse fim.
 */
class DepositService
{
    public const DIR = 'crossref';

    public function __construct(
        private readonly XmlBuilder $builder,
        private readonly Validator $validator,
        private readonly Client $client,
    ) {}

    public static function make(?Client $client = null): self
    {
        return new self(XmlBuilder::fromConfig(), new Validator, $client ?? Client::fromConfig());
    }

    /**
     * Gera, valida e grava o depósito. Não envia nada.
     *
     * @throws CrossrefException payload incompleto ou XML fora do schema
     */
    public function create(array $payload, int $bookId, ?int $userId = null): Deposit
    {
        $batchId = XmlBuilder::newBatchId($bookId);
        $xml = $this->builder->build($payload, $batchId);

        $errors = $this->validator->errors($xml);
        if ($errors !== []) {
            SyncLog::record(null, SyncLog::OUT, 'deposit.failed', null,
                "Livro {$bookId}: XML fora do schema da Crossref", ['book_id' => $bookId, 'errors' => array_slice($errors, 0, 20)], 'error');

            throw CrossrefException::invalidXml($errors);
        }

        $path = self::DIR.'/'.$batchId.'.xml';
        Storage::disk('local')->put($path, $xml);

        $deposit = DB::transaction(function () use ($payload, $bookId, $userId, $batchId, $path, $xml) {
            $deposit = Deposit::create([
                'batch_id' => $batchId,
                'environment' => $this->client->isProduction() ? Deposit::PRODUCTION : Deposit::TEST,
                'book_id' => $bookId,
                'xml_path' => $path,
                'checksum' => hash('sha256', $xml),
                'status' => Deposit::QUEUED,
                'created_by' => $userId ?? auth()->id(),
            ]);

            foreach ($this->builder->dois($payload) as $doi) {
                $deposit->items()->create([
                    'target_type' => $doi['target_type'],
                    'target_id' => $doi['target_id'],
                    'doi' => $doi['doi'],
                    'status' => DepositItem::PENDING,
                ]);
            }

            return $deposit;
        });

        SyncLog::record(null, SyncLog::OUT, 'deposit.created', $deposit,
            sprintf('Depósito %s gerado (%d DOI%s, %s)', $batchId, $deposit->items()->count(), $deposit->items()->count() === 1 ? '' : 's', $deposit->environment),
            ['book_id' => $bookId, 'xml_path' => $path, 'checksum' => $deposit->checksum]);

        return $deposit->load('items');
    }

    /**
     * Envia o XML gravado. HTTP 200 com "SUCCESS" = `sent` (nada registrado ainda).
     * Falha de rede marca `failed` e relança, para a fila tentar de novo.
     *
     * @throws ConnectionException
     */
    public function send(Deposit $deposit): Deposit
    {
        if (! $deposit->canBeSent()) {
            throw new CrossrefException("Depósito {$deposit->batch_id} está em '{$deposit->status}' e não pode ser enviado.");
        }

        $xml = Storage::disk('local')->get($deposit->xml_path);
        if ($xml === null || $xml === '') {
            return $this->fail($deposit, null, "Arquivo {$deposit->xml_path} não encontrado no storage.");
        }
        if (hash('sha256', $xml) !== $deposit->checksum) {
            return $this->fail($deposit, null, 'O XML gravado não confere com o checksum do depósito; gere um depósito novo.');
        }

        try {
            $response = $this->client->deposit($xml, $deposit->filename());
        } catch (ConnectionException $e) {
            $this->fail($deposit, null, 'Sem resposta da Crossref: '.mb_substr($e->getMessage(), 0, 300));

            throw $e;
        }

        if (! $response->accepted) {
            return $this->fail($deposit, $response->httpStatus, $response->message, $response->body);
        }

        $deposit->update([
            'status' => Deposit::SENT,
            'http_status' => $response->httpStatus,
            'response' => mb_substr($response->body, 0, 65000),
            'submitted_at' => now(),
        ]);

        SyncLog::record(null, SyncLog::OUT, 'deposit.sent', $deposit,
            "Depósito {$deposit->batch_id} recebido pela Crossref ({$deposit->environment}); aguardando processamento",
            ['http_status' => $response->httpStatus, 'message' => $response->message]);

        return $deposit;
    }

    /**
     * Consulta o relatório e atualiza itens e depósito. Enquanto a Crossref não
     * conclui, o depósito fica `processing` e os itens continuam `pending`.
     *
     * @throws ConnectionException
     */
    public function refresh(Deposit $deposit): Deposit
    {
        if (! $deposit->awaitingResult()) {
            throw new CrossrefException("Depósito {$deposit->batch_id} está em '{$deposit->status}'; só 'sent' ou 'processing' têm resultado a consultar.");
        }

        $result = $this->client->result($deposit->batch_id, $deposit->filename());

        if ($result->status === BatchResult::ERROR) {
            SyncLog::record(null, SyncLog::IN, 'deposit.failed', $deposit,
                "Depósito {$deposit->batch_id}: não foi possível ler o resultado: {$result->message}",
                ['http_status' => $result->httpStatus], 'warning');

            return $deposit; // estado inalterado: tenta de novo depois
        }

        if (! $result->isCompleted()) {
            $deposit->update(['status' => Deposit::PROCESSING]);
            SyncLog::record(null, SyncLog::IN, 'deposit.pending', $deposit, "Depósito {$deposit->batch_id}: {$result->message}");

            return $deposit;
        }

        $registered = 0;
        $failed = 0;

        DB::transaction(function () use ($deposit, $result, &$registered, &$failed) {
            foreach ($deposit->items as $item) {
                $record = $result->record($item->doi);

                if ($record === null) {
                    $item->update(['status' => DepositItem::FAILED, 'message' => 'DOI não consta no relatório da Crossref.']);
                    $failed++;

                    continue;
                }

                $ok = in_array($record['status'], ['Success', 'Warning'], true);
                $item->update([
                    'status' => $ok ? DepositItem::REGISTERED : DepositItem::FAILED,
                    'message' => mb_substr($record['message'] !== '' ? $record['message'] : $record['status'], 0, 2000),
                ]);
                $ok ? $registered++ : $failed++;
            }

            $deposit->update([
                'status' => $failed === 0 ? Deposit::DONE : Deposit::FAILED,
                'response' => mb_substr($result->raw, 0, 65000),
                'result_at' => now(),
            ]);
        });

        SyncLog::record(null, SyncLog::IN, $failed === 0 ? 'deposit.done' : 'deposit.failed', $deposit,
            sprintf('Depósito %s processado: %d DOI(s) registrado(s), %d com falha', $deposit->batch_id, $registered, $failed),
            ['registered' => $registered, 'failed' => $failed, 'summary' => $result->message],
            $failed === 0 ? 'info' : 'error');

        return $deposit->refresh()->load('items');
    }

    private function fail(Deposit $deposit, ?int $httpStatus, string $message, ?string $body = null): Deposit
    {
        $deposit->update([
            'status' => Deposit::FAILED,
            'http_status' => $httpStatus,
            'response' => mb_substr($body ?? $message, 0, 65000),
        ]);

        SyncLog::record(null, SyncLog::OUT, 'deposit.failed', $deposit,
            "Depósito {$deposit->batch_id} não foi aceito: ".mb_substr($message, 0, 300),
            ['http_status' => $httpStatus], 'error');

        return $deposit;
    }
}
