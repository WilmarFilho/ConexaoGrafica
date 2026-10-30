<?php

namespace App\Filament\Resources\Imports\Schemas;

use App\Filament\Resources\Imports\Pages\ReviewImport;
use App\Models\Book;
use App\Models\Contribution;
use App\Rules\Isbn13;
use App\Rules\Orcid;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * Formulário da revisão (lado direito da tela): abas Obra, Pessoas e Capítulos
 * sobre `data.book` e `data.chapter` (o capítulo atual). Cada campo mostra a
 * evidência da IA (página, trecho, confiança) com "ver página", e fica
 * destacado em amarelo quando está em `needs_review` ou a confiança é baixa.
 */
class ReviewForm
{
    public const LOW_CONFIDENCE = 0.6;

    /** @return array<\Filament\Schemas\Components\Component> */
    public static function components(ReviewImport $page): array
    {
        $book = fn (): array => $page->bookProposal();
        $chapter = fn (): array => $page->currentChapter();

        $bookCtx = fn (): array => ['evidence' => $book()['evidence'] ?? [], 'needs' => $book()['needs_review'] ?? []];
        $chapterCtx = fn (): array => ['evidence' => $chapter()['evidence'] ?? [], 'needs' => $chapter()['needs_review'] ?? []];

        return [
            Tabs::make('revisao')
                ->persistTabInQueryString('aba')
                ->tabs([
                    self::tabObra($page, $book, $bookCtx),
                    self::tabPessoas($page, $book),
                    self::tabCapitulos($page, $book, $chapter, $chapterCtx),
                ]),
        ];
    }

    // ------------------------------------------------------------------ Obra

