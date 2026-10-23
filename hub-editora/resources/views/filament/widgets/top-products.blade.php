<x-filament-widgets::widget>
    <x-filament::section heading="Mais vendidos" description="Últimos 30 dias, pedidos pagos">
        @php $rows = $this->getRows(); @endphp
        @if (! $rows)
            <div class="hub-muted" style="font-size:13px;">Sem vendas no período.</div>
        @else
            <div style="display:flex;flex-direction:column;gap:12px;">
                @foreach ($rows as $r)
                    <div>
                        <div style="display:flex;justify-content:space-between;gap:12px;font-size:13px;">
                            <span style="color:var(--hub-fg);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $r['name'] }}</span>
                            <span class="hub-mono hub-muted" style="flex:none;font-size:12px;">{{ $r['qty'] }} un · {{ $r['money'] }}</span>
                        </div>
                        <div class="hub-bar" style="margin-top:6px;"><span style="width:{{ $r['pct'] }}%;"></span></div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
