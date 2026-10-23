<?php

namespace App\Filament\Resources\Books\Schemas;

use App\Editorial\BookWorkflow;
use App\Editorial\DoiSuffix;
use App\Editorial\WordPress\Payload;
use App\Filament\Resources\Books\BookActions;
use App\Filament\Resources\Books\Pages\EditBook;
use App\Filament\Resources\Books\RelationManagers\ChaptersRelationManager;
use App\Models\Book;
use App\Models\Channel;
use App\Models\CommercialLink;
use App\Models\Person;
use App\Models\Product;
use App\Models\ProductChannelRef;
use App\Rules\Isbn13;
use App\Rules\Orcid;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * As 8 etapas do cadastro de livro (contrato, seção 1; telas do Diego):
 * Dados da obra → Autores → Publicação → Identificadores e DOI → Resumo →
 * Capítulos → Comercialização → Revisão.
 *
 * Na criação, o assistente é linear e termina salvando o rascunho; na edição
 * as etapas viram abas livres, a 6 recebe os capítulos e a 8 as ações.
 */
class BookWizard
{
    /** @return array<Step> */
    public static function steps(bool $editing): array
    {
        return [
            self::stepObra(),
            self::stepAutores(),
            self::stepPublicacao(),
            self::stepIdentificadores(),
            self::stepResumo(),
            self::stepCapitulos($editing),
            self::stepComercializacao($editing),
            self::stepRevisao($editing),
        ];
    }

    private static function stepObra(): Step
    {
        return Step::make('Dados da obra')
            ->icon(Heroicon::OutlinedBookOpen)
            ->columns(2)
            ->schema([
                TextInput::make('title')->label('Título da obra')->required()->maxLength(500)->columnSpanFull(),
                TextInput::make('subtitle')->label('Subtítulo')->maxLength(500)->columnSpanFull(),
                TextInput::make('alt_title')->label('Título alternativo')->maxLength(500),
                Select::make('alt_title_lang')->label('Idioma do título alternativo')->options(Book::LANGUAGES)->default('en')->native(false),
                Select::make('type')->label('Tipo de obra')->options(Book::TYPES)->default('edited_book')->required()->native(false),
                TextInput::make('edition_number')->label('Edição')->numeric()->minValue(1)->maxValue(99)->placeholder('1'),
                TextInput::make('series_title')->label('Coleção / série')->maxLength(255),
                TextInput::make('series_issn')->label('ISSN da série')->maxLength(9)->placeholder('0000-0000')
                    ->helperText('Com ISSN, a Crossref recebe a obra como livro de série.'),
                TextInput::make('volume')->label('Volume')->maxLength(20),
                Select::make('language')->label('Idioma principal')->options(Book::LANGUAGES)->default('pt')->required()->native(false),
                Select::make('extra_languages')->label('Outros idiomas')->options(Book::LANGUAGES)->multiple()->native(false),
                Select::make('product_id')->label('Produto do catálogo do Hub')
                    ->relationship('product', 'name')
                    ->searchable(['name', 'sku', 'isbn'])->preload()->native(false)
                    ->helperText('Opcional. O produto já aponta para a loja; a capa pública vem dele.'),
                FileUpload::make('cover_path')->label('Capa (uso interno)')
                    ->image()->disk('local')->directory('capas')->visibility('private')->maxSize(4096)
                    ->helperText('Fica privada no Hub. A capa do site vem do produto da loja.')
                    ->columnSpanFull(),
            ]);
    }

    private static function stepAutores(): Step
    {
        return Step::make('Autores e organizadores')
            ->icon(Heroicon::OutlinedUserGroup)
            ->schema([
                Text::make('Organizadores da obra (papel "Organizador" = editor na Crossref) e demais colaboradores, na ordem em que aparecem. Pessoa e instituição podem ser criadas aqui mesmo. Para ser publicada, cada pessoa precisa de ORCID ou instituição.')
                    ->color('gray'),
                ContributionsRepeater::make('editor', 'Pessoas da obra'),
            ]);
    }