    private static function tabObra(ReviewImport $page, Closure $book, Closure $ctx): Tab
    {
        return Tab::make('Obra')
            ->icon(Heroicon::OutlinedBookOpen)
            ->badge(fn () => $page->getRecord()->bookApproved() ? 'aprovada' : (count($book()['needs_review'] ?? []) ?: null))
            ->badgeColor(fn () => $page->getRecord()->bookApproved() ? 'success' : 'warning')
            ->schema([
                Text::make(fn () => self::bookBanner($page, $book())),

                Section::make('Título e tipo')->columns(2)->schema([
                    self::field(TextInput::make('book.title')->label('Título')->maxLength(500)->columnSpanFull(), 'title', $ctx),
                    self::field(TextInput::make('book.subtitle')->label('Subtítulo')->maxLength(500)->columnSpanFull(), 'subtitle', $ctx),
                    self::field(TextInput::make('book.alt_title')->label('Título em outro idioma')->maxLength(500), 'alt_title', $ctx),
                    Select::make('book.alt_title_lang')->label('Idioma do título alternativo')->options(Book::LANGUAGES)->nullable(),
                    self::field(Select::make('book.type')->label('Tipo')->options(Book::TYPES)->nullable(), 'type', $ctx),
                    self::field(Select::make('book.language')->label('Idioma')->options(Book::LANGUAGES)->nullable(), 'language', $ctx),
                    self::field(TextInput::make('book.edition_number')->label('Edição')->numeric()->minValue(1), 'edition_number', $ctx),
                    self::field(TextInput::make('book.volume')->label('Volume')->maxLength(20), 'volume', $ctx),
                    self::field(TextInput::make('book.series.title')->label('Série')->maxLength(255), 'series_title', $ctx),
                    TextInput::make('book.series.issn')->label('ISSN da série')->maxLength(9),
                ]),

                Section::make('Publicação')->columns(3)->schema([
                    self::field(TextInput::make('book.publisher')->label('Editora')->maxLength(255), 'publisher', $ctx),
                    self::field(TextInput::make('book.publication_place')->label('Cidade')->maxLength(255), 'publication_place', $ctx),
                    self::field(TextInput::make('book.publication_year')->label('Ano')->numeric()->minValue(1900)->maxValue(2100), 'publication_year', $ctx),
                    self::field(DatePicker::make('book.publication_date')->label('Data de publicação')->native(false)->displayFormat('d/m/Y'), 'publication_date', $ctx),
                    self::field(TextInput::make('book.pages')->label('Páginas')->numeric()->minValue(1), 'pages', $ctx),
                    Select::make('book.format')->label('Formato')->options(Book::FORMATS)->nullable(),
                    self::field(TextInput::make('book.subject_area')->label('Área de conhecimento')->maxLength(255), 'subject_area', $ctx),
                    self::field(TextInput::make('book.subject_subarea')->label('Subárea')->maxLength(255), 'subject_subarea', $ctx),
                    self::field(TextInput::make('book.audience')->label('Público')->maxLength(500), 'audience', $ctx),
                ]),

                Section::make('Identificadores')->columns(2)->schema([
                    self::field(TextInput::make('book.isbn_print')->label('ISBN impresso')->maxLength(17)->rule(new Isbn13)->extraInputAttributes(['class' => 'hub-mono']), 'isbn_print', $ctx),
                    self::field(TextInput::make('book.isbn_ebook')->label('ISBN e-book')->maxLength(17)->rule(new Isbn13)->extraInputAttributes(['class' => 'hub-mono']), 'isbn_ebook', $ctx),
                    Repeater::make('book.other_isbns')->label('Outros ISBNs')->columnSpanFull()->columns(2)
                        ->schema([
                            TextInput::make('type')->label('Tipo')->maxLength(30)->placeholder('EPUB'),
                            TextInput::make('value')->label('ISBN')->maxLength(17)->rule(new Isbn13),
                        ])
                        ->defaultItems(0)->addActionLabel('Adicionar ISBN')->collapsible(),
                ]),

                Section::make('Resumo e palavras-chave')->schema([
                    self::field(Textarea::make('book.abstract')->label(fn () => 'Resumo científico'.(($book()['abstract_source'] ?? null) === 'ai' ? ' — sugerido pela IA (o PDF não trazia), confira e edite' : ''))->rows(6), 'abstract', $ctx),
                    Textarea::make('book.synopsis')->label('Sinopse comercial (não vai no XML)')->rows(3),
                    self::field(TagsInput::make('book.keywords')->label(fn () => 'Palavras-chave'.(($book()['keywords_source'] ?? null) === 'ai' ? ' — sugeridas pela IA' : '')), 'keywords', $ctx),
                    TagsInput::make('book.keywords_en')->label('Palavras-chave em inglês'),
                ]),
            ]);
    }

    private static function bookBanner(ReviewImport $page, array $book): HtmlString
    {
        $import = $page->getRecord();
        $parts = [];
        $parts[] = $import->bookApproved()
            ? '<span class="hub-pill hub-pill-ok">Obra aprovada</span>'
            : '<span class="hub-pill hub-pill-warn">Obra ainda não aprovada</span>';
        if ($needs = $book['needs_review'] ?? []) {
            $parts[] = '<span class="hub-pill hub-pill-warn">Revisar: '.e(implode(', ', $needs)).'</span>';
        }
        if (filled($book['notes'] ?? null)) {
            $parts[] = '<div class="hub-note"><strong>Aviso da IA:</strong> '.e($book['notes']).'</div>';
        }
        if ($toc = $book['toc'] ?? []) {
            $sections = collect($toc)->map(fn ($s) => e($s['section'] ?? 'Sem seção').' ('.count($s['chapters'] ?? []).')')->implode(' · ');
            $parts[] = '<div class="hub-note">Sumário: '.$sections.'</div>';
        }

        return new HtmlString('<div class="hub-banner">'.implode(' ', $parts).'</div>');
    }

    // --------------------------------------------------------------- Pessoas

