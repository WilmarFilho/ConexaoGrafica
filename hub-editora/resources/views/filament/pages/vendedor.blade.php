<x-filament-panels::page>
    {{-- Cartões de número (componente .hub-kpi do tema). --}}
    <div class="hub-kpis" style="margin-bottom:4px;">
        @foreach ($this->getStats() as $s)
            <div class="hub-kpi">
                <span class="hub-eyebrow">{{ $s['label'] }}</span>
                <span class="hub-kpi-value is-{{ $s['tone'] }}">{{ $s['value'] }}</span>
                @if (! empty($s['desc']))
                    <span class="hub-kpi-desc">{{ $s['desc'] }}</span>
                @endif
            </div>
        @endforeach
    </div>

    {{ $this->table }}
</x-filament-panels::page>
