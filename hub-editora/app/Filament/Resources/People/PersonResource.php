<?php

namespace App\Filament\Resources\People;

use App\Filament\Resources\People\Pages\ManagePeople;
use App\Models\Person;
use App\Rules\Orcid;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Pessoas (autores, organizadores...). Uma pessoa, vários papéis em várias
 * obras. O ORCID é validado e guardado na forma canônica; o nome de
 * ordenação é gerado quando vazio. Instituições aparecem pelas contribuições.
 */
class PersonResource extends Resource
{
    protected static ?string $model = Person::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Publicações';

    protected static ?string $navigationLabel = 'Pessoas';

    protected static ?string $modelLabel = 'pessoa';

    protected static ?string $pluralModelLabel = 'pessoas';

    protected static ?string $slug = 'pessoas';

    protected static ?int $navigationSort = 42;

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

    /** Campos reaproveitados no "criar pessoa na hora" do assistente do livro. */
    public static function formComponents(): array
    {
        return [
            TextInput::make('name')->label('Nome completo')->required()->maxLength(255)
                ->helperText('Como aparece na obra.'),
            TextInput::make('sort_name')->label('Nome de ordenação')->maxLength(255)
                ->placeholder('SOBRENOME, Nome')
                ->helperText('Para citações e Crossref. Gerado do nome se ficar vazio.'),
            TextInput::make('orcid')->label('ORCID')
                ->placeholder('0000-0000-0000-0000')
                ->maxLength(40)
                ->rule(new Orcid)
                ->dehydrateStateUsing(fn ($state) => filled($state) ? Orcid::normalize($state) : null)
                ->unique(ignoreRecord: true)
                ->validationMessages(['unique' => 'Já existe uma pessoa com este ORCID.'])
                ->helperText('Aceita a URL https://orcid.org/… ou só os 16 caracteres.'),
            TextInput::make('email')->label('E-mail (interno)')->email()->maxLength(190)
                ->helperText('Nunca é publicado.'),
            Textarea::make('bio')->label('Minibiografia')->rows(3)->maxLength(2000)->columnSpanFull(),
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
            ->defaultSort('sort_name')
            ->paginated([25, 50, 100])
            ->columns([
                TextColumn::make('name')->label('Nome')->weight(FontWeight::Medium)
                    ->description(fn (Person $record) => $record->sort_name)
                    ->searchable(['name', 'sort_name'])->sortable(),
                TextColumn::make('orcid')->label('ORCID')->placeholder('—')->copyable()
                    ->extraAttributes(['class' => 'hub-mono'])
                    ->url(fn (Person $record) => $record->orcid_url, shouldOpenInNewTab: true)
                    ->searchable(),
                TextColumn::make('institutions_label')->label('Instituições')
                    ->state(fn (Person $record) => $record->institutions()->map(fn ($i) => $i->acronym ?: $i->name)->implode(', ') ?: null)
                    ->placeholder('—')->wrap()->toggleable(),
                TextColumn::make('email')->label('E-mail')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('contributions_count')->label('Obras')->badge()->color('gray')->sortable(),
                TextColumn::make('wp_term_id')->label('Termo WP')->placeholder('—')->extraAttributes(['class' => 'hub-mono'])->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('orcid')->label('Com ORCID')->nullable()
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('orcid'),
                        false: fn (Builder $query) => $query->whereNull('orcid'),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->recordActions([
                EditAction::make()->label('Editar')->slideOver(),
                DeleteAction::make()->label('Remover')
                    ->hidden(fn (?Person $record) => (bool) $record?->contributions()->exists()),
            ])
            ->emptyStateHeading('Nenhuma pessoa cadastrada')
            ->emptyStateDescription('Autores e organizadores também podem ser criados direto no assistente do livro.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePeople::route('/'),
        ];
    }
}