    private static function tabPessoas(ReviewImport $page, Closure $book): Tab
    {
        return Tab::make('Pessoas')
            ->icon(Heroicon::OutlinedUserGroup)
            ->badge(fn () => count($book()['contributors'] ?? []) ?: null)
            ->schema([
                Text::make('Organizadores e autores listados na obra (capa, ficha, página de autores). O índice de cada pessoa é o vínculo usado pelos capítulos: por isso não dá para excluir; para descartar alguém, deixe o nome em branco. Regra da editora: cada pessoa precisa de ORCID ou instituição.')
                    ->color('gray'),

                Repeater::make('book.contributors')
                    ->hiddenLabel()
                    ->schema([
                        Hidden::make('_idx'),
                        Grid::make(['default' => 1, 'md' => 6])->schema([
                            TextInput::make('name')->label('Nome')->maxLength(255)->columnSpan(['md' => 3]),
                            TextInput::make('sort_name')->label('Nome de ordenação')->placeholder('SOBRENOME, Nome')->maxLength(255)->columnSpan(['md' => 2]),
                            Select::make('role')->label('Papel')->options(Contribution::ROLES)->columnSpan(['md' => 1]),
                            TextInput::make('orcid')->label('ORCID')->placeholder('0000-0000-0000-0000')->maxLength(40)->rule(new Orcid)
                                ->extraInputAttributes(['class' => 'hub-mono'])->columnSpan(['md' => 2]),
                            TextInput::make('affiliation.name')->label('Instituição')->maxLength(255)->columnSpan(['md' => 2]),
                            TextInput::make('affiliation.city')->label('Cidade')->maxLength(120)->columnSpan(['md' => 1]),
                            TextInput::make('affiliation.state')->label('UF')->maxLength(60)->columnSpan(['md' => 1]),
                        ]),
                        Text::make(fn (Get $get) => self::contributorEvidence($book()['contributors'][(int) ($get('_idx') ?? -1)] ?? null)),
                    ])
                    ->itemLabel(fn (array $state) => self::contributorLabel($state, $book()['contributors'][$state['_idx'] ?? -1] ?? null))
                    ->deletable(false)
                    ->reorderable(false)
                    ->addActionLabel('Adicionar pessoa à obra')
                    ->collapsible()
                    ->defaultItems(0),

                View::make('filament.resources.imports.people')
                    ->viewData(fn () => ['people' => $page->getRecord()->result['people'] ?? []]),
            ]);
    }

    private static function contributorLabel(array $state, ?array $original): string
    {
        $flags = [];
        foreach ((array) ($original['needs_review'] ?? []) as $f) {
            $flags[] = match ($f) {
                'affiliation' => 'sem ORCID nem instituição',
                'orcid' => 'ORCID inválido',
                'person' => 'não listada na obra',
                default => $f,
            };
        }
        $role = Contribution::ROLES[$state['role'] ?? ''] ?? '';

        return trim(($state['name'] ?? 'Nova pessoa').($role ? " · {$role}" : '').($flags ? ' · ⚠ '.implode(', ', $flags) : ''));
    }

    private static function contributorEvidence(?array $c): HtmlString
    {
        if (! $c) {
            return new HtmlString('<span class="hub-ev">Pessoa acrescentada na revisão.</span>');
        }
        $ev = $c['evidence'] ?? null;
        $conf = $c['confidence'] ?? null;

        return self::evidenceHtml($ev, $conf, (array) ($c['needs_review'] ?? []), null);
    }

    // ------------------------------------------------------------- Capítulos