    private static function stepPublicacao(): Step
    {
        return Step::make('Publicação')
            ->icon(Heroicon::OutlinedBuildingLibrary)
            ->columns(2)
            ->schema([
                TextInput::make('publisher')->label('Editora')->required()->maxLength(255)->default('Conexão Editora'),
                TextInput::make('publication_place')->label('Cidade de publicação')->required()->maxLength(255)->default('Goiânia'),
                TextInput::make('publication_year')->label('Ano de publicação')->numeric()->required()
                    ->minValue(1900)->maxValue(2100)->default((int) date('Y'))->live(onBlur: true),
                DatePicker::make('publication_date')->label('Data de publicação')->native(false)->displayFormat('d/m/Y'),
                TextInput::make('pages')->label('Total de páginas')->numeric()->minValue(1),
                Select::make('format')->label('Formato')->options(Book::FORMATS)->default('both')->required()->native(false),
                TextInput::make('subject_area')->label('Área de conhecimento')->required()->maxLength(255)
                    ->datalist(fn () => Book::query()->distinct()->orderBy('subject_area')->pluck('subject_area')->all())
                    ->helperText('Texto livre; sugere as áreas já usadas.'),
                TextInput::make('subject_subarea')->label('Subárea')->maxLength(255)
                    ->datalist(fn () => Book::query()->whereNotNull('subject_subarea')->distinct()->orderBy('subject_subarea')->pluck('subject_subarea')->all()),
                Textarea::make('audience')->label('Público-alvo')->rows(2)->maxLength(500)->columnSpanFull(),
            ]);
    }

    private static function stepIdentificadores(): Step
    {
        return Step::make('Identificadores e DOI')
            ->icon(Heroicon::OutlinedHashtag)
            ->columns(2)
            ->schema([
                TextInput::make('isbn_print')->label('ISBN impresso')->maxLength(17)->placeholder('978-65-…')->rule(new Isbn13),
                TextInput::make('isbn_ebook')->label('ISBN e-book')->maxLength(17)->placeholder('978-65-…')->rule(new Isbn13),
                Repeater::make('other_isbns')->label('Outros ISBNs')
                    ->columns(2)->defaultItems(0)->addActionLabel('Adicionar ISBN')
                    ->schema([
                        TextInput::make('type')->label('Tipo')->required()->maxLength(60)->placeholder('Ex.: audiolivro, 1ª edição'),
                        TextInput::make('value')->label('ISBN')->required()->maxLength(17)->rule(new Isbn13),
                    ])
                    ->columnSpanFull(),

                TextInput::make('doi_prefix')->label('Prefixo DOI atribuído à editora')
                    ->default(fn () => DoiSuffix::prefix())
                    ->disabled()->dehydrated()
                    ->extraInputAttributes(['class' => 'hub-mono']),
                TextInput::make('doi_suffix')->label('Sufixo DOI do livro')
                    ->required()->maxLength(60)
                    ->regex(DoiSuffix::PATTERN)
                    ->validationMessages(['regex' => 'Use só letras minúsculas, números, ponto, hífen e sublinhado.'])
                    ->default(fn () => DoiSuffix::forBook((int) date('Y')))
                    ->disabled(fn (?Book $record) => $record && ! $record->isDoiEditable())
                    ->dehydrated()
                    ->live(onBlur: true)
                    ->extraInputAttributes(['class' => 'hub-mono'])
                    ->rule(fn (?Book $record) => function (string $attribute, mixed $value, Closure $fail) use ($record) {
                        $prefix = $record?->doi_prefix ?: DoiSuffix::prefix();
                        if (filled($value) && ! DoiSuffix::isAvailable($prefix, strtolower((string) $value), $record)) {
                            $fail('Este sufixo já é usado por outro livro ou capítulo.');
                        }
                    })
                    ->suffixAction(
                        Action::make('sugerir')
                            ->label('Sugerir')
                            ->icon(Heroicon::OutlinedSparkles)
                            ->tooltip('Próximo sufixo livre do ano de publicação')
                            ->visible(fn (?Book $record) => ! $record || $record->isDoiEditable())
                            ->action(fn (Set $set, Get $get, ?Book $record) => $set('doi_suffix', DoiSuffix::forBook(
                                (int) ($get('publication_year') ?: date('Y')),
                                $record?->doi_prefix ?: DoiSuffix::prefix(),
                            )))
                    )
                    ->helperText('Sugerido pelo sistema: conexao.{ano}.{NNN}. Editável só enquanto rascunho; depois de validado não muda.'),

                Placeholder::make('doi_preview')->label('Prévia do DOI')
                    ->content(fn (Get $get, ?Book $record) => new HtmlString(
                        '<span class="hub-mono">'.e(($record?->doi_prefix ?: DoiSuffix::prefix()).'/'.($get('doi_suffix') ?: '…')).'</span>'
                    )),

                Text::make('O prefixo '.DoiSuffix::DEFAULT_PREFIX.' é demonstrativo e não representa um prefixo real. Configure o prefixo oficial da editora (CROSSREF_PREFIX) antes de um depósito em produção. O DOI só é considerado registrado depois da confirmação da Crossref.')
                    ->color('warning')
                    ->visible(fn (?Book $record) => DoiSuffix::isDemoPrefix($record?->doi_prefix ?: null)),

                Actions::make([
                    Action::make('verificar')
                        ->label('Verificar campos')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('gray')
                        ->action(function (Get $get, ?Book $record) {
                            $problems = self::verify($get, $record);

                            $problems
                                ? Notification::make()->title('Pendências encontradas')->body(implode("\n", $problems))->warning()->persistent()->send()
                                : Notification::make()->title('Tudo certo')->body('ISBN, DOI e pessoas conferidos.')->success()->send();
                        }),
                ])->columnSpanFull(),
            ]);
    }

