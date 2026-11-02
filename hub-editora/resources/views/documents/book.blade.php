{{-- Ficha editorial do livro (layout do Diego): resumo, sumário, dados bibliográficos, referências e direitos autorais. --}}
@php
    $organizers = collect($book['contributors'])->filter(fn ($c) => $c['role'] === ($book['type'] === 'edited_book' ? 'editor' : 'author'));
    if ($organizers->isEmpty()) { $organizers = collect($book['contributors']); }
    $names = $organizers->pluck('name')->implode(', ');
    $label = $organizers->count() > 1 ? ($book['type'] === 'edited_book' ? 'Organizadores' : 'Autores') : ($book['type'] === 'edited_book' ? 'Organizador' : 'Autor');
    $languages = ['pt' => 'Português (Brasil)'] + \App\Models\Book::LANGUAGES;
    $area = trim(($book['subject_area'] ?? '').(filled($book['subject_subarea'] ?? null) ? ' / '.$book['subject_subarea'] : ''));
    $bySection = collect($chapters)->groupBy(fn ($c) => (string) ($c['section'] ?? ''));
    $licenses = $book['licenses'] ?? [];
@endphp
@extends('documents.layout', ['title' => $book['title'].' — ficha editorial do livro', 'footer' => 'Ficha editorial do livro'])

@section('content')
{{-- 01 · resumo --}}
<div class="page">
    @include('documents.partials.top', ['mark' => 'Livro completo · Resumo'])
    <div class="eyebrow">Publicação{{ $area !== '' ? ' · '.$area : '' }}</div>

    <table class="hero"><tr>
        <td>
            <div class="hero-little">Livro · {{ $book['publisher'] ?: 'Conexão Editora' }}</div>
            <div class="hero-title">{{ $book['title'] }}</div>
            @if (filled($book['subtitle'] ?? null))
                <div class="hero-subtitle">{{ $book['subtitle'] }}</div>
            @endif
            @if ($names !== '')
                <div class="hero-people">{{ $label }}: {{ $names }}</div>
            @endif
        </td>
        @if ($cover)
            <td class="capa"><img class="cover" src="{{ $cover }}" alt=""></td>
        @endif
    </tr></table>

    <div class="section">
        <div class="section-title">Resumo do livro</div>
        <div class="bodytext">
            @foreach (preg_split('/\n\s*\n/', trim((string) ($book['abstract'] ?? ''))) as $paragraph)
                @if (trim($paragraph) !== '')<p>{{ trim($paragraph) }}</p>@endif
            @endforeach
        </div>
    </div>

    @if ($book['keywords'])
        <div class="section">
            <div class="section-title">Palavras-chave</div>
            <div>@foreach ($book['keywords'] as $kw)<span class="chip">{{ $kw }}</span>@endforeach</div>
        </div>
    @endif
</div>

{{-- 02 · sumário --}}
<div class="page">
    @include('documents.partials.top', ['mark' => 'Livro completo · Sumário'])
    <div class="eyebrow">Capítulos do livro</div>
    <h2>Sumário do livro</h2>
    <p class="muted">Capítulos apresentados na página editorial do livro.</p>

    <div class="section">
        <table class="toc">
            @foreach ($bySection as $section => $list)
                @if ($section !== '')
                    <tr><td colspan="2" class="toc-section">{{ $section }}</td></tr>
                @endif
                @foreach ($list as $c)
                    @php($people = collect($c['contributors'])->pluck('name')->implode(', '))
                    <tr>
                        <td class="n"><span class="pnum">{{ str_pad((string) ($c['number'] ?: $c['sequence']), 2, '0', STR_PAD_LEFT) }}</span></td>
                        <td>
                            <span class="toc-title">{{ $c['title'] }}@if (filled($c['subtitle'] ?? null)): {{ $c['subtitle'] }}@endif</span>
                            <span class="toc-small">
                                @if ($people !== '')Autores: {{ $people }}@endif
                                @if ($c['first_page'])@if ($people !== '') · @endif p. {{ $c['first_page'] }}–{{ $c['last_page'] }}@endif
                                @if (filled($c['doi'] ?? null)) · DOI {{ $c['doi'] }}@endif
                            </span>
                        </td>
                    </tr>
                @endforeach
            @endforeach
        </table>
    </div>
</div>

