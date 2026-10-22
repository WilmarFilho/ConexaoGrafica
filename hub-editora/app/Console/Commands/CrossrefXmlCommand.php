<?php

namespace App\Console\Commands;

use App\Crossref\CrossrefException;
use App\Crossref\Validator;
use App\Crossref\XmlBuilder;
use Illuminate\Console\Command;

/**
 * Gera e valida o XML da Crossref a partir de um payload JSON do contrato, sem
 * gravar nada no banco. Para o integrador conferir o que o livro vai produzir.
 *
 *   php artisan hub:crossref-xml conexao-editora/docs/exemplos/publicacao-tratado.json
 *   php artisan hub:crossref-xml payload.json --saida=/tmp/lote.xml
 */
class CrossrefXmlCommand extends Command
{
    protected $signature = 'hub:crossref-xml {json : Arquivo JSON com {book, chapters}} {--saida= : Grava o XML neste arquivo em vez de imprimir}';

    protected $description = 'Gera e valida (XSD local) o doi_batch da Crossref de um payload JSON';

    public function handle(): int
    {
        $file = (string) $this->argument('json');

        if (! is_file($file)) {
            $this->error("Arquivo não encontrado: {$file}");

            return self::FAILURE;
        }

        $payload = json_decode((string) file_get_contents($file), true);
        if (! is_array($payload) || ! isset($payload['book'])) {
            $this->error('JSON inválido ou sem a chave "book".');

            return self::FAILURE;
        }

        try {
            $xml = XmlBuilder::fromConfig()->build($payload, XmlBuilder::newBatchId((int) ($payload['book']['hub_id'] ?? 0)));
        } catch (CrossrefException $e) {
            $this->error('Payload incompleto:');
            foreach ($e->errors ?: [$e->getMessage()] as $err) {
                $this->line('  - '.$err);
            }

            return self::FAILURE;
        }

        $errors = (new Validator)->errors($xml);

        if ($saida = $this->option('saida')) {
            file_put_contents((string) $saida, $xml);
            $this->info("XML gravado em {$saida} (".number_format(strlen($xml) / 1024, 1, ',', '.').' KB).');
        } else {
            $this->line($xml);
        }

        if ($errors !== []) {
            $this->error(count($errors).' erro(s) de schema:');
            foreach ($errors as $err) {
                $this->line('  - '.$err);
            }

            return self::FAILURE;
        }

        $this->info('XML válido no crossref5.4.0.xsd.');

        return self::SUCCESS;
    }
}