    /** "Verificar campos": ISBN, sufixo DOI (formato e unicidade) e regra do Romário nas pessoas. */
    private static function verify(Get $get, ?Book $record): array
    {
        $problems = [];

        foreach (['isbn_print' => 'ISBN impresso', 'isbn_ebook' => 'ISBN e-book'] as $field => $label) {
            if (filled($get($field)) && ! Isbn13::isValid($get($field))) {
                $problems[] = "{$label} inválido.";
            }
        }
        foreach ((array) $get('other_isbns') as $other) {
            if (filled($other['value'] ?? null) && ! Isbn13::isValid($other['value'])) {
                $problems[] = 'Outro ISBN inválido: '.$other['value'];
            }
        }
        if (blank($get('isbn_print')) && blank($get('isbn_ebook')) && empty($get('other_isbns'))) {
            $problems[] = 'Informe ao menos um ISBN.';
        }

        $prefix = $record?->doi_prefix ?: DoiSuffix::prefix();
        $suffix = strtolower((string) $get('doi_suffix'));
        if (! DoiSuffix::isWellFormed($suffix)) {
            $problems[] = 'Sufixo DOI mal formado.';
        } elseif (! DoiSuffix::isAvailable($prefix, $suffix, $record)) {
            $problems[] = 'Sufixo DOI já usado por outro livro ou capítulo.';
        }

        $rows = (array) $get('contributions');
        if ($rows === []) {
            $problems[] = 'Nenhuma pessoa vinculada à obra (etapa 2).';
        }
        foreach ($rows as $row) {
            $person = filled($row['person_id'] ?? null) ? Person::find($row['person_id']) : null;
            if (! $person) {
                continue;
            }
            if (filled($person->orcid) && ! Orcid::isValid($person->orcid)) {
                $problems[] = "{$person->name}: ORCID inválido.";
            }
            if (blank($person->orcid) && blank($row['institution_id'] ?? null) && blank($row['affiliation_text'] ?? null)) {
                $problems[] = "{$person->name}: sem ORCID nem instituição.";
            }
        }

        return $problems;
    }

    private static function stepResumo(): Step
    {
        return Step::make('Resumo')
            ->icon(Heroicon::OutlinedDocumentText)
            ->schema([
                Textarea::make('abstract')->label('Resumo / sinopse científica')->required()->rows(6)->maxLength(5000),
                TagsInput::make('keywords')->label('Palavras-chave')->splitKeys(['Tab', ','])->placeholder('Digite e pressione Enter'),
                Textarea::make('abstract_en')->label('Abstract (inglês)')->rows(4)->maxLength(5000),
                TagsInput::make('keywords_en')->label('Keywords (inglês)')->splitKeys(['Tab', ',']),
                Textarea::make('synopsis')->label('Sinopse comercial')->rows(3)->maxLength(5000)
                    ->helperText('Texto da página do site; não vai para a Crossref.'),
                Repeater::make('references')->label('Referências da obra')
                    ->defaultItems(0)->collapsed()->addActionLabel('Adicionar referência')->columns(4)
                    ->itemLabel(fn (array $state) => Str::limit($state['text'] ?? 'Referência', 80))
                    ->schema([
                        Textarea::make('text')->label('Referência')->required()->rows(2)->columnSpan(3),
                        TextInput::make('doi')->label('DOI')->maxLength(100)->placeholder('10.…'),
                    ]),
                Text::make('Resumos gerados por IA devem ser revisados editorialmente antes da publicação.')->color('gray'),
            ]);
    }

