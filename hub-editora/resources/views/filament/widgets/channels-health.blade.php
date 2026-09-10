<x-filament-widgets::widget>
    <x-filament::section heading="Canais" description="Configuração e última sincronização">
        @php
            $dot = ['ok' => '#15803D', 'off' => '#B45309', 'error' => '#B91C1C'];
        @endphp
        <div style="display:flex;flex-direction:column;gap:10px;">
            @foreach ($this->getRows() as $r)
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:8px 0;border-bottom:1px solid #EEEBE6;">
                    <div style="display:flex;align-items:center;gap:10px;min-width:0;">
                        <span style="flex:none;width:9px;height:9px;border-radius:50%;background:{{ $dot[$r['state']] }};"></span>
                        <div style="min-width:0;">
                            <div style="font-weight:600;color:#24292F;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $r['name'] }}</div>
                            <div style="font-size:12px;color:#6A6F7A;">
                                {{ $r['label'] }}@if ($r['sync']) · sync {{ $r['sync'] }}@endif
                                @if ($r['errors']) · <span style="color:#B91C1C">{{ $r['errors'] }} erro(s) desde o último sucesso</span>@endif
                            </div>
                        </div>
                    </div>
                    @if ($r['orders30'] !== null)
                        <div class="hub-mono" style="flex:none;text-align:right;font-size:13px;color:#24292F;">
                            {{ $r['orders30'] }}<span style="color:#6A6F7A;font-size:11px;"> / 30d</span>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        <div style="margin-top:10px;font-size:12px;">
            <a href="{{ \App\Filament\Pages\Integracoes::getUrl() }}" style="color:#2E6BD6;font-weight:600;">Abrir integrações →</a>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
