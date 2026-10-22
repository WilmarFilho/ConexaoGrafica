{{-- Ficha científica do capítulo: título, autores com afiliação e ORCID, livro, resumo, como citar, QR, compra. --}}
@php
    $organizers = collect($book['contributors'])->filter(fn ($c) => $c['role'] === ($book['type'] === 'edited_book' ? 'editor' : 'author'));
    if ($organizers->isEmpty()) { $organizers = collect($book['contributors']); }
    $languages = ['pt' => 'Português', 'en' => 'Inglês', 'es' => 'Espanhol', 'fr' => 'Francês', 'it' => 'Italiano', 'de' => 'Alemão'];
    $commercial = collect($book['commercial'])->filter(fn ($l) => $l['enabled']);
    $pages = $chapter['first_page'].($chapter['last_page'] && $chapter['last_page'] !== $chapter['first_page'] ? '–'.$chapter['last_page'] : '');
@endphp
@extends('documents.layout', [
    'title' => 'Ficha científica — '.$chapter['title'],
    'kind_label' => 'Ficha do capítulo',
    'doi_line' => 'DOI '.$chapter['doi'],
])

@section('content')
<table style="width: 100%; border-collapse: collapse;">
    <tr>
        <td style="padding: 0; vertical-align: top;">
            <span class="tag">Capítulo {{ $chapter['number'] ?: $chapter['sequence'] }}</span>
            <span class="doi-tag">DOI {{ $chapter['doi'] }}</span>
            <h1 style="margin-top: 8pt;">{{ $chapter['title'] }}</h1>
            @if ($chapter['subtitle'])
                <p class="subtitle">{{ $chapter['subtitle'] }}</p>
            @endif
            @if ($chapter['title_en'])
                <p class="alt-title">{{ $chapter['title_en'] }}</p>
            @endif

            @if (count($authors))
                <p class="authors">
                    @foreach ($authors as $a)
                        {{ $a['name'] }}@if ($a['affiliation'])<sup>{{ $a['affiliation'] }}</sup>@endif @if (! $loop->last) · @endif
                    @endforeach
                </p>
                <ul class="affs">
                    @foreach ($affiliations as $i => $aff)
                        <li><sup>{{ $i + 1 }}</sup> {{ $aff }}</li>
                    @endforeach
                    @foreach ($authors as $a)
                        @if ($a['orcid'])
                            <li class="muted">{{ $a['name'] }} — ORCID <a href="https://orcid.org/{{ $a['orcid'] }}">https://orcid.org/{{ $a['orcid'] }}</a></li>
                        @endif
                    @endforeach
                </ul>
            @endif
        </td>
        <td style="width: 100pt; padding: 0 0 0 10pt; vertical-align: top;">
            <div class="qr-box">
                <img src="{{ $qr }}" alt="QR Code">
                <div class="url">Página permanente<br>{{ $qr_url }}</div>
            </div>
        </td>
    </tr>
</table>

<h2>Publicado em</h2>
<table class="meta">
    <tr><td class="k">Livro</td><td><b>{{ $book['title'] }}</b>@if ($book['subtitle']): {{ $book['subtitle'] }}@endif</td></tr>
    @if ($organizers->isNotEmpty())
        <tr><td class="k">{{ $organizers_label }}</td><td>{{ $organizers->pluck('name')->implode('; ') }}</td></tr>
    @endif
    <tr><td class="k">Editora</td><td>{{ $book['publisher'] }}, {{ $book['publication_place'] }}, {{ $book['publication_year'] }}@if ($book['edition_number'] && $book['edition_number'] > 1) · {{ $book['edition_number'] }}ª edição @endif</td></tr>
    @if ($book['isbn_print'])<tr><td class="k">ISBN (impresso)</td><td class="mono">{{ $book['isbn_print'] }}</td></tr>@endif
    @if ($book['isbn_ebook'])<tr><td class="k">ISBN (e-book)</td><td class="mono">{{ $book['isbn_ebook'] }}</td></tr>@endif
    <tr><td class="k">DOI do livro</td><td class="mono"><a href="https://doi.org/{{ $book['doi'] }}">https://doi.org/{{ $book['doi'] }}</a></td></tr>
    <tr><td class="k">DOI do capítulo</td><td class="mono"><a href="https://doi.org/{{ $chapter['doi'] }}">https://doi.org/{{ $chapter['doi'] }}</a></td></tr>
    <tr><td class="k">Páginas</td><td>{{ $pages }}</td></tr>
    @if ($chapter['section'])<tr><td class="k">Seção</td><td>{{ $chapter['section'] }}</td></tr>@endif
    @if ($chapter['publication_date'])<tr><td class="k">Data de publicação</td><td>{{ \Carbon\Carbon::parse($chapter['publication_date'])->format('d/m/Y') }}</td></tr>@endif
    <tr><td class="k">Idioma</td><td>{{ $languages[$chapter['language']] ?? $chapter['language'] }}</td></tr>
    <tr><td class="k">Área</td><td>{{ $book['subject_area'] }}@if ($book['subject_subarea']) · {{ $book['subject_subarea'] }}@endif</td></tr>
    @if ($landing_url)<tr><td class="k">Página permanente</td><td><a href="{{ $landing_url }}">{{ $landing_url }}</a></td></tr>@endif
</table>

@if ($chapter['abstract'])
    <div class="avoid-break">
        <h2>Resumo</h2>
        <p class="lead">{{ $chapter['abstract'] }}</p>
    </div>
@endif

@if ($chapter['keywords'])
    <div class="avoid-break">
    <h2>Palavras-chave</h2>
    <div>@foreach ($chapter['keywords'] as $kw)<span class="pill">{{ $kw }}</span>@endforeach</div>
    </div>
@endif

<h2>Como citar este capítulo</h2>
@foreach (['abnt', 'apa', 'vancouver'] as $fmt)
    <div class="cite">
        <div class="fmt">{{ \App\Editorial\Citations::LABELS[$fmt] }}</div>
        <div class="txt">{!! $chapter['citations_html'][$fmt] !!}</div>
    </div>
@endforeach

<div class="box avoid-break" style="margin-top: 10pt;">
    <h3 style="margin-top: 0;">O livro completo</h3>
    @if ($commercial->isNotEmpty())
        <p class="small">Disponível em: {{ $commercial->pluck('label')->map(fn ($l) => str_replace('Comprar ', '', $l))->implode(' · ') }}.</p>
    @endif
    @if ($book_landing_url)
        <p class="small">Comprar o livro e ver os demais capítulos: <a href="{{ $book_landing_url }}">{{ $book_landing_url }}</a></p>
    @else
        <p class="small">Página do livro: <a href="https://doi.org/{{ $book['doi'] }}">https://doi.org/{{ $book['doi'] }}</a></p>
    @endif
</div>
@endsection