    private static function stepCapitulos(bool $editing): Step
    {
        return Step::make('Capítulos')
            ->icon(Heroicon::OutlinedListBullet)
            ->schema($editing
                ? [
                    Livewire::make(ChaptersRelationManager::class, fn (Book $record) => [
                        'ownerRecord' => $record,
                        'pageClass' => EditBook::class,
                    ]),
                ]
                : [
                    Text::make('Conclua o assistente para salvar o livro como rascunho. Os capítulos são cadastrados em seguida, nesta mesma etapa: um a um, por "Importar JSON" ou pela importação com IA.')
                        ->color('gray'),
                ]);
    }

    private static function stepComercializacao(bool $editing): Step
    {
        return Step::make('Comercialização')
            ->icon(Heroicon::OutlinedShoppingBag)
            ->schema([
                Text::make('Cada modalidade liga a página pública ao produto WooCommerce pelo ID (preço, estoque e carrinho reais). Só use URL quando não houver produto. O sistema não altera a loja; sem produto a página fica no ar sem o botão.')
                    ->color('gray'),
                Repeater::make('commercialLinks')->label('Modalidades de compra')
                    ->relationship()
                    ->orderColumn('sort_order')
                    ->reorderableWithButtons()
                    ->maxItems(3)
                    ->default($editing ? [] : CommercialLink::defaults())
                    ->addActionLabel('Adicionar modalidade')
                    ->itemLabel(fn (array $state) => CommercialLink::MODALITIES[$state['modality'] ?? ''] ?? 'Modalidade')
                    ->columns(12)
                    ->schema([
                        Select::make('modality')->label('Modalidade')->options(CommercialLink::MODALITIES)->required()->native(false)->columnSpan(3),
                        TextInput::make('label')->label('Texto do botão')->required()->maxLength(120)->columnSpan(3),
                        Select::make('woo_product_id')->label('Produto na loja (Woo)')
                            ->options(fn () => self::wooProducts())
                            ->searchable()->native(false)
                            ->placeholder('Buscar no catálogo')
                            ->columnSpan(3),
                        TextInput::make('url')->label('URL (sem produto)')
                            ->url()->maxLength(255)
                            ->regex(CommercialLink::URL_PATTERN)
                            ->validationMessages(['regex' => 'Só HTTPS no domínio conexaoeditora.com.br.'])
                            ->columnSpan(2),
                        Toggle::make('enabled')->label('Ativo')->default(true)->inline(false)->columnSpan(1),
                    ]),
            ]);
    }

    /** Produtos da loja WooCommerce vinculados no catálogo do Hub: id Woo → "Título · Formato (ID)". */
    public static function wooProducts(): array
    {
        return ProductChannelRef::query()
            ->whereHas('channel', fn ($query) => $query->where('slug', Channel::WOOCOMMERCE))
            ->with('product')
            ->get()
            ->filter(fn (ProductChannelRef $record) => ctype_digit((string) $record->external_id))
            ->sortBy(fn (ProductChannelRef $record) => $record->product?->name)
            ->mapWithKeys(fn (ProductChannelRef $record) => [(int) $record->external_id => ($record->product?->name ?? 'Produto')
                .($record->format ? ' · '.(Product::FORMATS[$record->format] ?? $record->format) : '')
                ." (ID {$record->external_id})"])
            ->all();
    }

