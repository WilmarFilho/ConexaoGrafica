<x-filament-panels::page>
    {{-- Números do dia. Estilos inline porque o CSS do Filament não traz utilitários avulsos. --}}
    @php
        $tones = [
            'warning' => ['bg' => '#FFF4E0', 'fg' => '#8A4B0A'],
            'info' => ['bg' => '#EAF2FC', 'fg' => '#1E3A5F'],
            'danger' => ['bg' => '#FDECEC', 'fg' => '#8F1D1D'],
            'success' => ['bg' => '#E8F5EC', 'fg' => '#1B5E33'],
        ];
    @endphp
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:4px;">
        @foreach ($this->getStats() as $s)
            <div style="background:#fff;border:1px solid #E6E3DD;border-radius:12px;padding:14px 16px;display:flex;flex-direction:column;gap:6px;">
                <span style="font-size:12px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:#6A6F7A;">{{ $s['label'] }}</span>
                <span class="hub-mono" style="font-size:26px;font-weight:600;line-height:1;color:{{ $tones[$s['tone']]['fg'] }};">{{ $s['value'] }}</span>
            </div>
        @endforeach
    </div>

    {{ $this->table }}
</x-filament-panels::page>
