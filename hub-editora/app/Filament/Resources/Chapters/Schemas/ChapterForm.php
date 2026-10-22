<?php

namespace App\Filament\Resources\Chapters\Schemas;

use App\Editorial\DoiSuffix;
use App\Editorial\Slug;
use App\Filament\Resources\Books\Schemas\ContributionsRepeater;
use App\Models\Book;
use App\Models\Chapter;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * Formulário do capítulo (contrato, seção 2), usado na etapa 6 do livro e
 * na edição avulsa. Os três resumos ficam lado a lado: original, sugestão
 * da IA e aprovado (o único publicado).
 */
class ChapterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Identificação')
                    ->columns(4)
                    ->schema([
                        TextInput::make('sequence')->label('Ordem no livro')->numeric()->minValue(1)->placeholder('próxima')
                            ->helperText('Define o sufixo DOI sugerido.'),
                        TextInput::make('number')->label('Número impresso')->maxLength(20),
                        TextInput::make('section')->label('Parte / seção do sumário')->maxLength(255)->columnSpan(2)
                            ->datalist(fn (?Chapter $record) => $record
                                ? Chapter::query()->where('book_id', $record->book_id)->whereNotNull('section')->distinct()->pluck('section')->all()
                                : []),
                        TextInput::make('title')->label('Título')->required()->maxLength(500)->columnSpanFull(),
                        TextInput::make('subtitle')->label('Subtítulo')->maxLength(500)->columnSpan(2),
                        TextInput::make('title_en')->label('Título em inglês')->maxLength(500)->columnSpan(2),
                        TextInput::make('first_page')->label('Primeira página')->numeric()->required()->minValue(1),
                        TextInput::make('last_page')->label('Última página')->numeric()->required()->minValue(1)->gte('first_page')
                            ->validationMessages(['gte' => 'A última página não pode vir antes da primeira.']),
                        Select::make('language')->label('Idioma')->options(Book::LANGUAGES)->placeholder('Herda do livro')->native(false),
                        DatePicker::make('publication_date')->label('Data de publicação')->native(false)->displayFormat('d/m/Y')->placeholder('Herda do livro'),
                    ]),

                Section::make('DOI e endereço')
                    ->columns(3)
                    ->schema([
                        TextInput::make('doi_suffix')->label('Sufixo DOI')
                            ->maxLength(70)
                            ->placeholder('gerado ao salvar: {sufixo do livro}.{NN}')
                            ->regex(DoiSuffix::PATTERN)
                            ->disabled(fn (?Chapter $record) => $record && ! $record->isDoiEditable())
                            ->dehydrated()
                            ->extraInputAttributes(['class' => 'hub-mono']),
                        TextInput::make('slug')->label('Slug')
                            ->maxLength(180)
                            ->placeholder('gerado do título')
                            ->disabled(fn (?Chapter $record) => $record && Slug::isLocked($record))
                            ->dehydrated()
                            ->helperText('Imutável depois que o DOI é enviado.'),
                        TextInput::make('landing_url')->label('URL pública')
                            ->disabled()->dehydrated(false)
                            ->placeholder('preenchida ao publicar'),
                    ]),

                Section::make('Resumos e palavras-chave')
                    ->description('O resumo publicado (site e Crossref) é sempre o aprovado.')
                    ->schema([
                        Textarea::make('abstract_original')->label('Resumo original (da obra)')->rows(4)->maxLength(5000),
                        Textarea::make('abstract_ai')->label('Sugestão da IA')->rows(4)->maxLength(5000)
                            ->helperText('Gerada pela importação; só vale depois de aprovada por uma pessoa.'),
                        Textarea::make('abstract_approved')->label('Resumo aprovado')->rows(4)->maxLength(5000)
                            ->helperText('Ao aprovar com este campo vazio, o sistema copia o original (ou, na falta dele, a sugestão da IA).'),
                        TagsInput::make('keywords')->label('Palavras-chave')->splitKeys(['Tab', ',']),
                        Repeater::make('references')->label('Referências')
                            ->defaultItems(0)->collapsed()->addActionLabel('Adicionar referência')->columns(4)
                            ->itemLabel(fn (array $state) => Str::limit($state['text'] ?? 'Referência', 80))
                            ->schema([
                                Textarea::make('text')->label('Referência')->required()->rows(2)->columnSpan(3),
                                TextInput::make('doi')->label('DOI')->maxLength(100)->placeholder('10.…'),
                            ]),
                    ]),

                Section::make('Autores')
                    ->schema([
                        ContributionsRepeater::make('author', 'Autores do capítulo'),
                    ]),
            ]);
    }
}
