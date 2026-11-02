{{-- Ficha editorial do capítulo (layout do Diego): resumo, autores, dados bibliográficos, referências e direitos autorais. --}}
@php
    $organizers = collect($book['contributors'])->filter(fn ($c) => $c['role'] === 'editor');
    $languages = ['pt' => 'Português (Brasil)'] + \App\Models\Book::LANGUAGES;
    $area = trim(($book['subject_area'] ?? '').(filled($book['subject_subarea'] ?? null) ? ' / '.$book['subject_subarea'] : ''));
    $number = (string) ($chapter['number'] ?: $chapter['sequence']);
    $names = collect($authors)->pluck('name')->implode(', ');
    $licenses = $chapter['licenses'] ?? [];
    $references = (array) ($chapter['references'] ?? []);
@endphp
@extends('documents.layout', ['title' => $chapter['title'].' — ficha editorial do capítulo', 'footer' => 'Ficha editorial do capítulo'])

@section('content')
{{-- 01 · resumo --}}
<div class="page">
    @include('documents.partials.top', ['mark' => 'Capítulo '.$number.' · Resumo'])
    <div class="eyebrow">Capítulo de livro{{ $area !== '' ? ' · '.$area : '' }}</div>

    <table class="hero"><tr>
        <td>
            <div class="hero-little">Capítulo {{ $number }} · {{ $book['title'] }}</div>
            <div class="hero-title">{{ $chapter['title'] }}</div>
            @if (filled($chapter['subtitle'] ?? null))
                <div class="hero-subtitle">{{ $chapter['subtitle'] }}</div>
            @endif
            @if ($names !== '')
                <div class="hero-people">{{ count($authors) > 1 ? 'Autores' : 'Autor' }}: {{ $names }}</div>
            @endif
        </td>
        @if ($cover)
            <td class="capa"><img class="cover" src="{{ $cover }}" alt=""></td>
        @endif
    </tr></table>

    <div class="section">
        <div class="section-title">Resumo do capítulo</div>
        <div class="bodytext">
            @foreach (preg_split('/\n\s*\n/', trim((string) ($chapter['abstract'] ?? ''))) as $paragraph)
                @if (trim($paragraph) !== '')<p>{{ trim($paragraph) }}</p>@endif
            @endforeach
        </div>
    </div>

    @if ($chapter['keywords'])
        <div class="section">
            <div class="section-title">Palavras-chave</div>
            <div>@foreach ($chapter['keywords'] as $kw)<span class="chip">{{ $kw }}</span>@endforeach</div>
        </div>
    @endif
</div>

{{-- 02 · autores --}}
<div class="page">
    @include('documents.partials.top', ['mark' => 'Capítulo '.$number.' · Autores'])
    <div class="eyebrow">Autoria</div>
    <h2>Autores e afiliações</h2>
    <div class="section">
        @foreach ($authors as $a)
            <div class="listline">
                <b>{{ $a['name'] }}</b>@if ($a['affiliation'])<sup>{{ $a['affiliation'] }}</sup>@endif
                @if (filled($a['orcid']))<br><span class="muted" style="font-size: 10px;">ORCID <a href="https://orcid.org/{{ $a['orcid'] }}">https://orcid.org/{{ $a['orcid'] }}</a></span>@endif
            </div>
        @endforeach
    </div>
    @if ($affiliations)
        <div class="section">
            <div class="section-title">Afiliações</div>
            @foreach ($affiliations as $i => $aff)
                <div class="listline"><b>{{ $i + 1 }}.</b> {{ $aff }}</div>
            @endforeach
        </div>
    @endif
</div>

