<?php

namespace App\Filament\Resources\Books;

use App\Filament\Resources\Books\Pages\CreateBook;
use App\Filament\Resources\Books\Pages\EditBook;
use App\Filament\Resources\Books\Pages\ListBooks;
use App\Filament\Resources\Books\Pages\ViewBook;
use App\Filament\Resources\Books\RelationManagers\DepositsRelationManager;
use App\Filament\Resources\Books\Schemas\BookInfolist;
use App\Filament\Resources\Books\Tables\BooksTable;
use App\Models\Book;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Livros do módulo Editorial (Conexão DOI). O cadastro é o assistente de
 * 8 etapas (CreateBook/EditBook, via BookWizard); a tela de visualização
 * resume o registro, o checklist e as ações de situação.
 *
 * Perfis: admin, editor, revisor e depósito entram; expedição não.
 * Editor e revisor cadastram; só revisor/admin aprovam; depósito só consulta
 * (e verá as ações de depósito da frente D).
 */
class BookResource extends Resource
{
    protected static ?string $model = Book::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookmarkSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Publicações';

    protected static ?string $navigationLabel = 'Livros';

    protected static ?string $modelLabel = 'livro';

    protected static ?string $pluralModelLabel = 'livros';

    protected static ?string $slug = 'livros';

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'title';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->canAccessEditorial();
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->canEditEditorial();
    }

    public static function canEdit(Model $record): bool
    {
        return (bool) auth()->user()?->canEditEditorial();
    }

    public static function canView(Model $record): bool
    {
        return (bool) auth()->user()?->canAccessEditorial();
    }

    /** Só administrador apaga, e só rascunho: livro com DOI enviado nunca some. */
    public static function canDelete(Model $record): bool
    {
        return (bool) auth()->user()?->isAdmin() && $record instanceof Book && $record->status === 'draft' && $record->doi_status === 'none';
    }

    public static function infolist(Schema $schema): Schema
    {
        return BookInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BooksTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        if (! auth()->user()?->canApproveEditorial()) {
            return null;
        }

        $n = Book::query()->where('status', 'review')->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Aguardando aprovação';
    }

    /**
     * Ações de depósito na Crossref (frente E1), mostradas na tela do livro para
     * os perfis Depósito e Administrador: registrar DOI, consultar resultado,
     * baixar o XML do último depósito.
     *
     * @return array<Action>
     */
    public static function depositActions(): array
    {
        return DepositActions::all();
    }

    /** Histórico de depósitos (só na tela de resumo, para quem deposita). */
    public static function getRelations(): array
    {
        return [
            DepositsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBooks::route('/'),
            'create' => CreateBook::route('/novo'),
            'view' => ViewBook::route('/{record}'),
            'edit' => EditBook::route('/{record}/editar'),
        ];
    }
}
