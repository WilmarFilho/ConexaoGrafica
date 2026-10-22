{{--
    Base das fichas científicas em PDF (dompdf). Template inicial, sóbrio e
    institucional, nas cores dos mockups (navy/teal); o layout definitivo do
    Diego entra trocando este arquivo e os dois filhos. Regras do dompdf:
    nada de flex/grid, fontes DejaVu (acentos), tabelas para colunas.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>{{ $title ?? 'Ficha científica' }}</title>
<style>
    @page { margin: 18mm 18mm 22mm 18mm; }
    * { box-sizing: border-box; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; line-height: 1.45; color: #1F2933; margin: 0; }
    a { color: #0F8B8D; text-decoration: none; }
    p { margin: 0 0 6pt 0; }
    h1, h2, h3 { margin: 0; color: #0B2545; font-weight: bold; }
    h1 { font-size: 20pt; line-height: 1.2; }
    h2 { font-size: 11pt; text-transform: uppercase; letter-spacing: 0.6pt; border-bottom: 1.2pt solid #0F8B8D; padding-bottom: 3pt; margin: 16pt 0 8pt 0; }
    h3 { font-size: 10pt; margin: 8pt 0 3pt 0; }

    .band { background: #0B2545; color: #FFFFFF; padding: 9pt 12pt; margin-bottom: 14pt; }
    .band table { width: 100%; border-collapse: collapse; }
    .band td { padding: 0; vertical-align: middle; }
    .brand { font-size: 12.5pt; font-weight: bold; letter-spacing: 1pt; }
    .brand small { display: block; font-size: 7pt; letter-spacing: 2.5pt; font-weight: normal; color: #9FD3D3; }
    .band .kind { text-align: right; font-size: 8pt; color: #CFE3E3; text-transform: uppercase; letter-spacing: 1pt; }

    .tag { display: inline-block; background: #0F8B8D; color: #FFFFFF; font-size: 7.5pt; padding: 1.5pt 6pt; border-radius: 3pt; text-transform: uppercase; letter-spacing: 0.8pt; }
    .tag-light { background: #EEF4F7; color: #0B2545; }
    .doi-tag { display: inline-block; background: #EEF4F7; color: #0B2545; font-size: 8pt; padding: 1.5pt 6pt; border-radius: 3pt; }
    .muted { color: #6B7280; }
    .small { font-size: 8pt; }
    .mono { font-family: "DejaVu Sans Mono", monospace; font-size: 8.5pt; }
    .subtitle { font-size: 12.5pt; color: #3F4E66; margin: 4pt 0 0 0; }
    .alt-title { font-size: 9.5pt; color: #6B7280; font-style: italic; margin-top: 2pt; }
    .lead { font-size: 10pt; }

    .pill { display: inline-block; background: #EEF4F7; color: #0B2545; border: 0.6pt solid #DCE3EA; padding: 1.5pt 6pt; margin: 0 3pt 3pt 0; border-radius: 3pt; font-size: 8.5pt; }

    table.meta { width: 100%; border-collapse: collapse; }
    table.meta td { padding: 3.5pt 6pt; border-bottom: 0.6pt solid #E6EBF0; vertical-align: top; }
    table.meta td.k { width: 32%; color: #6B7280; font-size: 8.5pt; text-transform: uppercase; letter-spacing: 0.4pt; }

    table.toc { width: 100%; border-collapse: collapse; }
    table.toc th { text-align: left; font-size: 8pt; color: #6B7280; text-transform: uppercase; letter-spacing: 0.4pt; padding: 3pt 5pt; border-bottom: 0.8pt solid #0F8B8D; }
    table.toc td { padding: 3.5pt 5pt; border-bottom: 0.6pt solid #E6EBF0; vertical-align: top; }
    table.toc td.n { width: 8%; color: #0F8B8D; font-weight: bold; }
    table.toc td.p { width: 13%; text-align: right; white-space: nowrap; }
    table.toc tr.section td { background: #EEF4F7; color: #0B2545; font-weight: bold; padding: 4pt 5pt; }
    .toc-authors { color: #6B7280; font-size: 8pt; }

    .cite { border-left: 2.5pt solid #0F8B8D; background: #F7FAFB; padding: 6pt 9pt; margin-bottom: 7pt; page-break-inside: avoid; }
    .cite .fmt { font-size: 7.5pt; font-weight: bold; color: #0F8B8D; text-transform: uppercase; letter-spacing: 1pt; margin-bottom: 2pt; }
    .cite .txt { font-size: 9pt; }

    .box { border: 0.8pt solid #DCE3EA; padding: 8pt 10pt; border-radius: 4pt; page-break-inside: avoid; }
    .qr-box { text-align: center; }
    .qr-box img { width: 86pt; height: 86pt; }
    .qr-box .url { font-size: 7pt; color: #6B7280; word-wrap: break-word; }

    .legal { font-size: 7.5pt; color: #6B7280; border-top: 0.6pt solid #DCE3EA; padding-top: 6pt; margin-top: 14pt; }
    .footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 7.5pt; color: #6B7280; border-top: 0.6pt solid #DCE3EA; padding-top: 3pt; }
    .footer table { width: 100%; border-collapse: collapse; }
    .footer td { padding: 0; }
    .footer td.r { text-align: right; }

    .cover { page-break-after: always; }
    .cover .title-block { margin-top: 70pt; }
    .cover h1 { font-size: 26pt; }
    .cover .people { margin-top: 26pt; }
    .cover .people .label { font-size: 8pt; color: #6B7280; text-transform: uppercase; letter-spacing: 1pt; }
    .cover .people .names { font-size: 12pt; color: #0B2545; }
    .cover .publisher { margin-top: 40pt; font-size: 11pt; color: #0B2545; }
    .cover .bottom { margin-top: 60pt; }
    .avoid-break { page-break-inside: avoid; }
    sup { font-size: 6.5pt; color: #0F8B8D; }
    .authors { font-size: 11pt; color: #0B2545; margin: 8pt 0 2pt 0; }
    .affs { font-size: 8.5pt; color: #3F4E66; margin: 0; padding-left: 0; list-style: none; }
    .affs li { margin-bottom: 1pt; }
</style>
</head>
<body>
<div class="footer">
    <table>
        <tr>
            <td>{{ $book['publisher'] ?? 'Conexão Editora' }} · ficha gerada em {{ $generated_at->format('d/m/Y H:i') }} · template v{{ $template_version }}</td>
            <td class="r">{{ $doi_line ?? '' }}</td>
        </tr>
    </table>
</div>

<div class="band">
    <table>
        <tr>
            <td class="brand">{{ mb_strtoupper($book['publisher'] ?? 'Conexão Editora') }}<small>FICHA CIENTÍFICA DE APRESENTAÇÃO</small></td>
            <td class="kind">{{ $kind_label }}</td>
        </tr>
    </table>
</div>

@yield('content')

<p class="legal">
    Esta é uma ficha de apresentação gerada automaticamente a partir dos metadados registrados pela editora.
    Ela não substitui a obra nem o capítulo e não reproduz o seu conteúdo. A página permanente e o DOI são os
    identificadores de referência. {{ $legal_extra ?? '' }}
</p>
</body>
</html>
