{{-- Pessoas únicas da proposta (obra + capítulos), só leitura. Recebe $people. --}}
@if (! empty($people))
    <table class="hub-people">
        <thead>
            <tr>
                <th>Pessoa (obra + capítulos)</th>
                <th>Papéis</th>
                <th>Capítulos</th>
                <th>ORCID</th>
                <th>Instituição</th>
                <th>Pendência</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($people as $p)
                <tr>
                    <td><strong>{{ $p['name'] ?? '' }}</strong><br><span class="hub-note">{{ $p['sort_name'] ?? '' }}</span></td>
                    <td>{{ collect($p['roles'] ?? [])->map(fn ($r) => \App\Models\Contribution::ROLES[$r] ?? $r)->implode(', ') }}</td>
                    <td class="hub-mono">{{ implode(', ', $p['chapters'] ?? []) ?: '—' }}</td>
                    <td class="hub-mono">{{ $p['orcid'] ?? '—' }}</td>
                    <td>{{ $p['affiliation_text'] ?? '—' }}</td>
                    <td>
                        @if (! empty($p['needs_review']))
                            <span class="hub-flag">⚠ {{ in_array('affiliation', $p['needs_review'], true) ? 'sem ORCID nem instituição' : implode(', ', $p['needs_review']) }}</span>
                        @else
                            <span class="hub-conf">ok</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
