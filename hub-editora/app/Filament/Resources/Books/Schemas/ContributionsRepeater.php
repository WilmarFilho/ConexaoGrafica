<?php

namespace App\Filament\Resources\Books\Schemas;

use App\Filament\Resources\Institutions\InstitutionResource;
use App\Filament\Resources\People\PersonResource;
use App\Models\Contribution;
use App\Models\Institution;
use App\Models\Person;
use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

/**
 * Repetidor de contribuições (pessoa, papel, ordem, afiliação) usado na
 * etapa 2 do livro e no formulário do capítulo. A pessoa e a instituição
 * podem ser criadas na hora, sem sair do assistente.
 */
class ContributionsRepeater
{
    public static function make(string $defaultRole = 'author', string $label = 'Autores'): Repeater
    {
        return Repeater::make('contributions')
            ->label($label)
            ->relationship()
            ->orderColumn('sequence')
            ->reorderableWithButtons()
            ->addActionLabel('Adicionar pessoa')
            ->itemLabel(fn (array $state) => filled($state['person_id'] ?? null)
                ? (Person::find($state['person_id'])?->name ?? '').' · '.(Contribution::ROLES[$state['role'] ?? ''] ?? '')
                : 'Nova pessoa')
            ->columns(12)
            ->defaultItems(0)
            ->rule(static fn (): Closure => function (string $attribute, mixed $value, Closure $fail) {
                $seen = [];
                foreach ((array) $value as $row) {
                    $key = ($row['person_id'] ?? '').'|'.($row['role'] ?? '');
                    if (filled($row['person_id'] ?? null) && isset($seen[$key])) {
                        $fail('A mesma pessoa aparece duas vezes com o mesmo papel.');

                        return;
                    }
                    $seen[$key] = true;
                }
            })
            ->schema([
                Select::make('person_id')
                    ->label('Pessoa')
                    ->relationship('person', 'name')
                    ->getOptionLabelFromRecordUsing(fn (Person $p) => $p->name.($p->orcid ? " · {$p->orcid}" : ''))
                    ->searchable(['name', 'sort_name', 'orcid'])
                    ->preload()
                    ->required()
                    ->native(false)
                    ->createOptionForm(PersonResource::formComponents())
                    ->createOptionModalHeading('Nova pessoa')
                    ->columnSpan(5),
                Select::make('role')
                    ->label('Papel')
                    ->options(Contribution::ROLES)
                    ->default($defaultRole)
                    ->required()
                    ->native(false)
                    ->columnSpan(2),
                Select::make('institution_id')
                    ->label('Instituição')
                    ->relationship('institution', 'name')
                    ->getOptionLabelFromRecordUsing(fn (Institution $i) => $i->label)
                    ->searchable(['name', 'acronym', 'city'])
                    ->preload()
                    ->native(false)
                    ->createOptionForm(InstitutionResource::formComponents())
                    ->createOptionModalHeading('Nova instituição')
                    ->columnSpan(3),
                TextInput::make('affiliation_text')
                    ->label('Afiliação (texto)')
                    ->maxLength(255)
                    ->placeholder('Se não estiver cadastrada')
                    ->columnSpan(2),
            ]);
    }
}