    private static function stepRevisao(bool $editing): Step
    {
        if (! $editing) {
            return Step::make('Revisão')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->schema([
                    Text::make('Ao concluir, o livro é salvo como rascunho. Depois, cadastre os capítulos e volte a esta etapa para conferir o checklist, enviar à revisão, aprovar e publicar no site.')
                        ->color('gray'),
                ]);
        }

        return Step::make('Revisão')
            ->icon(Heroicon::OutlinedShieldCheck)
            ->schema([
                Section::make('Dados bibliográficos')
                    ->schema([
                        Text::make(fn (Book $record) => new HtmlString(self::bibliographicHtml($record->fresh() ?? $record))),
                    ]),
                Section::make('Checklist')
                    ->description('Itens com ✓ estão prontos. Os obrigatórios travam o envio para revisão; os de aprovação travam a aprovação.')
                    ->schema([
                        Text::make(fn (Book $record) => new HtmlString(self::checklistHtml($record->fresh() ?? $record))),
                    ]),
                Actions::make(BookActions::transitions(saveFirst: true)),
                Text::make('Registro do DOI: depois de aprovar e publicar no site, use "Registrar DOI" na tela de resumo do livro (botão Visualizar), disponível para os perfis Depósito e Administrador.')
                    ->color('gray')
                    ->visible(fn () => (bool) auth()->user()?->canDeposit()),
                Section::make('Prévia do payload enviado ao site')
                    ->description('Exatamente o que o PUT /wp-json/conexao-doi/v1/publicacoes/{id} recebe.')
                    ->collapsed()
                    ->schema([
                        Text::make(fn (Book $record) => new HtmlString(self::payloadHtml($record->fresh() ?? $record))),
                    ]),
            ]);
    }

    // ---- Trechos de HTML da etapa 8 e da tela de resumo ----------------------

    public static function bibliographicHtml(Book $book): string
    {
        $book->loadMissing(['contributions.person', 'chapters']);

        $rows = [
            'Obra' => $book->title.($book->subtitle ? ': '.$book->subtitle : ''),
            'Organizadores/autores' => $book->contributorsLine() ?: '—',
            'Editora' => trim($book->publisher.', '.$book->publication_place.', '.$book->publication_year),
            'DOI' => '<span class="hub-mono">'.e($book->doi).'</span>',
            'ISBN impresso' => $book->isbn_print ? '<span class="hub-mono">'.e($book->isbn_print).'</span>' : 'Não informado',
            'ISBN e-book' => $book->isbn_ebook ? '<span class="hub-mono">'.e($book->isbn_ebook).'</span>' : 'Não informado',
            'Capítulos' => (string) $book->chapters->count(),
            'Situação' => e($book->statusLabel()).' · DOI: '.e($book->doiStatusLabel()),
            'Página pública' => $book->landing_url ? '<a href="'.e($book->landing_url).'" target="_blank" rel="noopener" style="text-decoration:underline">'.e($book->landing_url).'</a>' : 'ainda não publicada',
        ];

        $html = '<dl style="display:grid;grid-template-columns:max-content 1fr;gap:4px 16px;font-size:.9rem">';
        foreach ($rows as $k => $v) {
            $safe = in_array($k, ['DOI', 'ISBN impresso', 'ISBN e-book', 'Situação', 'Página pública'], true) ? $v : e($v);
            $html .= '<dt style="color:#8D8D8D">'.e($k).'</dt><dd style="font-weight:500">'.$safe.'</dd>';
        }

        return $html.'</dl>';
    }

    public static function checklistHtml(Book $book): string
    {
        $html = '<ul style="display:grid;gap:6px;font-size:.9rem">';
        foreach (BookWorkflow::checklist($book) as $item) {
            [$mark, $color] = $item['ok'] ? ['✓', '#5BD98A'] : ($item['stage'] === 'info' ? ['○', '#8D8D8D'] : ['⚠', '#F5B544']);
            $stage = match ($item['stage']) {
                'review' => 'obrigatório',
                'approve' => 'para aprovar',
                default => 'opcional',
            };
            $html .= '<li><span style="color:'.$color.';font-weight:600">'.$mark.'</span> '.e($item['label'])
                .' <span style="color:#8D8D8D;font-size:.8rem">('.$stage.')</span>'
                .(! $item['ok'] && $item['detail'] ? '<div style="color:#8D8D8D;font-size:.8rem;margin-left:1.4em">'.e($item['detail']).'</div>' : '')
                .'</li>';
        }

        return $html.'</ul>';
    }

    public static function payloadHtml(Book $book): string
    {
        $json = json_encode(Payload::forBook($book), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return '<pre class="hub-mono" style="font-size:.78rem;line-height:1.4;max-height:28rem;overflow:auto;white-space:pre-wrap">'.e((string) $json).'</pre>';
    }
}