    private static function tabCapitulos(ReviewImport $page, Closure $book, Closure $chapter, Closure $ctx): Tab
    {
        $pagesCtx = fn () => $ctx() + ['alias' => 'pages'];

        return Tab::make('Capítulos')
            ->icon(Heroicon::OutlinedListBullet)
            ->badge(fn () => $page->getRecord()->chaptersCount() ? $page->getRecord()->approvedChaptersCount().'/'.$page->getRecord()->chaptersCount() : null)
            ->badgeColor(fn () => $page->getRecord()->isFullyApproved() || ($page->getRecord()->chaptersCount() > 0 && $page->getRecord()->approvedChaptersCount() === $page->getRecord()->chaptersCount()) ? 'success' : 'warning')
            ->schema([
                Grid::make(['default' => 1, 'lg' => 12])->schema([
                    View::make('filament.resources.imports.chapter-list')
                        ->viewData(fn () => ['chapters' => $page->chaptersOutline(), 'current' => $page->current])
                        ->columnSpan(['lg' => 3]),

                    Group::make([
                        Text::make(fn () => self::chapterBanner($page, $chapter())),

                        Section::make('Identificação')->columns(6)->schema([
                            self::field(TextInput::make('chapter.title')->label('Título')->maxLength(500)->columnSpan(5), 'title', $ctx),
                            self::field(TextInput::make('chapter.number')->label('Número')->maxLength(20)->columnSpan(1), 'number', $ctx),
                            TextInput::make('chapter.subtitle')->label('Subtítulo')->maxLength(500)->columnSpan(3),
                            TextInput::make('chapter.section')->label('Seção do sumário')->maxLength(255)->columnSpan(3),
                            self::field(TextInput::make('chapter.first_page')->label('Primeira página (impressa)')->numeric()->minValue(0)->columnSpan(2), 'pages', $pagesCtx),
                            self::field(TextInput::make('chapter.last_page')->label('Última página (impressa)')->numeric()->minValue(0)->columnSpan(2), 'pages', $pagesCtx),
                            TextInput::make('chapter.pdf_first_page')->label('Página no PDF')->numeric()->minValue(1)->columnSpan(1)
                                ->helperText('Para abrir o PDF.'),
                            TextInput::make('chapter.pdf_last_page')->label('Fim no PDF')->numeric()->minValue(1)->columnSpan(1),
                            Select::make('chapter.language')->label('Idioma (se diferente do livro)')->options(Book::LANGUAGES)->nullable()->columnSpan(2),
                            TextInput::make('chapter.title_en')->label('Título em inglês')->maxLength(500)->columnSpan(4),
                        ]),

                        Section::make('Autores')
                            ->description(fn () => self::authorsDescription($ctx()))
                            ->extraAttributes(fn () => self::wrapperAttributes('authors', $ctx()))
                            ->schema([
                                Repeater::make('chapter.contributors')
                                    ->hiddenLabel()
                                    ->schema([
                                        Hidden::make('_idx'),
                                        Grid::make(['default' => 1, 'md' => 6])->schema([
                                            TextInput::make('name')->label('Nome como aparece no capítulo')->maxLength(255)->columnSpan(['md' => 2]),
                                            Select::make('book_contributor_index')->label('Pessoa da obra')
                                                ->options(fn () => collect($book()['contributors'] ?? [])
                                                    ->mapWithKeys(fn ($c, $i) => [(string) $i => ($c['name'] ?: '—').' ('.(Contribution::ROLES[$c['role'] ?? ''] ?? $c['role'] ?? '').')'])->all())
                                                ->placeholder('— pessoa nova —')
                                                ->nullable()
                                                ->columnSpan(['md' => 2]),
                                            TextInput::make('orcid')->label('ORCID')->maxLength(40)->rule(new Orcid)->extraInputAttributes(['class' => 'hub-mono'])->columnSpan(['md' => 1]),
                                            TextInput::make('affiliation_text')->label('Instituição')->maxLength(255)->columnSpan(['md' => 1]),
                                        ]),
                                        Text::make(fn (Get $get) => self::contributorEvidence($chapter()['contributors'][(int) ($get('_idx') ?? -1)] ?? null)),
                                    ])
                                    ->itemLabel(fn (array $state) => self::contributorLabel($state, $chapter()['contributors'][$state['_idx'] ?? -1] ?? null))
                                    ->reorderableWithButtons()
                                    ->addActionLabel('Adicionar autor')
                                    ->collapsible()
                                    ->defaultItems(0),
                            ]),

                        Section::make('Resumo')
                            ->description(fn () => self::abstractDescription($chapter()))
                            ->extraAttributes(fn () => self::wrapperAttributes('abstract', $ctx()))
                            ->schema([
                                Textarea::make('chapter.abstract_original')->label('Resumo original (do PDF)')->rows(5)->disabled()->dehydrated(false)
                                    ->visible(fn () => filled($chapter()['abstract_original'] ?? null)),
                                Textarea::make('chapter.abstract_ai')->label('Sugestão da IA (não havia resumo no PDF)')->rows(6)->disabled()->dehydrated(false)
                                    ->visible(fn () => filled($chapter()['abstract_ai'] ?? null)),
                                self::field(Textarea::make('chapter.abstract_approved')->label('Resumo aprovado (o que será publicado)')->rows(7), 'abstract', $ctx),
                            ]),

                        Section::make('Palavras-chave e referências')->schema([
                            self::field(TagsInput::make('chapter.keywords')->label(fn () => 'Palavras-chave'.(($chapter()['keywords_source'] ?? null) === 'ai' ? ' — sugeridas pela IA' : '')), 'keywords', $ctx),
                            Repeater::make('chapter.references')
                                ->label('Referências')
                                ->schema([
                                    Textarea::make('text')->label('Referência')->rows(2)->columnSpan(3),
                                    TextInput::make('doi')->label('DOI')->maxLength(100)->extraInputAttributes(['class' => 'hub-mono'])->columnSpan(1),
                                ])
                                ->columns(4)
                                ->defaultItems(0)
                                ->addActionLabel('Adicionar referência')
                                ->collapsible()
                                ->collapsed()
                                ->itemLabel(fn (array $state) => mb_substr((string) ($state['text'] ?? ''), 0, 90) ?: 'Referência'),
                        ]),
                    ])->columnSpan(['lg' => 9]),
                ]),
            ]);
    }

