<?php

namespace App\Filament\Resources\Chapters;

use App\Filament\Resources\Chapters\Pages\EditChapter;
use App\Filament\Resources\Chapters\Pages\ListChapters;
use App\Filament\Resources\Chapters\Schemas\ChapterForm;
use App\Filament\Resources\Chapters\Tables\ChaptersTable;
use App\Models\Chapter;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Capítulos de todos os livros, para o revisor trabalhar em lote (resumo
 * original × IA × aprovado). Capítulo novo nasce dentro do livro (etapa 6).
 */
class ChapterResource extends Resource
{
    protected static ?string $model = Chapter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string|UnitEnum|null $navigationGroup = 'Publicações';

    protected static ?string $navigationLabel = 'Capítulos';

    protected static ?string $modelLabel = 'capítulo';

    protected static ?string $pluralModelLabel = 'capítulos';

    protected static ?string $slug = 'capitulos';

    protected static ?int $navigationSort = 41;

    protected static ?string $recordTitleAttribute = 'title';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->canAccessEditorial();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return (bool) auth()->user()?->canEditEditorial();
    }

    public static function canDelete(Model $record): bool
    {
        return (bool) auth()->user()?->canEditEditorial() && $record instanceof Chapter && $record->doi_status === 'none';
    }

    public static function form(Schema $schema): Schema
    {
        return ChapterForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChaptersTable::configure($table, withBook: true)
            ->defaultSort('book_id')
            ->recordActions([
                EditAction::make()->label('Editar'),
                ChapterActions::approve(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ChapterActions::approveBulk(),
                ]),
            ])
            ->emptyStateHeading('Nenhum capítulo cadastrado');
    }

    public static function getNavigationBadge(): ?string
    {
        if (! auth()->user()?->canApproveEditorial()) {
            return null;
        }

        $n = Chapter::query()->where('status', 'review')->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChapters::route('/'),
            'edit' => EditChapter::route('/{record}/editar'),
        ];
    }
}