{{-- 03 · dados bibliográficos --}}
<div class="page">
    @include('documents.partials.top', ['mark' => 'Capítulo '.$number.' · Dados bibliográficos'])
    <div class="eyebrow">Identificação da publicação</div>
    <h2>Informações bibliográficas</h2>

    <table class="doirow"><tr>
        <td><span class="doirow-label">DOI do capítulo</span><span class="doirow-value">{{ filled($chapter['doi'] ?? null) ? $chapter['doi'] : 'Não se aplica' }}</span></td>
        <td><span class="doirow-label">Páginas</span><span class="doirow-value">{{ $chapter['first_page'] }}–{{ $chapter['last_page'] }}</span></td>
    </tr></table>

    <div class="doibox">
        <span class="doibox-label">Publicado em</span>
        <p class="pubtext">{{ $book['title'] }}@if (filled($book['subtitle'] ?? null)): {{ $book['subtitle'] }}@endif @if (filled($book['doi'] ?? null))· DOI do livro <a href="https://doi.org/{{ $book['doi'] }}">{{ $book['doi'] }}</a>@endif</p>
    </div>

    <div class="section">
        <table class="meta">
            <tr><td class="k">Título</td><td>{{ $chapter['title'] }}</td></tr>
            @if ($names !== '')<tr><td class="k">{{ count($authors) > 1 ? 'Autores' : 'Autor' }}</td><td>{{ $names }}</td></tr>@endif
            <tr><td class="k">Livro</td><td>{{ $book['title'] }}</td></tr>
            @if ($organizers->isNotEmpty())<tr><td class="k">{{ $organizers->count() > 1 ? 'Organizadores' : 'Organizador' }}</td><td>{{ $organizers->pluck('name')->implode(', ') }}</td></tr>@endif
            <tr><td class="k">Editora</td><td>{{ $book['publisher'] }}</td></tr>
            <tr><td class="k">Ano de publicação</td><td>{{ $book['publication_year'] }}</td></tr>
            <tr><td class="k">Idioma</td><td>{{ $languages[$chapter['language'] ?? $book['language']] ?? ($chapter['language'] ?? $book['language']) }}</td></tr>
            @if (filled($book['isbn_print'] ?? null))<tr><td class="k">ISBN (impresso)</td><td>{{ $book['isbn_print'] }}</td></tr>@endif
            @if (filled($book['isbn_ebook'] ?? null))<tr><td class="k">ISBN (e-book)</td><td>{{ $book['isbn_ebook'] }}</td></tr>@endif
            @if ($area !== '')<tr><td class="k">Área</td><td>{{ $area }}</td></tr>@endif
        </table>
    </div>
</div>

{{-- 04 · referências --}}
<div class="page{{ $licenses ? '' : ' page-last' }}">
    @include('documents.partials.top', ['mark' => 'Capítulo '.$number.' · Referências'])
    <div class="eyebrow">Citação e direitos</div>
    <h2>Referências</h2>

    <div class="section">
        <div class="section-title">Como citar este capítulo · ABNT</div>
        <div class="bibliogr">{!! $chapter['citations_html']['abnt'] !!}</div>
    </div>
    <div class="section">
        <div class="section-title">Referência · APA</div>
        <div class="bibliogr">{!! $chapter['citations_html']['apa'] !!}</div>
    </div>
    <div class="section">
        <div class="section-title">Referência · Vancouver</div>
        <div class="bibliogr">{!! $chapter['citations_html']['vancouver'] !!}</div>
    </div>

    @if (filled($landing_url ?? null))
        <div class="section">
            <div class="section-title">Página do capítulo</div>
            <p class="bodytext"><a href="{{ $landing_url }}">{{ $landing_url }}</a></p>
        </div>
    @endif

    @if ($references)
        <div class="section">
            <div class="section-title">Referências do capítulo</div>
            @foreach ($references as $ref)
                <div class="listline">{{ $ref['text'] ?? '' }}@if (filled($ref['doi'] ?? null)) <a href="https://doi.org/{{ $ref['doi'] }}">https://doi.org/{{ $ref['doi'] }}</a>@endif</div>
            @endforeach
        </div>
    @endif
</div>

{{-- 05 · direitos autorais --}}
@if ($licenses)
    <div class="page page-last">
        @include('documents.partials.top', ['mark' => 'Capítulo '.$number.' · Direitos autorais'])
        @include('documents.licenses', ['licenses' => $licenses, 'subject' => 'Este capítulo está licenciado', 'lead' => 'Condições de atribuição, compartilhamento e reutilização do capítulo.'])
    </div>
@endif
@endsection
