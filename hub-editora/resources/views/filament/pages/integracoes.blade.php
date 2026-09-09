<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:16px;">
            <x-filament::button type="submit" icon="heroicon-o-check">
                Salvar integrações
            </x-filament::button>
        </div>
    </form>

    <p style="color:#6A6F7A;font-size:12px;margin-top:8px;">
        Segredos ficam criptografados no banco do hub e nunca são exibidos de volta. O que estiver aqui vence o arquivo <code>.env</code> do servidor.
    </p>
</x-filament-panels::page>
