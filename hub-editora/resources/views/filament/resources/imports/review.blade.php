<x-filament-panels::page>
    @php
        /** @var \App\Models\Import $import */
        $import = $this->getRecord();
        $r = $import->result ?? [];
    @endphp

    {{-- Estilos da tela: inline porque o CSS do Filament não traz utilitários avulsos (ver expedicao.blade.php). --}}
    <style>
        .hub-review { display: grid; grid-template-columns: minmax(0, 5fr) minmax(0, 7fr); gap: 16px; align-items: start; }
        @media (max-width: 1100px) { .hub-review { grid-template-columns: minmax(0, 1fr); } .hub-review-pdf { position: static !important; height: 70vh !important; } }
        .hub-review-pdf { position: sticky; top: 1rem; height: calc(100vh - 7rem); display: flex; flex-direction: column; background: #fff; border: 1px solid #E6E3DD; border-radius: 12px; overflow: hidden; }
        .hub-review-pdf-bar { display: flex; align-items: center; gap: 10px; padding: 8px 12px; border-bottom: 1px solid #E6E3DD; font-size: 12px; color: #6A6F7A; }
        .hub-review-pdf-bar input { width: 64px; padding: 2px 6px; border: 1px solid #D6D3CD; border-radius: 6px; font-size: 12px; }
        .hub-review-pdf-bar a, .hub-review-pdf-bar button { color: #2E6BD6; font-weight: 600; background: none; border: 0; cursor: pointer; }
        .hub-review-pdf-holder { flex: 1; min-height: 0; }
        .hub-pdf-frame { width: 100%; height: 100%; border: 0; display: block; }
        .hub-review-form { min-width: 0; }
        .hub-needs-review { background: #FFF8E1; border: 1px solid #F3D27A; border-radius: 10px; padding: 8px 10px; }
        .hub-banner { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 4px; }
        .hub-pill { display: inline-block; font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 999px; background: #EFEDE8; color: #44474F; }
        .hub-pill-ok { background: #E8F5EC; color: #1B5E33; }
        .hub-pill-warn { background: #FFF4E0; color: #8A4B0A; }
        .hub-pill-danger { background: #FDECEC; color: #8F1D1D; }
        .hub-note { width: 100%; font-size: 12px; color: #6A6F7A; }
        .hub-ev { font-size: 12px; color: #6A6F7A; }
        .hub-ev-btn { color: #2E6BD6; font-weight: 600; background: none; border: 0; padding: 0; cursor: pointer; }
        .hub-ev-btn:hover { text-decoration: underline; }
        .hub-flag { color: #8A4B0A; font-weight: 600; }
        .hub-flag-ai { color: #2558B5; }
        .hub-conf { color: #1B5E33; }
        .hub-chapters { display: flex; flex-direction: column; gap: 6px; }
        .hub-chapters-nav { display: flex; gap: 6px; margin-bottom: 6px; }
        .hub-chapters-nav button { flex: 1; font-size: 12px; font-weight: 600; padding: 6px 8px; border: 1px solid #D6D3CD; border-radius: 8px; background: #fff; color: #2E6BD6; cursor: pointer; }
        .hub-chapters-nav button:disabled { color: #B0B3BA; cursor: default; }
        .hub-chapter { display: flex; gap: 8px; align-items: flex-start; width: 100%; text-align: left; padding: 8px 10px; border: 1px solid #E6E3DD; border-radius: 10px; background: #fff; cursor: pointer; font-size: 13px; line-height: 1.3; }
        .hub-chapter:hover { border-color: #2E6BD6; }
        .hub-chapter-current { border-color: #2E6BD6; background: #EAF2FC; }
        .hub-chapter-approved { border-left: 4px solid #15803D; }
        .hub-chapter-failed { border-left: 4px solid #B91C1C; }
        .hub-chapter-needs { border-left: 4px solid #B45309; }
        .hub-chapter-n { font-family: 'IBM Plex Mono', monospace; color: #6A6F7A; min-width: 2ch; }
        .hub-chapter-meta { display: block; font-size: 11px; color: #6A6F7A; margin-top: 2px; }
        .hub-people { width: 100%; font-size: 13px; border-collapse: collapse; margin-top: 12px; }
        .hub-people th, .hub-people td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #E6E3DD; vertical-align: top; }
        .hub-people th { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #6A6F7A; }
        .hub-warnings { background: #FFF4E0; border: 1px solid #F3D27A; border-radius: 10px; padding: 10px 14px; font-size: 13px; color: #8A4B0A; margin-bottom: 4px; }
        .hub-status-card { background: #fff; border: 1px solid #E6E3DD; border-radius: 12px; padding: 20px 24px; display: flex; flex-direction: column; gap: 8px; }
    </style>

    @if (! $import->hasProposal())
        {{-- Ainda na fila / processando / erro: acompanha e oferece reprocessar. --}}
        <div class="hub-status-card" @if ($import->isProcessing() || $import->status === \App\Models\Import::RECEIVED) wire:poll.10s @endif>
            <div class="hub-banner">
                <span class="hub-pill {{ $import->status === \App\Models\Import::ERROR ? 'hub-pill-danger' : 'hub-pill-warn' }}">{{ $import->statusLabel() }}</span>
                <span class="hub-note">{{ $import->original_name }} · enviado em {{ $import->created_at?->format('d/m/Y H:i') }}{{ $import->creator ? ' por '.$import->creator->name : '' }}</span>
            </div>
            @if ($import->status === \App\Models\Import::ERROR)
                <p style="color:#8F1D1D;font-size:14px;"><strong>Falhou:</strong> {{ $import->error }}</p>
                <p class="hub-note">Use "Reprocessar" para chamar a IA de novo com o mesmo PDF. Se o PDF não tem camada de texto, não há o que fazer nesta versão (OCR fora do escopo).</p>
            @elseif ($import->status === \App\Models\Import::RECEIVED)
                <p class="hub-note">Na fila. O processamento começa quando o worker (queue:work database) pegar o job; esta página atualiza sozinha.</p>
            @else
                <p class="hub-note">A IA está lendo o PDF ({{ $import->pages ? $import->pages.' páginas' : 'contando páginas' }}). Um livro grande leva dezenas de minutos; esta página atualiza sozinha.</p>
                @if (! empty($r['summary']))
                    <p class="hub-note">Capítulos analisados até agora: {{ $r['summary']['analyzed'] ?? 0 }} de {{ $r['summary']['chapters'] ?? 0 }}.</p>
                @endif
            @endif
        </div>
    @else
        @if (! empty($r['warnings']))
            <div class="hub-warnings">
                <strong>Avisos da importação:</strong>
                <ul style="margin:4px 0 0 18px;list-style:disc;">
                    @foreach ($r['warnings'] as $w)
                        <li>{{ $w }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="hub-review">
            <div
                class="hub-review-pdf"
                wire:ignore
                x-data="{
                    base: @js($this->pdfUrl()),
                    page: {{ $this->initialPdfPage() }},
                    go(p) {
                        p = parseInt(p) || 1;
                        this.page = p;
                        // Um iframe novo garante que o visualizador abra na página pedida (só trocar o #page nem sempre move o Chrome).
                        const f = document.createElement('iframe');
                        f.className = 'hub-pdf-frame';
                        f.title = 'PDF da importação';
                        f.src = this.base + '#page=' + p + '&zoom=page-width';
                        this.$refs.holder.replaceChildren(f);
                    }
                }"
                x-init="go(page)"
                x-on:hub-pdf-page.window="go($event.detail.page)"
            >
                <div class="hub-review-pdf-bar">
                    <span>PDF · página</span>
                    <input type="number" min="1" x-model.lazy="page" x-on:change="go($event.target.value)" aria-label="Página do PDF">
                    <button type="button" x-on:click="go(page - 1)">‹</button>
                    <button type="button" x-on:click="go(page + 1)">›</button>
                    <span style="margin-left:auto;">{{ $import->pages ? $import->pages.' páginas' : '' }}</span>
                    <a href="{{ $this->pdfUrl() }}" target="_blank" rel="noopener">abrir em nova aba</a>
                </div>
                <div class="hub-review-pdf-holder" x-ref="holder"></div>
            </div>

            <div class="hub-review-form">
                <form wire:submit="save">
                    {{ $this->form }}
                </form>
            </div>
        </div>
    @endif
</x-filament-panels::page>
