{{-- Lista lateral de capítulos da revisão (Conexão DOI, frente E2). Recebe $chapters e $current. --}}
<div class="hub-chapters" wire:loading.class="opacity-50">
    <div class="hub-chapters-nav">
        <button type="button" wire:click="goTo({{ $current - 1 }})" @disabled($current <= 0)>‹ Anterior</button>
        <button type="button" wire:click="goTo({{ $current + 1 }})" @disabled($current >= count($chapters) - 1)>Próximo ›</button>
    </div>

    @forelse ($chapters as $c)
        @php
            $classes = ['hub-chapter'];
            if ($c['index'] === $current) $classes[] = 'hub-chapter-current';
            if ($c['approved']) $classes[] = 'hub-chapter-approved';
            elseif ($c['status'] !== 'suggested') $classes[] = 'hub-chapter-failed';
            elseif ($c['needs'] > 0) $classes[] = 'hub-chapter-needs';
        @endphp
        <button type="button" class="{{ implode(' ', $classes) }}" wire:click="goTo({{ $c['index'] }})" title="{{ $c['title'] }}">
            <span class="hub-chapter-n">{{ $c['number'] ?? $c['sequence'] }}</span>
            <span style="min-width:0;flex:1;">
                <span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $c['title'] }}</span>
                <span class="hub-chapter-meta">
                    @if ($c['approved']) ✓ aprovado
                    @elseif ($c['status'] === 'failed') ✗ falhou
                    @elseif ($c['status'] === 'pending') pendente
                    @elseif ($c['needs'] > 0) ⚠ {{ $c['needs'] }} a revisar
                    @else ok
                    @endif
                    @if ($c['pages']) · p. {{ $c['pages'] }} @endif
                    @if ($c['ai_abstract']) · resumo IA @endif
                </span>
            </span>
        </button>
    @empty
        <p class="hub-note">A proposta não tem capítulos.</p>
    @endforelse
</div>