    private static function chapterBanner(ReviewImport $page, array $c): HtmlString
    {
        if ($c === []) {
            return new HtmlString('<div class="hub-banner"><span class="hub-pill">Nenhum capítulo na proposta.</span></div>');
        }
        $n = $page->getRecord()->chaptersCount();
        $parts = ['<span class="hub-pill">Capítulo '.($page->current + 1).' de '.$n.'</span>'];
        $parts[] = ! empty($c['approved'])
            ? '<span class="hub-pill hub-pill-ok">Aprovado</span>'
            : '<span class="hub-pill hub-pill-warn">Não aprovado</span>';
        if (($c['status'] ?? '') === 'failed') {
            $parts[] = '<span class="hub-pill hub-pill-danger">A IA não conseguiu analisar: '.e((string) ($c['error'] ?? 'preencha à mão')).'</span>';
        } elseif (($c['status'] ?? '') === 'pending') {
            $parts[] = '<span class="hub-pill hub-pill-danger">Ainda não analisado</span>';
        }
        if ($needs = $c['needs_review'] ?? []) {
            $parts[] = '<span class="hub-pill hub-pill-warn">Revisar: '.e(implode(', ', $needs)).'</span>';
        }
        if (filled($c['pdf_first_page'] ?? null)) {
            $parts[] = self::pageButton((int) $c['pdf_first_page'], 'abrir no PDF (p. '.(int) $c['pdf_first_page'].')');
        }
        if (filled($c['notes'] ?? null)) {
            $parts[] = '<div class="hub-note"><strong>Aviso da IA:</strong> '.e($c['notes']).'</div>';
        }
        if ($toc = $c['toc_authors'] ?? []) {
            $parts[] = '<div class="hub-note">Autores no sumário: '.e(implode('; ', $toc)).'</div>';
        }

        return new HtmlString('<div class="hub-banner">'.implode(' ', $parts).'</div>');
    }

