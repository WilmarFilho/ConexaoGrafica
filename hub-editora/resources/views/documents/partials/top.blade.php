{{-- Topo de cada página da ficha: logo à esquerda e o marcador da seção à direita. --}}
<table class="top"><tr>
    <td>@if ($logo)<img class="logo" src="{{ $logo }}" alt="Conexão Editora">@endif</td>
    <td class="topmark">{{ $mark }}</td>
</tr></table>
