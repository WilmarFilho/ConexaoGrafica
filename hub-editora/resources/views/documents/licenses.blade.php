{{--
    Direitos autorais (layout do Diego): as sete licenças Creative Commons em cartões,
    a(s) atribuída(s) em destaque com o selo "Licença atribuída".
    $licenses: licenças da obra (expandidas); $subject: "Este livro está licenciado" ou do capítulo.
--}}
@php
    $chosen = array_column($licenses, 'code');
    $all = \App\Editorial\License::expand(array_keys(\App\Editorial\License::ALL), $subject);
@endphp
<div class="eyebrow" style="margin-top: 12px;">Licenciamento e compartilhamento</div>
<h2>Direitos autorais</h2>
<p class="rights-lead">{{ $lead }}</p>
@foreach ($all as $l)
    @php($active = in_array($l['code'], $chosen, true))
    <table class="license {{ $active ? 'license-active' : 'license-inactive' }}"><tr>
        <td class="icons">
            @foreach ($l['elements'] as $el)<span class="cc">{{ $el === 'zero' ? 'CC0' : strtoupper($el) }}</span>@endforeach
        </td>
        <td>
            @if ($active)<span class="license-pill">Licença atribuída</span>@endif
            <div class="license-title">{{ $l['name'] }}</div>
            <div class="license-text">{{ $l['statement'] }}@if ($active) <a href="{{ $l['url'] }}">{{ $l['url'] }}</a>@endif</div>
        </td>
    </tr></table>
@endforeach
