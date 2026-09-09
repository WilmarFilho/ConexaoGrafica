<x-filament-widgets::widget>
    <x-filament::section heading="Mais vendidos" description="Últimos 30 dias, pedidos pagos">
        @php $rows = $this->getRows(); @endphp
        @if (! $rows)
            <div style="color:#6A6F7A;font-size:13px;">Sem vendas no período.</div>
        @else
            <div style="display:flex;flex-direction:column;gap:10px;">
                @foreach ($rows as $r)
                    <div>
                        <div style="display:flex;justify-content:space-between;gap:12px;font-size:13px;">
                            <span style="color:#24292F;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $r['name'] }}</span>
                            <span class="hub-mono" style="flex:none;color:#6A6F7A;">{{ $r['qty'] }} un · {{ $r['money'] }}</span>
                        </div>
                        <div style="height:6px;background:#EEEBE6;border-radius:3px;margin-top:4px;overflow:hidden;">
                            <div style="height:100%;width:{{ $r['pct'] }}%;background:#2E6BD6;border-radius:3px;"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
