<?php

namespace App\Filament\Resources\Imports;

use App\Filament\Resources\Imports\Pages\CreateImport;
use App\Filament\Resources\Imports\Pages\ListImports;
use App\Filament\Resources\Imports\Pages\ReviewImport;
use App\Filament\Resources\Imports\Tables\ImportsTable;
use App\Models\Import;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Importações de livros em PDF com IA (Conexão DOI, frentes C e E2).
 * Lista → enviar PDF (fila) → revisar lado a lado (PDF × proposta) →
 * aprovar → integrar ao catálogo (cria livro, capítulos, pessoas).
 *
 * Perfis: admin, editor e revisor. Depósito e expedição não entram.
 */
class ImportResource extends Resource
{
    protected static ?string $model = Import::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Publicações';

    protected static ?string $navigationLabel = 'Importações (IA)';

    protected static ?string $modelLabel = 'importação';

    protected static ?string $pluralModelLabel = 'importações';

    protected static ?string $slug = 'importacoes';

    protected static ?int $navigationSort = 44;

    protected static ?string $recordTitleAttribute = 'original_name';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->canEditEditorial();
    }

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function canView(Model $record): bool
    {
        return static::canAccess();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canAccess();
    }

    /** Só administrador apaga, e nunca uma importação já integrada (o livro continua existindo). */
    public static function canDelete(Model $record): bool
    {
        return (bool) auth()->user()?->isAdmin() && $record instanceof Import && $record->status !== Import::INTEGRATED;
    }

    public static function table(Table $table): Table
    {
        return ImportsTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::canAccess()) {
            return null;
        }

        $n = Import::query()->whereIn('status', [Import::SUGGESTED, Import::REVIEW])->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Propostas aguardando revisão';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImports::route('/'),
            'create' => CreateImport::route('/nova'),
            'review' => ReviewImport::route('/{record}'),
        ];
    }
}
