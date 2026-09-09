<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;

/**
 * Detalhe do pedido: coluna larga com cliente, entrega e itens; coluna
 * estreita com pagamento e expedição (espelha o artboard "Pedido").
 */
class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Grid::make(1)
                    ->columnSpan(2)
                    ->schema([
                        Section::make('Cliente e entrega')
                            ->columns(2)
                            ->schema([
                                TextEntry::make('customer.name')->label('Nome')->weight(FontWeight::SemiBold),
                                TextEntry::make('customer.document')->label('CPF/CNPJ')->placeholder('—')->extraAttributes(['class' => 'hub-mono']),
                                TextEntry::make('customer.email')->label('E-mail')->placeholder('—')->copyable(),
                                TextEntry::make('customer.phone')->label('Telefone')->placeholder('—')->extraAttributes(['class' => 'hub-mono']),
                                TextEntry::make('ship_address')
                                    ->label('Endereço de entrega')
                                    ->columnSpanFull()
                                    ->state(fn (Order $record) => self::addressLines($record))
                                    ->placeholder('Pedido digital: nada a expedir')
                                    ->html(),
                            ]),

                        Section::make('Itens')
                            ->schema([
                                RepeatableEntry::make('items')
                                    ->hiddenLabel()
                                    ->columns(6)
                                    ->schema([
                                        TextEntry::make('name')->label('Produto')->columnSpan(3)->weight(FontWeight::Medium),
                                        TextEntry::make('external_sku')->label('SKU')->placeholder('—')->extraAttributes(['class' => 'hub-mono']),
                                        TextEntry::make('quantity')->label('Qtd')->alignEnd()->extraAttributes(['class' => 'hub-mono']),
                                        TextEntry::make('total_cents')->label('Total')->money('BRL', divideBy: 100)->alignEnd()->extraAttributes(['class' => 'hub-mono']),
                                    ]),
                            ]),
                    ]),

                Grid::make(1)
                    ->columnSpan(1)
                    ->schema([
                        Section::make('Pagamento')
                            ->schema([
                                TextEntry::make('payment_status')
                                    ->label('Situação')
                                    ->badge()
                                    ->formatStateUsing(fn (?string $state) => match ($state) {
                                        'paid' => 'Pago', 'pending' => 'Pendente', 'refunded' => 'Estornado', 'failed' => 'Falhou', default => $state ?? '—',
                                    })
                                    ->color(fn (?string $state) => match ($state) {
                                        'paid' => 'success', 'pending' => 'warning', 'refunded', 'failed' => 'danger', default => 'gray',
                                    }),
                                TextEntry::make('payment_method')->label('Forma')->formatStateUsing(fn (?string $state) => OrdersTable::paymentMethodLabel($state) ?? '—'),
                                TextEntry::make('paid_at')->label('Pago em')->dateTime('d/m/Y H:i')->placeholder('—'),
                                TextEntry::make('subtotal_cents')->label('Itens')->money('BRL', divideBy: 100)->extraAttributes(['class' => 'hub-mono']),
                                TextEntry::make('shipping_cents')->label('Frete cobrado')->money('BRL', divideBy: 100)->extraAttributes(['class' => 'hub-mono']),
                                TextEntry::make('discount_cents')->label('Desconto')->money('BRL', divideBy: 100)->extraAttributes(['class' => 'hub-mono']),
                                TextEntry::make('total_cents')->label('Total')->money('BRL', divideBy: 100)->weight(FontWeight::Bold)->extraAttributes(['class' => 'hub-mono']),
                            ]),

                        Section::make('Expedição')
                            ->schema([
                                TextEntry::make('status')->label('Etapa')->badge(),
                                TextEntry::make('channel_status')->label('Status no canal')->placeholder('—')->extraAttributes(['class' => 'hub-mono']),
                                TextEntry::make('shipment.carrier')->label('Transportadora')->placeholder('Sem etiqueta ainda'),
                                TextEntry::make('shipment.service')->label('Serviço')->placeholder('—'),
                                TextEntry::make('shipment.tracking_code')->label('Rastreio')->placeholder('—')->copyable()->extraAttributes(['class' => 'hub-mono']),
                                TextEntry::make('shipment.cost_cents')->label('Custo do frete')->money('BRL', divideBy: 100)->placeholder('—')->extraAttributes(['class' => 'hub-mono']),
                            ]),

                        Section::make('Identificadores')
                            ->collapsed()
                            ->schema([
                                TextEntry::make('source_label')->label('Loja / landing')->placeholder('—'),
                                TextEntry::make('external_id')->label('ID no canal')->copyable()->extraAttributes(['class' => 'hub-mono']),
                                TextEntry::make('external_number')->label('Número')->placeholder('—')->extraAttributes(['class' => 'hub-mono']),
                                TextEntry::make('updated_at')->label('Última sincronização')->since(),
                            ]),
                    ]),
            ]);
    }

    private static function addressLines(Order $o): ?string
    {
        if (! $o->requires_shipping || ! $o->ship_street) {
            return null;
        }

        $line1 = trim($o->ship_street.', '.($o->ship_number ?: 's/n').($o->ship_complement ? ' · '.$o->ship_complement : ''));
        $line2 = trim(($o->ship_district ? $o->ship_district.' · ' : '').$o->ship_city.' / '.$o->ship_state);
        $zip = $o->ship_zip ? '<span class="hub-mono">CEP '.e($o->ship_zip).'</span>' : '';
        $ref = $o->ship_reference ? '<span style="color:#6A6F7A">Ref.: '.e($o->ship_reference).'</span>' : '';

        return implode('<br>', array_filter([e($line1), e($line2), $zip, $ref]));
    }
}