{{-- 03 · dados bibliográficos --}}
<div class="page">
    @include('documents.partials.top', ['mark' => 'Livro completo · Dados bibliográficos'])
    <div class="eyebrow">Identificação da publicação</div>
    <h2>Informações bibliográficas</h2>

    <table class="doirow"><tr>
        <td><span class="doirow-label">ISBN · Impresso</span><span class="doirow-value">{{ $book['isbn_print'] ?: 'Não se aplica' }}</span></td>
        <td><span class="doirow-label">ISBN · E-book</span><span class="doirow-value">{{ $book['isbn_ebook'] ?: 'Não se aplica' }}</span></td>
    </tr></table>

    <div class="doibox">
        <span class="doibox-label">DOI do livro</span>
        @if (filled($book['doi'] ?? null))
            <p class="pubtext"><a href="https://doi.org/{{ $book['doi'] }}">https://doi.org/{{ $book['doi'] }}</a></p>
        @else
            <p class="pubtext">Não informado no registro bibliográfico.</p>
        @endif
    </div>

    <div class="section">
        <table class="meta">
            <tr><td class="k">Título</td><td>{{ $book['title'] }}</td></tr>
            @if (filled($book['subtitle'] ?? null))<tr><td class="k">Subtítulo</td><td>{{ $book['subtitle'] }}</td></tr>@endif
            @if ($names !== '')<tr><td class="k">{{ $label }}</td><td>{{ $names }}</td></tr>@endif
            <tr><td class="k">Editora</td><td>{{ $book['publisher'] }}</td></tr>
            <tr><td class="k">Ano de publicação</td><td>{{ $book['publication_year'] }}</td></tr>
            @if (filled($book['edition_number'] ?? null))<tr><td class="k">Edição</td><td>{{ $book['edition_number'] }}ª edição</td></tr>@endif
            @if (filled($book['pages'] ?? null))<tr><td class="k">Extensão</td><td>{{ $book['pages'] }} páginas</td></tr>@endif
            @if (filled($book['format'] ?? null))<tr><td class="k">Formatos</td><td>{{ \App\Models\Book::FORMATS[$book['format']] ?? $book['format'] }}</td></tr>@endif
            <tr><td class="k">Idioma</td><td>{{ $languages[$book['language']] ?? $book['language'] }}</td></tr>
            @if ($area !== '')<tr><td class="k">Área</td><td>{{ $area }}</td></tr>@endif
            @if (filled($book['audience'] ?? null))<tr><td class="k">Público-alvo</td><td>{{ $book['audience'] }}</td></tr>@endif
        </table>
    </div>
</div>

{{-- 04 · referências --}}
<div class="page{{ $licenses ? '' : ' page-last' }}">
    @include('documents.partials.top', ['mark' => 'Livro completo · Referências'])
    <div class="eyebrow">Citação e direitos</div>
    <h2>Referências</h2>
    <p class="bodytext">A referência abaixo identifica o próprio livro e pode ser utilizada para citá-lo. A bibliografia interna consultada pelos autores não integra esta ficha editorial.</p>

    <div class="section">
        <div class="section-title">Como citar este livro · ABNT</div>
        <div class="bibliogr">{!! $book['citations_html']['abnt'] !!}</div>
    </div>
    <div class="section">
        <div class="section-title">Referência · APA</div>
        <div class="bibliogr">{!! $book['citations_html']['apa'] !!}</div>
    </div>
    <div class="section">
        <div class="section-title">Referência · Vancouver</div>
        <div class="bibliogr">{!! $book['citations_html']['vancouver'] !!}</div>
    </div>
    <div class="section">
        <div class="section-title">Publicação</div>
        <p class="bodytext">{{ $book['publisher'] }}<br>{{ $book['publication_place'] }}, Brasil<br>Livro em {{ mb_strtolower($languages[$book['language']] ?? 'português') }}@if (filled($book['format'] ?? null)), {{ ['print' => 'disponível em formato impresso', 'ebook' => 'disponível em formato digital', 'both' => 'disponível em formatos impresso e digital'][$book['format']] ?? '' }}@endif.</p>
    </div>
    @if (filled($landing_url ?? null))
        <div class="section">
            <div class="section-title">Página do livro</div>
            <p class="bodytext"><a href="{{ $landing_url }}">{{ $landing_url }}</a></p>
        </div>
    @endif
</div>

{{-- 05 · direitos autorais --}}
@if ($licenses)
    <div class="page page-last">
        @include('documents.partials.top', ['mark' => 'Livro completo · Direitos autorais'])
        @include('documents.licenses', ['licenses' => $licenses, 'subject' => 'Este livro está licenciado', 'lead' => 'Condições de atribuição, compartilhamento e reutilização do livro.'])
    </div>
@endif
@endsection
