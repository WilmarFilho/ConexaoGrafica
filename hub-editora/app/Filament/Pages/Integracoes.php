<?php

namespace App\Filament\Pages;

use App\Integrations\Bling\BlingClient;
use App\Integrations\MelhorEnvio\MelhorEnvioClient;
use App\Integrations\PagarMe\PagarMeClient;
use App\Integrations\WooCommerce\WooCommerceClient;
use App\Models\Channel;
use App\Models\SyncLog;
use App\Support\HubSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Integrações: situação de cada canal, teste de conexão e troca de chaves.
 * Segredos nunca são exibidos: campos vazios mantêm o valor atual.
 */
class Integracoes extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static ?string $navigationLabel = 'Integrações';

    protected static ?string $title = 'Integrações';

    protected static ?string $slug = 'integracoes';

    protected static ?int $navigationSort = 70;

    protected string $view = 'filament.pages.integracoes';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $fill = [];
        foreach (HubSettings::definitions() as $key => $def) {
            $fill[$key] = $def['secret'] ? null : HubSettings::effective($key);
        }
        $fill['melhor_envio.sandbox'] = (bool) config('hub.melhor_envio.sandbox');
        $this->form->fill($fill);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Grid::make(2)->schema([
                    $this->section('WooCommerce (loja Pubcon)', Channel::WOOCOMMERCE, 'woo', [
                        $this->text('woocommerce.url'),
                        $this->secret('woocommerce.key'),
                        $this->secret('woocommerce.secret'),
                        $this->secret('woocommerce.webhook_secret'),
                    ], 'Chaves em WooCommerce → Configurações → Avançado → REST API. Webhooks apontam para '.url('/webhooks/woocommerce').'.'),

                    $this->section('Pagar.me (landing pages)', Channel::PAGARME, 'pagarme', [
                        $this->secret('pagarme.secret_key'),
                        $this->secret('pagarme.webhook_secret'),
                    ], 'Dash 2.0 → Configurações → Chaves de API. Webhook com Basic auth apontando para '.url('/webhooks/pagarme').'. Pedidos feitos pela loja são ignorados aqui: o dono é o WooCommerce.'),

                    $this->section('Melhor Envio', Channel::MELHOR_ENVIO, 'me', [
                        $this->secret('melhor_envio.token'),
                        Toggle::make('melhor_envio.sandbox')->label('Ambiente de testes (sandbox)')->inline(false),
                        Grid::make(2)->schema([
                            $this->text('melhor_envio.from.name'),
                            $this->text('melhor_envio.from.company_document'),
                            $this->text('melhor_envio.from.document'),
                            $this->text('melhor_envio.from.phone'),
                            $this->text('melhor_envio.from.email'),
                            $this->text('melhor_envio.from.postal_code'),
                            $this->text('melhor_envio.from.address'),
                            $this->text('melhor_envio.from.number'),
                            $this->text('melhor_envio.from.complement'),
                            $this->text('melhor_envio.from.district'),
                            $this->text('melhor_envio.from.city'),
                            $this->text('melhor_envio.from.state'),
                        ]),
                    ], 'Token em Integrações → Tokens. O remetente sai impresso na etiqueta e o CEP de origem define a cotação.'),

                    $this->section('Amazon via Bling', Channel::BLING, 'bling', [
                        $this->text('bling.client_id'),
                        $this->secret('bling.client_secret'),
                        Actions::make([
                            Action::make('conectar_bling')
                                ->label(BlingClient::isConnected() ? 'Reconectar ao Bling' : 'Conectar ao Bling')
                                ->icon(Heroicon::OutlinedLink)
                                ->color(BlingClient::isConnected() ? 'gray' : 'primary')
                                ->url(route('bling.connect'))
                                ->disabled(! BlingClient::isConfigured()),
                        ]),
                    ], 'Aplicativo privado em developer.bling.com.br com redirecionamento '.url('/bling/callback').'. Salve as chaves e clique em Conectar: o Bling pede autorização uma vez e o hub renova o acesso sozinho. Só entram pedidos com número da Amazon.'),
                ]),
            ]);
    }

    private function section(string $title, string $slug, string $test, array $fields, string $help): Section
    {
        return Section::make($title)
            ->description(new HtmlString($this->statusLine($slug)))
            ->afterHeader([
                Action::make('testar_'.$test)
                    ->label('Testar conexão')
                    ->icon(Heroicon::OutlinedSignal)
                    ->color('gray')
                    ->action(fn () => $this->test($slug)),
            ])
            ->schema([
                ...$fields,
                Text::make($help)->color('gray'),
            ]);
    }

    private function text(string $key): TextInput
    {
        $def = HubSettings::definitions()[$key];

        return TextInput::make($key)
            ->label($def['label'])
            ->helperText($def['help'] ?? null)
            ->suffixIcon(fn () => HubSettings::source($key) === 'banco' ? Heroicon::OutlinedCircleStack : null)
            ->suffixIconColor('gray');
    }

    private function secret(string $key): TextInput
    {
        $def = HubSettings::definitions()[$key];
        $current = HubSettings::effective($key);

        return TextInput::make($key)
            ->label($def['label'])
            ->password()
            ->revealable()
            ->autocomplete('new-password')
            ->placeholder(filled($current)
                ? 'Definido ('.HubSettings::mask($current).' · '.HubSettings::source($key).'). Deixe vazio para manter.'
                : 'Não definido')
            ->helperText($def['help'] ?? null);
    }

    private function statusLine(string $slug): string
    {
        $channel = Channel::bySlug($slug);
        $configured = match ($slug) {
            Channel::WOOCOMMERCE => filled(config('hub.woocommerce.url')) && filled(config('hub.woocommerce.key')) && filled(config('hub.woocommerce.secret')),
            Channel::PAGARME => filled(config('hub.pagarme.secret_key')),
            Channel::MELHOR_ENVIO => MelhorEnvioClient::isConfigured(),
            Channel::BLING => BlingClient::isConfigured(),
            default => false,
        };

        $dot = $configured ? '#15803D' : '#B45309';
        $label = $configured ? 'Configurado' : 'Sem credenciais';

        if ($slug === Channel::BLING && $configured) {
            $connected = BlingClient::isConnected();
            $dot = $connected ? '#15803D' : '#B45309';
            $label = $connected
                ? 'Conectado'.(BlingClient::connectedAt() ? ' em '.BlingClient::connectedAt()->format('d/m/Y H:i') : '')
                : 'Chaves salvas · falta autorizar (Conectar ao Bling)';
        }
        $sync = $channel?->last_sync_at
            ? ' · última sincronização '.$channel->last_sync_at->diffForHumans().($channel->last_sync_status === 'error' ? ' <span style="color:#B91C1C">com erro</span>' : '')
            : '';

        return '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:'.$dot.';margin-right:6px"></span>'.$label.$sync;
    }

    /** Ping real na API do canal com as credenciais efetivas. */
    public function test(string $slug): void
    {
        try {
            $detail = match ($slug) {
                Channel::WOOCOMMERCE => (function () {
                    $n = 0;
                    foreach (WooCommerceClient::fromConfig()->ordersModifiedSince(now()->subDays(30), 1) as $o) {
                        $n++;
                        break;
                    }

                    return 'Loja respondeu; leitura de pedidos OK.';
                })(),
                Channel::PAGARME => (function () {
                    foreach (PagarMeClient::fromConfig()->ordersCreatedSince(now()->subDays(30), 1) as $o) {
                        break;
                    }

                    return 'Pagar.me respondeu; leitura de pedidos OK.';
                })(),
                Channel::MELHOR_ENVIO => (function () {
                    $b = MelhorEnvioClient::make()->balance();

                    return 'Melhor Envio respondeu. Saldo: R$ '.number_format((float) ($b['balance'] ?? 0), 2, ',', '.').(config('hub.melhor_envio.sandbox') ? ' (sandbox)' : '');
                })(),
                Channel::BLING => (function () {
                    if (! BlingClient::isConnected()) {
                        throw new \RuntimeException('Chaves salvas, mas a conta ainda não foi autorizada. Clique em "Conectar ao Bling".');
                    }
                    $r = BlingClient::fromConfig()->get('/pedidos/vendas', ['limite' => 1]);

                    return 'Bling respondeu; leitura de pedidos de venda OK'.(isset($r['data'][0]['numero']) ? ' (último nº '.$r['data'][0]['numero'].')' : '').'.';
                })(),
                default => throw new \RuntimeException('Teste ainda não disponível para este canal.'),
            };

            SyncLog::record(Channel::bySlug($slug), SyncLog::MANUAL, 'settings.tested', null, "{$slug}: {$detail}");
            Notification::make()->title('Conexão OK')->body($detail)->success()->send();
        } catch (Throwable $e) {
            SyncLog::record(Channel::bySlug($slug), SyncLog::MANUAL, 'settings.tested', null, "{$slug}: ".$e->getMessage(), [], 'error');
            Notification::make()->title('Falha na conexão')->body(mb_substr($e->getMessage(), 0, 300))->danger()->persistent()->send();
        }
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $changed = [];

        foreach (HubSettings::definitions() as $key => $def) {
            // As chaves têm ponto (ex.: bling.client_id) e o formulário devolve o estado aninhado.
            $value = data_get($state, $key);

            if ($key === 'melhor_envio.sandbox') {
                $value = $value ? '1' : '0';
            } elseif (is_string($value)) {
                $value = trim($value);
            }

            // Segredo vazio = manter o atual. Texto vazio = limpar o que estava no banco.
            if ($def['secret'] && ($value === null || $value === '')) {
                continue;
            }

            $before = HubSettings::stored()[$key] ?? null;
            if ((string) $before === (string) $value) {
                continue;
            }

            HubSettings::set($key, $value === '' ? null : $value, auth()->id());
            $changed[] = $def['label'];
        }

        if ($changed) {
            SyncLog::record(null, SyncLog::MANUAL, 'settings.changed', null, 'Alterado: '.implode(', ', $changed));
            Notification::make()->title('Integrações salvas')->body(implode(', ', $changed))->success()->send();
        } else {
            Notification::make()->title('Nada mudou')->info()->send();
        }

        // Limpa os campos de segredo e recarrega os textos.
        $this->mount();
    }
}
