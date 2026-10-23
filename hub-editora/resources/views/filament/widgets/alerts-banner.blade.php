<x-filament-widgets::widget>
    @php $issues = $this->getIssues(); @endphp
    @if ($issues)
        <div class="hub-alert" role="alert">
            <svg class="hub-alert-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l10 18H2z"/><path d="M12 10v5M12 18v.5"/></svg>
            <div style="flex:1;min-width:0;">
                <div class="hub-alert-title">{{ count($issues) === 1 ? '1 integração precisa de atenção' : count($issues).' integrações precisam de atenção' }}</div>
                @foreach ($issues as $i)
                    <div class="hub-alert-body"><strong style="color:#FF8A8A;font-weight:500;">{{ $i['channel'] }}:</strong> {{ $i['message'] }}</div>
                @endforeach
                <div class="hub-alert-foot">Um e-mail é enviado aos usuários quando um canal cai (no máximo a cada 6 horas).</div>
            </div>
            <a href="{{ \App\Filament\Pages\Integracoes::getUrl() }}" style="font-size:13px;">{{ $issues[0]['action'] }} →</a>
        </div>
    @endif
</x-filament-widgets::widget>