    private static function authorsDescription(array $ctx): HtmlString
    {
        $ev = $ctx['evidence']['authors'] ?? null;

        return self::evidenceHtml($ev, $ev['confidence'] ?? null, in_array('authors', $ctx['needs'], true) ? ['authors'] : [], $ev['source'] ?? null,
            'Ligue cada autor a uma pessoa da obra; sem vínculo, a integração cria uma pessoa nova.');
    }

    private static function abstractDescription(array $c): HtmlString
    {
        $ev = $c['evidence']['abstract'] ?? null;
        $source = $c['abstract_source'] ?? null;
        $lead = match ($source) {
            'pdf' => 'Resumo original do PDF; edite o aprovado se precisar.',
            'ai' => 'Não havia resumo no PDF: a IA sugeriu um (120–250 palavras). Confira com o texto e edite.',
            default => 'Sem resumo: escreva o aprovado.',
        };

        return self::evidenceHtml($ev, $ev['confidence'] ?? null, in_array('abstract', $c['needs_review'] ?? [], true) ? ['abstract'] : [], $source, $lead);
    }

    // ---------------------------------------------------------- Utilitários

    /** Liga o campo à evidência `evidence[$key]` e ao destaque de `needs_review`. */
    private static function field(Field $field, string $key, Closure $ctx): Field
    {
        return $field
            ->helperText(function () use ($key, $ctx) {
                $c = $ctx();
                $k = $c['alias'] ?? $key;
                $ev = $c['evidence'][$k] ?? null;

                return self::evidenceHtml($ev, $ev['confidence'] ?? null, in_array($k, $c['needs'] ?? [], true) ? [$k] : [], $ev['source'] ?? null);
            })
            ->extraFieldWrapperAttributes(function () use ($key, $ctx) {
                $c = $ctx();

                return self::wrapperAttributes($c['alias'] ?? $key, $c);
            });
    }

    private static function wrapperAttributes(string $key, array $ctx): array
    {
        $ev = $ctx['evidence'][$key] ?? null;
        $low = isset($ev['confidence']) && (float) $ev['confidence'] < self::LOW_CONFIDENCE;

        return ['class' => in_array($key, $ctx['needs'] ?? [], true) || $low ? 'hub-needs-review' : ''];
    }

    /** "p. 31 · «trecho» · 98% · [ver página]" */
    public static function evidenceHtml(?array $ev, ?float $confidence, array $flags, ?string $source, ?string $lead = null): HtmlString
    {
        $bits = [];
        if ($lead) {
            $bits[] = e($lead);
        }
        if ($flags) {
            $bits[] = '<span class="hub-flag">⚠ revisar</span>';
        }
        if ($source === 'ai') {
            $bits[] = '<span class="hub-flag hub-flag-ai">sugestão da IA</span>';
        }
        if (is_array($ev)) {
            if (filled($ev['excerpt'] ?? null)) {
                $bits[] = '«'.e(mb_substr((string) $ev['excerpt'], 0, 160)).(mb_strlen((string) $ev['excerpt']) > 160 ? '…' : '').'»';
            }
            if ($confidence !== null) {
                $pct = (int) round($confidence * 100);
                $bits[] = '<span class="'.($confidence < self::LOW_CONFIDENCE ? 'hub-flag' : 'hub-conf').'">confiança '.$pct.'%</span>';
            }
            if (filled($ev['pdf_page'] ?? null)) {
                $bits[] = self::pageButton((int) $ev['pdf_page']);
            }
        } elseif ($ev === null && ! $lead && ! $flags) {
            $bits[] = '<span class="hub-conf">sem evidência da IA</span>';
        }

        return new HtmlString('<span class="hub-ev">'.implode(' · ', $bits).'</span>');
    }

    public static function pageButton(int $page, ?string $label = null): string
    {
        return '<button type="button" class="hub-ev-btn" onclick="window.dispatchEvent(new CustomEvent(\'hub-pdf-page\', {detail: {page: '.$page.'}}))">'
            .e($label ?? "ver p. {$page}").'</button>';
    }
}
