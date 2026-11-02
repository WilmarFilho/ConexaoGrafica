{{-- Direitos autorais: a licença Creative Commons atribuída (uma ou mais), com os ícones e a frase. --}}
@if (! empty($licenses))
    <div class="avoid-break">
    <h2>Direitos autorais</h2>
    @foreach ($licenses as $l)
        <div class="box license">
            <div class="license-head">
                @foreach ($l['elements'] as $el)<span class="cc-icon">{{ $el === 'zero' ? '0' : strtoupper($el) }}</span>@endforeach
                <strong>{{ $l['name'] }}</strong>
                <span class="muted small">· {{ $l['title'] }}</span>
            </div>
            <div class="small">{{ $l['statement'] }}</div>
            <div class="small"><a href="{{ $l['url'] }}">{{ $l['url'] }}</a></div>
        </div>
    @endforeach
    </div>
@endif
