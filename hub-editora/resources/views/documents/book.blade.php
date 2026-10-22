{{-- Ficha científica do livro: capa + identificação, resumo, sumário, como citar, QR. --}}
@php
    $organizers = collect($book['contributors'])->filter(fn ($c) => $c['role'] === ($book['type'] === 'edited_book' ? 'editor' : 'author'));
    if ($organizers->isEmpty()) { $organizers = collect($book['contributors']); }
    $formats = ['print' => 'Impresso', 'ebook' => 'E-book', 'both' => 'Impresso e e-book'];
    $types = ['edited_book' => 'Livro organizado', 'monograph' => 'Monografia', 'reference' => 'Obra de referência', 'other' => 'Livro'];
    $languages = ['pt' => 'Português', 'en' => 'Inglês', 'es' => 'Espanhol', 'fr' => 'Francês', 'it' => 'Italiano', 'de' => 'Alemão'];
    $bySection = collect($chapters)->groupBy(fn ($c) => (string) ($c['section'] ?? ''));
    $commercial = collect($book['commercial'])->filter(fn ($l) => $l['enabled']);
@endphp
@extends('documents.layout', [
    'title' => 'Ficha científica — '.$book['title'],
    'kind_label' => 'Ficha do livro',
    'doi_line' => 'DOI '.$book['doi'],
])

@section('content')
<div class="cover">
    <div class="title-block">
        <span class="tag">{{ $types[$book['type']] ?? 'Livro' }}</span>
        <h1 style="margin-top: 10pt;">{{ $book['title'] }}</h1>
        @if ($book['subtitle'])
            <p class="subtitle">{{ $book['subtitle'] }}</p>
        @endif
        @if ($book['alt_title'])
            <p class="alt-title">{{ $book['alt_title'] }}</p>
        @endif
    </div>

    @if ($organizers->isNotEmpty())
        <div class="people">
            <div class="label">{{ $organizers_label }}</div>
            <div class="names">{{ $organizers->pluck('name')->implode(' · ') }}</div>
        </div>
    @endif

    <div class="publisher">
        {{ $book['publisher'] }} · {{ $book['publication_place'] }} · {{ $book['publication_year'] }}
        @if ($book['edition_number'] && $book['edition_number'] > 1)
            · {{ $book['edition_number'] }}ª edição
        @endif
        @if ($book['series'])
            <br><span class="muted small">{{ $book['series']['title'] }}@if ($book['series']['issn']) · ISSN {{ $book['series']['issn'] }}@endif @if ($book['volume']) · v. {{ $book['volume'] }}@endif</span>
        @endif
    </div>

    <table class="bottom" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="vertical-align: bottom; padding: 0;">
                <div class="muted small">DOI</div>
                <div class="mono"><a href="https://doi.org/{{ $book['doi'] }}">https://doi.org/{{ $book['doi'] }}</a></div>
                @if ($book['isbn_print'])<div class="muted small" style="margin-top: 5pt;">ISBN (impresso) <span class="mono">{{ $book['isbn_print'] }}</span></div>@endif
                @if ($book['isbn_ebook'])<div class="muted small">ISBN (e-book) <span class="mono">{{ $book['isbn_ebook'] }}</span></div>@endif
            </td>
            <td style="width: 110pt; text-align: right; vertical-align: bottom; padding: 0;">
                <div class="qr-box">
                    <img src="{{ $qr }}" alt="QR Code">
                    <div class="url">Página permanente<br>{{ $qr_url }}</div>
                </div>
            </td>
        </tr>
    </table>
</div>

