<?php

namespace App\Filament\Resources\Institutions;

use App\Filament\Resources\Institutions\Pages\ManageInstitutions;
use App\Models\Institution;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Instituições de afiliação (universidades, hospitais, sociedades). */
class InstitutionResource extends Resource
{
    protected static ?string $model = Institution::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = 'Publicações';

    protected static ?string $navigationLabel = 'Instituições';

    protected static ?string $modelLabel = 'instituição';

    protected static ?string $pluralModelLabel = 'instituições';

    protected static ?string $slug = 'instituicoes';

    protected static ?int $navigationSort = 43;

    protected static ?string $recordTitleAttribute = 'name';

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

    public static function canDelete(Model $record): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    /** Campos reaproveitados no "criar instituição na hora" do assistente. */
    public static function formComponents(): array
    {
        return [
            TextInput::make('name')->label('Nome')->required()->maxLength(255)->columnSpanFull(),
            TextInput::make('acronym')->label('Sigla')->maxLength(30),
            TextInput::make('ror_id')->label('ROR ID')->maxLength(40)->placeholder('https://ror.org/…')
                ->helperText('Opcional. Identificador da instituição no Research Organization Registry.'),
            TextInput::make('city')->label('Cidade')->required()->maxLength(120),
            TextInput::make('state')->label('Estado / UF')->required()->maxLength(60),
            TextInput::make('country')->label('País (ISO-2)')->required()->default('BR')->length(2)
                ->extraInputAttributes(['style' => 'text-transform: uppercase']),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components(self::formComponents());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('contributions'))
            ->defaultSort('name')
            ->paginated([25, 50, 100])
            ->columns([
                TextColumn::make('name')->label('Nome')->weight(FontWeight::Medium)
                    ->description(fn (Institution $record) => $record->acronym)
                    ->searchable(['name', 'acronym'])->sortable()->wrap(),
                TextColumn::make('city')->label('Cidade')->searchable()->sortable(),
                TextColumn::make('state')->label('UF')->sortable(),
                TextColumn::make('country')->label('País')->badge()->color('gray'),
                TextColumn::make('ror_id')->label('ROR')->placeholder('—')->extraAttributes(['class' => 'hub-mono'])->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('contributions_count')->label('Vínculos')->badge()->color('gray')->sortable(),
            ])
            ->filters([
                SelectFilter::make('country')->label('País')
                    ->options(fn () => Institution::query()->distinct()->orderBy('country')->pluck('country', 'country')->all()),
            ])
            ->recordActions([
                EditAction::make()->label('Editar')->slideOver(),
                DeleteAction::make()->label('Remover')
                    ->hidden(fn (?Institution $record) => (bool) $record?->contributions()->exists()),
            ])
            ->emptyStateHeading('Nenhuma instituição cadastrada');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageInstitutions::route('/'),
        ];
    }
}
