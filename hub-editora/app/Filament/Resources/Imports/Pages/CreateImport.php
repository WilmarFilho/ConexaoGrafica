<?php

namespace App\Filament\Resources\Imports\Pages;

use App\Filament\Resources\Imports\ImportResource;
use App\Jobs\ProcessImport;
use App\Models\Book;
use App\Models\Import;
use App\Models\SyncLog;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Envio do PDF: o arquivo vai para o disco privado (storage/app/private/imports),
 * nasce um Import em `received` e o ProcessImport entra na fila `database`.
 * `?book=ID` (atalho da etapa 6 do livro) pré-seleciona o livro.
 */
class CreateImport extends CreateRecord
{
    protected static string $resource = ImportResource::class;

    protected static ?string $title = 'Importar PDF com IA';

    public function mount(): void
    {
        parent::mount();

        if ($book = request()->integer('book')) {
            $this->form->fill(['book_id' => $book]);
        }
    }

    public function getSubheading(): ?string
    {
        return 'A IA lê o PDF e monta uma proposta (obra, pessoas, capítulos, resumos). Nada entra no catálogo antes da revisão.';
    }

    public function form(Schema $schema): Schema
    {
        $configured = (string) config('hub.ai.key') !== '';

        return $schema->components([
            Section::make('Arquivo')
                ->description('PDF com camada de texto (não digitalizado), até 200 MB. Livros de centenas de páginas levam dezenas de minutos para analisar.')
                ->schema([
                    FileUpload::make('file')
                        ->label('PDF do livro')
                        ->disk('local')
                        ->directory(Import::DIR)
                        ->visibility('private')
                        ->acceptedFileTypes(['application/pdf'])
                        ->maxSize(204800)
                        ->storeFileNamesIn('original_name')
                        ->required()
                        ->helperText('Só application/pdf. O arquivo fica privado no servidor do Hub.'),
                    Hidden::make('original_name'),
                ]),

            Section::make('Vínculo')
                ->schema([
                    Select::make('book_id')
                        ->label('Livro já cadastrado (opcional)')
                        ->options(fn () => Book::query()->orderBy('title')->pluck('title', 'id')->all())
                        ->searchable()
                        ->nullable()
                        ->helperText('Se informado, a integração atualiza este livro em vez de criar outro.'),
                ]),

            Text::make($configured
                ? 'Modelo '.config('hub.ai.model').' (esforço '.config('hub.ai.effort').'). O custo estimado fica gravado na importação e na Auditoria.'
                : 'OPENAI_API_KEY não está configurada no .env do Hub: a importação será criada, mas ficará em erro até a chave existir.')
                ->color($configured ? 'gray' : 'danger'),
        ])->columns(1);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $disk = Storage::disk('local');
        $uploaded = is_array($data['file'] ?? null) ? (reset($data['file']) ?: null) : ($data['file'] ?? null);

        if (! is_string($uploaded) || ! $disk->exists($uploaded)) {
            throw ValidationException::withMessages(['data.file' => 'O PDF não foi recebido; envie de novo.']);
        }

        $magic = (string) @file_get_contents($disk->path($uploaded), false, null, 0, 5);
        if (! str_starts_with($magic, '%PDF-')) {
            $disk->delete($uploaded);
            throw ValidationException::withMessages(['data.file' => 'O arquivo não parece um PDF.']);
        }

        $name = Import::DIR.'/'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.pdf';
        $disk->move($uploaded, $name);

        $original = is_array($data['original_name'] ?? null) ? (reset($data['original_name']) ?: null) : ($data['original_name'] ?? null);

        $import = Import::create([
            'book_id' => $data['book_id'] ?: null,
            'file_path' => $name,
            'original_name' => $original ?: basename($name),
            'size' => $disk->size($name),
            'status' => Import::RECEIVED,
            'created_by' => auth()->id(),
        ]);

        SyncLog::record(null, SyncLog::IN, 'import.received', $import,
            sprintf('%s (%s KB) recebido pelo painel', $import->original_name, number_format($import->size / 1024, 0, ',', '.')),
            ['file' => $import->file_path, 'book_id' => $import->book_id]);

        ProcessImport::dispatch($import->id);

        return $import;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'PDF recebido. A análise roda na fila; a página mostra o andamento.';
    }

    protected function getRedirectUrl(): string
    {
        return ImportResource::getUrl('review', ['record' => $this->getRecord()]);
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Enviar para análise')->icon(Heroicon::OutlinedSparkles);
    }

    public function canCreateAnother(): bool
    {
        return false;
    }
}