<h2>Identificação</h2>
<table class="meta">
    <tr><td class="k">Título</td><td>{{ $book['title'] }}@if ($book['subtitle']): {{ $book['subtitle'] }}@endif</td></tr>
    @if ($organizers->isNotEmpty())
        <tr><td class="k">{{ $organizers_label }}</td><td>
            @foreach ($organizers as $o)
                {{ $o['name'] }}@if (! empty($o['affiliation']['name'])) <span class="muted small">— {{ $o['affiliation']['name'] }}</span>@endif @if ($o['orcid']) <span class="small mono muted">ORCID {{ $o['orcid'] }}</span>@endif<br>
            @endforeach
        </td></tr>
    @endif
    <tr><td class="k">DOI</td><td class="mono"><a href="https://doi.org/{{ $book['doi'] }}">https://doi.org/{{ $book['doi'] }}</a></td></tr>
    @if ($book['isbn_print'])<tr><td class="k">ISBN (impresso)</td><td class="mono">{{ $book['isbn_print'] }}</td></tr>@endif
    @if ($book['isbn_ebook'])<tr><td class="k">ISBN (e-book)</td><td class="mono">{{ $book['isbn_ebook'] }}</td></tr>@endif
    <tr><td class="k">Editora</td><td>{{ $book['publisher'] }}, {{ $book['publication_place'] }}</td></tr>
    <tr><td class="k">Publicação</td><td>{{ $book['publication_year'] }}@if ($book['publication_date']) ({{ \Carbon\Carbon::parse($book['publication_date'])->format('d/m/Y') }})@endif @if ($book['edition_number']) · {{ $book['edition_number'] }}ª edição @endif</td></tr>
    @if ($book['pages'])<tr><td class="k">Páginas</td><td>{{ $book['pages'] }}</td></tr>@endif
    <tr><td class="k">Formato</td><td>{{ $formats[$book['format']] ?? $book['format'] }}</td></tr>
    <tr><td class="k">Idioma</td><td>{{ $languages[$book['language']] ?? $book['language'] }}</td></tr>
    <tr><td class="k">Área</td><td>{{ $book['subject_area'] }}@if ($book['subject_subarea']) · {{ $book['subject_subarea'] }}@endif</td></tr>
    @if ($book['audience'])<tr><td class="k">Público</td><td>{{ $book['audience'] }}</td></tr>@endif
    @if ($landing_url)<tr><td class="k">Página permanente</td><td><a href="{{ $landing_url }}">{{ $landing_url }}</a></td></tr>@endif
</table>

@if ($book['abstract'])
    <div class="avoid-break">
        <h2>Resumo</h2>
        <p class="lead">{{ $book['abstract'] }}</p>
    </div>
@endif
@if ($book['synopsis'])
    <h3>Sinopse</h3>
    <p>{{ $book['synopsis'] }}</p>
@endif

@if ($book['keywords'])
    <div class="avoid-break">
    <h2>Palavras-chave</h2>
    <div>@foreach ($book['keywords'] as $kw)<span class="pill">{{ $kw }}</span>@endforeach</div>
    @if ($book['keywords_en'])
        <div class="muted small" style="margin-top: 3pt;">Keywords: {{ implode('; ', $book['keywords_en']) }}</div>
    @endif
    </div>
@endif

@if (count($chapters))
    <h2>Sumário</h2>
    <table class="toc">
        <tr><th>Cap.</th><th>Título e autores</th><th style="text-align: right;">Páginas</th></tr>
        @foreach ($bySection as $section => $list)
            @if ($section !== '')
                <tr class="section"><td colspan="3">{{ $section }}</td></tr>
            @endif
            @foreach ($list as $c)
                <tr>
                    <td class="n">{{ $c['number'] ?: $c['sequence'] }}</td>
                    <td>
                        {{ $c['title'] }}@if ($c['subtitle']): {{ $c['subtitle'] }}@endif
                        <div class="toc-authors">{{ collect($c['contributors'])->pluck('name')->implode('; ') }} · DOI {{ $c['doi'] }}</div>
                    </td>
                    <td class="p">{{ $c['first_page'] }}–{{ $c['last_page'] }}</td>
                </tr>
            @endforeach
        @endforeach
    </table>
@endif

<h2>Como citar este livro</h2>
@foreach (['abnt', 'apa', 'vancouver'] as $fmt)
    <div class="cite">
        <div class="fmt">{{ \App\Editorial\Citations::LABELS[$fmt] }}</div>
        <div class="txt">{!! $book['citations_html'][$fmt] !!}</div>
    </div>
@endforeach

@if ($commercial->isNotEmpty() || $landing_url)
    <div class="box avoid-break" style="margin-top: 10pt;">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 0; vertical-align: middle;">
                    <h3 style="margin-top: 0;">Onde encontrar a obra</h3>
                    @if ($commercial->isNotEmpty())
                        <p class="small">Disponível em: {{ $commercial->pluck('label')->map(fn ($l) => str_replace('Comprar ', '', $l))->implode(' · ') }}.</p>
                    @endif
                    @if ($landing_url)
                        <p class="small">Página do livro, com compra e capítulos: <a href="{{ $landing_url }}">{{ $landing_url }}</a></p>
                    @endif
                </td>
                <td style="width: 100pt; text-align: right; padding: 0;">
                    <div class="qr-box"><img src="{{ $qr }}" alt="QR Code"></div>
                </td>
            </tr>
        </table>
    </div>
@endif
@endsection
