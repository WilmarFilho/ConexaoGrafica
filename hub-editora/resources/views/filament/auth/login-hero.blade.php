{{-- Metade editorial do login (redesign out/2026). Sem números do negócio: a tela é pública. --}}
<section class="hub-login-hero">
    <div class="hub-login-hero-brand">@include('filament.brand')</div>

    <div class="hub-login-hero-copy">
        <div class="hub-eyebrow" style="color:var(--hub-accent);margin-bottom:22px;">— Painel interno · Conexão Editora</div>
        <h1>Pedidos, expedição<br>e DOI num só<br><span>lugar.</span></h1>
        <p>Loja, landing pages, Amazon e Melhor Envio sincronizados. Livros com DOI registrados na Crossref sem sair daqui.</p>
    </div>

    <div class="hub-login-hero-foot hub-eyebrow">Acesso restrito à equipe · 2FA obrigatório</div>

    <svg class="hub-login-hero-ornament" viewBox="0 0 400 400" aria-hidden="true">
        <g style="stroke: var(--hub-border-sidebar)" stroke-width="1" fill="none"><path d="M0 100h400M0 200h400M0 300h400M100 0v400M200 0v400M300 0v400"/></g>
        <rect x="200" y="100" width="100" height="100" style="fill: var(--hub-accent-surface)"/>
        <rect x="100" y="200" width="100" height="100" style="fill: var(--hub-accent-surface)"/>
        <circle cx="250" cy="250" r="34" fill="none" style="stroke: var(--hub-accent)" stroke-width="1.5"/>
        <circle cx="250" cy="250" r="6" style="fill: var(--hub-accent)"/>
    </svg>
</section>
