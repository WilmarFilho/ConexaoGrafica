<x-filament-widgets::widget>
    @php $issues = $this->getIssues(); @endphp
    @if ($issues)
        <div style="background:#FDECEC;border:1px solid #F3C2C2;border-radius:12px;padding:14px 18px;display:flex;flex-direction:column;gap:8px;">
            <div style="display:flex;align-items:center;gap:8px;font-weight:700;color:#8F1D1D;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#B91C1C" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
                {{ count($issues) === 1 ? 'Uma integração precisa de atenção' : count($issues).' integrações precisam de atenção' }}
            </div>
            <ul style="margin:0;padding-left:26px;color:#24292F;font-size:14px;display:flex;flex-direction:column;gap:4px;">
                @foreach ($issues as $i)
                    <li><strong>{{ $i['channel'] }}</strong>: {{ $i['message'] }}</li>
                @endforeach
            </ul>
            <div style="padding-left:26px;font-size:13px;">
                <a href="{{ \App\Filament\Pages\Integracoes::getUrl() }}" style="color:#2E6BD6;font-weight:600;">{{ $issues[0]['action'] }} →</a>
                <span style="color:#6A6F7A;"> · Um e-mail é enviado aos usuários quando um canal cai (no máximo a cada 6 horas).</span>
            </div>
        </div>
    @endif
</x-filament-widgets::widget>
