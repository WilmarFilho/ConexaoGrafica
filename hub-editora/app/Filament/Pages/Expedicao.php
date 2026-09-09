<?php

namespace App\Filament\Pages;

use App\Enums\OrderStatus;
use App\Filament\Actions\ChangeOrderStatusAction;
use App\Filament\Resources\Orders\OrderResource;
use App\Integrations\MelhorEnvio\MelhorEnvioClient;
use App\Integrations\MelhorEnvio\ShipmentService;
use App\Jobs\GenerateLabel;
use App\Models\Order;
use App\Models\Shipment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * Central de Expedição: fila do que está pago e ainda não saiu, com
 * geração de etiquetas em lote e pendências visíveis na própria linha.
 */
class Expedicao extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $navigationLabel = 'Expedição';

    protected static ?string $title = 'Central de Expedição';

    protected static ?string $slug = 'expedicao';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.pages.expedicao';

    public static function getNavigationBadge(): ?string
    {
        $n = Order::query()->awaitingShipment()->count();

        return $n ? (string) $n : null;
    }

    /** Números do topo da página. */
    public function getStats(): array
    {
        $awaiting = Order::query()->awaitingShipment()->count();
        $labels = Order::query()->where('status', OrderStatus::LabelGenerated)->count();
        $problems = Order::query()->where('status', OrderStatus::Problem)->count();
        $shippedToday = Shipment::query()->whereDate('shipped_at', today())->count();

        return [
            ['label' => 'A separar', 'value' => $awaiting, 'tone' => 'warning'],
            ['label' => 'Etiqueta gerada', 'value' => $labels, 'tone' => 'info'],
            ['label' => 'Pendências', 'value' => $problems, 'tone' => 'danger'],
            ['label' => 'Postados hoje', 'value' => $shippedToday, 'tone' => 'success'],
        ];
    }

    public function table(Table $table): Table
    {
        $service = app(ShipmentService::class);

        return $table
            ->query(fn () => Order::query()
                ->with(['channel', 'customer', 'items', 'shipment'])
                ->where('requires_shipping', true)
                ->whereIn('status', [...OrderStatus::awaitingShipment(), OrderStatus::LabelGenerated, OrderStatus::ReadyToShip, OrderStatus::Problem]))
            ->defaultSort('placed_at')
            ->poll('30s')
            ->recordUrl(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('external_number')
                    ->label('Pedido')
                    ->weight(FontWeight::SemiBold)
                    ->extraAttributes(['class' => 'hub-mono'])
                    ->formatStateUsing(fn (?string $state) => $state ? '#'.$state : '—')
                    ->description(fn (Order $r) => trim(($r->channel?->name ?? '').($r->source_label ? ' · '.$r->source_label : '')))
                    ->searchable(['external_number', 'external_id']),

                TextColumn::make('customer.name')
                    ->label('Destinatário')
                    ->weight(FontWeight::Medium)
                    ->description(fn (Order $r) => $r->ship_city_state)
                    ->searchable(['customers.name'])
                    ->wrap(),

                TextColumn::make('ship_zip')
                    ->label('CEP')
                    ->extraAttributes(['class' => 'hub-mono'])
                    ->color(fn (?string $state) => strlen(preg_replace('/\D/', '', (string) $state)) === 8 ? null : 'danger')
                    ->icon(fn (?string $state) => strlen(preg_replace('/\D/', '', (string) $state)) === 8 ? null : Heroicon::OutlinedExclamationTriangle),

                TextColumn::make('items_summary')
                    ->label('Itens')
                    ->limit(50)
                    ->tooltip(fn (Order $r) => $r->items_summary)
                    ->color('gray')
                    ->wrap(),

                TextColumn::make('status')
                    ->label('Etapa')
                    ->badge(),

                TextColumn::make('pendencia')
                    ->label('Pendência')
                    ->state(fn (Order $r) => $r->status === OrderStatus::Problem
                        ? ($r->shipment?->problem ?: 'Ver detalhes')
                        : (implode(' · ', app(ShipmentService::class)->issues($r)) ?: null))
                    ->placeholder('—')
                    ->color('danger')
                    ->limit(70)
                    ->tooltip(fn (Order $r) => $r->shipment?->problem)
                    ->wrap(),

                TextColumn::make('shipment.tracking_code')
                    ->label('Rastreio')
                    ->extraAttributes(['class' => 'hub-mono'])
                    ->placeholder('—')
                    ->copyable(),

                TextColumn::make('placed_at')
                    ->label('Aguardando há')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('channel_id')->label('Origem')->relationship('channel', 'name')->multiple()->preload(),
                SelectFilter::make('status')->label('Etapa')->options(OrderStatus::class)->multiple(),
            ])
            ->recordActions([
                Action::make('cotar')
                    ->label('Cotar e gerar')
                    ->icon(Heroicon::OutlinedCalculator)
                    ->visible(fn (Order $r) => in_array($r->status, [...OrderStatus::awaitingShipment(), OrderStatus::Problem], true))
                    ->modalHeading(fn (Order $r) => 'Frete do pedido #'.($r->external_number ?: $r->external_id))
                    ->modalSubmitActionLabel('Comprar etiqueta')
                    ->modalWidth('md')
                    ->schema(function (Order $record) use ($service) {
                        if ($issues = $service->issues($record)) {
                            return [
                                Radio::make('service')
                                    ->label('Não dá para cotar ainda')
                                    ->helperText(implode(' · ', $issues))
                                    ->options([])
                                    ->disabled(),
                            ];
                        }

                        $error = null;
                        try {
                            $options = collect($service->quote($record))->mapWithKeys(fn ($o) => [
                                $o['id'] => sprintf('%s %s — R$ %s · %d dias', $o['company'], $o['name'], number_format($o['price_cents'] / 100, 2, ',', '.'), $o['days']),
                            ])->all();
                        } catch (Throwable $e) {
                            $options = [];
                            $error = $e->getMessage();
                        }

                        return [
                            Radio::make('service')
                                ->label('Serviço')
                                ->options($options)
                                ->default(array_key_first($options))
                                ->required()
                                ->helperText($options
                                    ? 'Mais barato primeiro. O pedido paga o serviço escolhido.'
                                    : 'Nenhum serviço disponível: '.($error ?: 'verifique CEP e configuração')),
                        ];
                    })
                    ->action(function (Order $record, array $data) use ($service) {
                        $shipment = $service->buy($record, isset($data['service']) ? (int) $data['service'] : null);

                        $shipment->status === Shipment::LABEL_GENERATED
                            ? Notification::make()->title('Etiqueta gerada')->body($shipment->carrier.' '.$shipment->service.' · '.$shipment->tracking_code)->success()->send()
                            : Notification::make()->title('Não foi possível gerar')->body($shipment->problem)->danger()->persistent()->send();
                    }),

                ChangeOrderStatusAction::make()->label('Etapa'),

                Action::make('etiqueta')
                    ->label('Etiqueta')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->url(fn (Order $r) => $r->shipment?->label_url, shouldOpenInNewTab: true)
                    ->visible(fn (Order $r) => filled($r->shipment?->label_url)),
            ])
            ->toolbarActions([
                BulkAction::make('gerar_lote')
                    ->label('Gerar etiquetas em lote')
                    ->icon(Heroicon::OutlinedQrCode)
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Gerar etiquetas para os pedidos selecionados')
                    ->modalDescription('Cada pedido é cotado e comprado no serviço mais barato disponível. Pedidos com pendência ficam marcados como problema, sem travar os demais.')
                    ->modalSubmitActionLabel('Gerar')
                    ->action(function (Collection $records) {
                        if (! MelhorEnvioClient::isConfigured()) {
                            Notification::make()->title('Melhor Envio não configurado')->body('Defina ME_TOKEN e ME_FROM_ZIP no .env.')->danger()->send();

                            return;
                        }

                        $eligible = $records->filter(fn (Order $o) => in_array($o->status, [...OrderStatus::awaitingShipment(), OrderStatus::Problem], true));
                        $eligible->each(fn (Order $o) => GenerateLabel::dispatch($o->id));

                        Notification::make()
                            ->title($eligible->count().' pedido(s) enviados para geração')
                            ->body('Acompanhe a coluna Etapa; a fila processa em segundo plano.')
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),

                BulkAction::make('marcar_separado')
                    ->label('Marcar como conferido')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->action(function (Collection $records) {
                        $records->each(fn (Order $o) => in_array($o->status, [OrderStatus::Paid, OrderStatus::Picking], true) && $o->update(['status' => OrderStatus::Checked]));
                        Notification::make()->title('Pedidos marcados como conferidos')->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateHeading('Fila vazia')
            ->emptyStateDescription('Nenhum pedido pago aguardando expedição.');
    }
}
