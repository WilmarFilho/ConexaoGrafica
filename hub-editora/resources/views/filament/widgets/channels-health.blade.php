<x-filament-widgets::widget>
    <x-filament::section heading="Canais" description="Configuração e última sincronização">
        @php
            $dot = ['ok' => '#5BD98A', 'off' => '#F5B544', 'error' => '#FF8A8A'];
        @endphp
        <div>
            @foreach ($this->getRows() as $r)
                <div class="hub-list-row" style="align-items:center;justify-content:space-between;">
                    <div style="display:flex;align-items:flex-start;gap:10px;min-width:0;">
                        <span class="hub-dot" style="background:{{ $dot[$r['state']] }};margin-top:6px;"></span>
                        <div style="min-width:0;">
                            <div style="font-size:13px;font-weight:500;color:#F2F2F2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $r['name'] }}</div>
                            <div class="hub-muted" style="font-size:12px;">
                                {{ $r['label'] }}@if ($r['sync']) · sync {{ $r['sync'] }}@endif
                                @if ($r['errors']) · <span style="color:#FF8A8A">{{ $r['errors'] }} erro(s) desde o último sucesso</span>@endif
                            </div>
                        </div>
                    </div>
                    @if ($r['orders30'] !== null)
                        <div class="hub-mono" style="flex:none;text-align:right;font-size:12.5px;color:#C9C9C9;">
                            {{ $r['orders30'] }} / 30d
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        <div style="margin-top:12px;font-size:13px;">
            <a href="{{ \App\Filament\Pages\Integracoes::getUrl() }}" style="color:#5B9BFF;">Abrir integrações →</a>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
