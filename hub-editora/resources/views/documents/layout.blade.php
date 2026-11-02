{{--
    Base das fichas em PDF (dompdf), no layout do Diego (conexao-livro.netlify.app):
    páginas A4 com logo e marcador no topo, faixa azul-marinho com a capa, seções
    com rótulo azul, cartões de licença e rodapé com a numeração "01 / 05".
    Regras do dompdf: nada de flex/grid (tabelas fazem as colunas) e só pesos
    normal/negrito por família, por isso a Nunito entra em três famílias.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>{{ $title ?? 'Ficha editorial' }}</title>
<style>
    @font-face { font-family: "Nunito"; font-weight: normal; src: url("{{ resource_path('fonts/nunito-400.ttf') }}") format("truetype"); }
    @font-face { font-family: "Nunito"; font-weight: bold; src: url("{{ resource_path('fonts/nunito-700.ttf') }}") format("truetype"); }
    @font-face { font-family: "Nunito Semi"; font-weight: normal; src: url("{{ resource_path('fonts/nunito-600.ttf') }}") format("truetype"); }
    @font-face { font-family: "Nunito Semi"; font-weight: bold; src: url("{{ resource_path('fonts/nunito-800.ttf') }}") format("truetype"); }
    @font-face { font-family: "Nunito Black"; font-weight: normal; src: url("{{ resource_path('fonts/nunito-900.ttf') }}") format("truetype"); }
    @font-face { font-family: "Nunito Black"; font-weight: bold; src: url("{{ resource_path('fonts/nunito-900.ttf') }}") format("truetype"); }

    @page { margin: 16mm 18mm 22mm 18mm; }
    * { box-sizing: border-box; }
    body { font-family: "Nunito", "DejaVu Sans", sans-serif; font-size: 12px; color: #182a3c; margin: 0; }
    a { color: #0197D4; text-decoration: none; }
    p { margin: 0; }
    .preto { font-family: "Nunito Black", "DejaVu Sans", sans-serif; font-weight: normal; }

    .page { page-break-after: always; }
    .page-last { page-break-after: auto; }

    /* topo: logo à esquerda, marcador à direita, linha embaixo */
    table.top { width: 100%; border-collapse: collapse; border-bottom: 1px solid #dce9f0; }
    table.top td { padding: 0 0 12px 0; vertical-align: middle; height: 45px; }
    .logo { width: 145px; height: auto; display: block; }
    .topmark { text-align: right; font-family: "Nunito"; font-weight: bold; font-size: 9px; letter-spacing: 1.1px; text-transform: uppercase; color: #677685; }

    .eyebrow { font-family: "Nunito Black"; font-size: 9px; color: #0197D4; letter-spacing: 2px; text-transform: uppercase; margin: 19px 0 8px 0; }
    h2 { font-family: "Nunito Black"; font-weight: normal; font-size: 20px; color: #00448B; margin: 0 0 13px 0; }
    h3 { font-family: "Nunito Black"; font-weight: normal; font-size: 13px; color: #00448B; margin: 0 0 9px 0; }

    /* faixa azul com título e capa */
    table.hero { width: 100%; border-collapse: separate; background: #00448B; border-radius: 12px; margin-top: 22px; }
    table.hero td { padding: 26px 24px; vertical-align: middle; color: #ffffff; }
    table.hero td.capa { width: 175px; padding-left: 0; text-align: right; }
    .hero-little { font-family: "Nunito Semi"; font-weight: bold; font-size: 10px; letter-spacing: 1.8px; text-transform: uppercase; color: #89defd; }
    .hero-title { font-family: "Nunito Black"; font-size: 27px; line-height: 1.07; color: #ffffff; margin: 12px 0 7px 0; }
    .hero-subtitle { font-family: "Nunito Semi"; font-weight: bold; font-size: 13px; line-height: 1.35; color: #d9f5ff; }
    .hero-people { font-size: 11px; line-height: 1.5; color: #d9effd; margin-top: 14px; }
    .cover { width: 150px; max-height: 213px; border-radius: 5px; }

    .section { margin-top: 20px; }
    .section-title { font-family: "Nunito Black"; font-size: 10px; color: #0197D4; letter-spacing: 1.6px; text-transform: uppercase; margin: 0 0 10px 0; }
    .bodytext { font-size: 11.5px; line-height: 1.67; color: #354657; }
    .bodytext p + p { margin-top: 8px; }
    .muted { color: #5d7082; font-size: 12px; }

    .chip { display: inline-block; padding: 6px 10px; margin: 0 4px 6px 0; background: #e9f7fd; color: #176388; font-family: "Nunito"; font-weight: bold; font-size: 10px; border-radius: 7px; }

    /* sumário */
    table.toc { width: 100%; border-collapse: collapse; }
    table.toc td { padding: 12px 0; border-bottom: 1px solid #e2eaf0; vertical-align: top; }
    table.toc td.n { width: 40px; }
    .pnum { display: inline-block; width: 27px; height: 27px; line-height: 27px; text-align: center; border-radius: 14px; background: #e5f6fc; color: #00448B; font-family: "Nunito Black"; font-size: 11px; }
    .toc-title { font-family: "Nunito"; font-weight: bold; font-size: 13px; color: #00448B; }
    .toc-small { display: block; color: #6d7f90; margin-top: 3px; font-size: 10px; }
    .toc-section { font-family: "Nunito Black"; font-size: 10px; color: #0197D4; letter-spacing: 1.6px; text-transform: uppercase; padding: 14px 0 4px 0; }

    /* dados bibliográficos */
    table.doirow { width: 100%; border-collapse: separate; border-spacing: 10px 0; margin: 0 -10px; }
    table.doirow td { width: 50%; background: #f3faff; border: 1px solid #d7ebf7; border-radius: 8px; padding: 13px; vertical-align: top; }
    .doirow-label { display: block; color: #698091; font-size: 10px; margin-bottom: 5px; }
    .doirow-value { font-family: "Nunito"; font-weight: bold; font-size: 13px; color: #00448B; }
    .doibox { background: #edf8fd; border-left: 4px solid #0197D4; padding: 14px 16px; margin-top: 10px; border-top-right-radius: 8px; border-bottom-right-radius: 8px; }
    .doibox-label { font-family: "Nunito"; font-weight: bold; color: #00448B; }
    .pubtext { font-size: 11px; line-height: 1.6; color: #405568; margin-top: 6px; }
    table.meta { width: 100%; border-collapse: collapse; }
    table.meta td { padding: 10px 6px; border-bottom: 1px solid #e4edf3; vertical-align: top; font-size: 11px; font-family: "Nunito"; font-weight: bold; color: #253d56; }
    table.meta td.k { width: 34%; color: #647889; }

    /* referências e citação */
    .bibliogr { padding: 16px; background: #edf8fd; border-left: 4px solid #0197D4; font-size: 11.5px; line-height: 1.7; color: #2b4660; }
    .listline { padding: 10px 0; border-bottom: 1px solid #dce9f0; font-size: 11px; line-height: 1.55; color: #354657; }
    .listline b { color: #00448B; }

    /* direitos autorais: sete cartões, o atribuído em destaque */
    .rights-lead { margin: 0 0 8px 0; color: #60778a; font-size: 12px; line-height: 1.6; }
    table.license { width: 100%; border-collapse: separate; margin-bottom: 4px; border-radius: 12px; page-break-inside: avoid; }
    table.license td { padding: 6px 12px; vertical-align: middle; }
    table.license td.icons { width: 106px; }
    .license-inactive { background: #f4f6f8; border: 2px solid #e2e7eb; color: #82919d; }
    .license-active { background: #effaff; border: 2px solid #0197D4; color: #00448B; }
    .cc { display: inline-block; width: 24px; height: 24px; line-height: 21px; text-align: center; border-radius: 12px; font-family: "DejaVu Sans", sans-serif; font-weight: bold; font-size: 7.5px; margin-right: 3px; }
    .license-inactive .cc { border: 2px solid #c9d1d7; color: #a2adb5; }
    .license-active .cc { border: 2px solid #0197D4; color: #0197D4; }
    .license-title { font-family: "Nunito Black"; font-size: 10.5px; line-height: 1.15; margin-bottom: 3px; }
    .license-text { font-size: 8.4px; line-height: 1.3; }
    .license-pill { float: right; background: #0197D4; color: #ffffff; font-family: "Nunito Semi"; font-weight: bold; font-size: 9px; border-radius: 12px; padding: 4px 8px; margin: -2px 0 4px 8px; }

    /* rodapé fixo (a numeração "01 / 05" é desenhada pelo gerador) */
    .footer { position: fixed; bottom: -12mm; left: 0; right: 0; border-top: 1px solid #dce9f0; padding-top: 8px; color: #8294a4; font-size: 9px; }
</style>
</head>
<body>
<div class="footer">Conexão Editora · {{ $footer ?? 'Ficha editorial' }}</div>
@yield('content')
</body>
</html>
