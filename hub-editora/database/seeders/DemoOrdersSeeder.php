<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Channel;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Database\Seeder;

/**
 * Pedidos fictícios para enxergar as telas em desenvolvimento.
 * Só roda quando chamado explicitamente: `php artisan db:seed --class=DemoOrdersSeeder`.
 * Nunca use em produção: os pedidos reais entram pelo sync dos canais.
 */
class DemoOrdersSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('DemoOrdersSeeder ignorado em produção.');

            return;
        }

        $woo = Channel::bySlug(Channel::WOOCOMMERCE);
        $pagarme = Channel::bySlug(Channel::PAGARME);
        $amazon = Channel::bySlug(Channel::AMAZON);

        $rows = [
            ['ch' => $woo, 'n' => '48213', 'name' => 'Mariana Souza Lima', 'doc' => '123.456.789-09', 'city' => 'Campinas', 'uf' => 'SP', 'zip' => '13010-100', 'st' => OrderStatus::Paid, 'pay' => 'paid', 'pm' => 'woo-pagarme-payments-pix', 'items' => [['Manual de Sobrevivência do Escritor', 1, 5990], ['Caderno do Leitor', 2, 2450]], 'ago' => 2],
            ['ch' => $pagarme, 'n' => 'or_3Km9', 'name' => 'João Pedro Carvalho', 'doc' => '987.654.321-00', 'city' => 'Anicuns', 'uf' => 'GO', 'zip' => '76170-000', 'st' => OrderStatus::Paid, 'pay' => 'paid', 'pm' => 'credit_card', 'items' => [['Box Trilogia das Cinzas', 1, 18900]], 'ago' => 5],
            ['ch' => $amazon, 'n' => '701-3392-4410', 'name' => 'Helena Barbosa Freitas', 'doc' => null, 'city' => 'Fortaleza', 'uf' => 'CE', 'zip' => '60150-160', 'st' => OrderStatus::Shipped, 'pay' => 'paid', 'pm' => 'amazon', 'items' => [['Poemas para Dias Nublados', 1, 4290]], 'ago' => 30, 'track' => 'AA123456789BR'],
            ['ch' => $woo, 'n' => '48210', 'name' => 'Sérgio Tavares Lopes', 'doc' => '321.654.987-11', 'city' => 'Belo Horizonte', 'uf' => 'MG', 'zip' => '30130-010', 'st' => OrderStatus::Problem, 'pay' => 'paid', 'pm' => 'woo-pagarme-payments-billet', 'items' => [['Atlas Ilustrado do Cerrado', 1, 12900]], 'ago' => 9, 'zip_bad' => '3013001'],
            ['ch' => $woo, 'n' => '48209', 'name' => 'Renata Albuquerque', 'doc' => '456.789.123-22', 'city' => null, 'uf' => null, 'zip' => null, 'st' => OrderStatus::Fulfilled, 'pay' => 'paid', 'pm' => 'woo-pagarme-payments-credit_card', 'items' => [['E-book: Guia de Escrita Criativa', 1, 2990]], 'ago' => 12, 'digital' => true],
            ['ch' => $pagarme, 'n' => 'or_7Qw2', 'name' => 'Carlos Eduardo Ramos', 'doc' => '654.321.987-33', 'city' => 'Porto Alegre', 'uf' => 'RS', 'zip' => '90010-150', 'st' => OrderStatus::AwaitingPayment, 'pay' => 'pending', 'pm' => 'boleto', 'items' => [['Box Trilogia das Cinzas', 1, 18900], ['Marcador Magnético', 3, 990]], 'ago' => 1],
            ['ch' => $woo, 'n' => '48205', 'name' => 'Ana Clara Menezes', 'doc' => '789.123.456-44', 'city' => 'Recife', 'uf' => 'PE', 'zip' => '50030-230', 'st' => OrderStatus::LabelGenerated, 'pay' => 'paid', 'pm' => 'woo-pagarme-payments-pix', 'items' => [['Manual de Sobrevivência do Escritor', 1, 5990]], 'ago' => 20, 'track' => 'ME98765432BR'],
            ['ch' => $amazon, 'n' => '702-1187-9921', 'name' => 'Lucas Andrade', 'doc' => null, 'city' => 'Curitiba', 'uf' => 'PR', 'zip' => '80010-000', 'st' => OrderStatus::Picking, 'pay' => 'paid', 'pm' => 'amazon', 'items' => [['Poemas para Dias Nublados', 2, 4290]], 'ago' => 7],
        ];

        foreach ($rows as $r) {
            $customer = Customer::findOrCreateFrom([
                'name' => $r['name'],
                'email' => str($r['name'])->slug('.').'@exemplo.com',
                'phone' => '(62) 9'.random_int(8000, 9999).'-'.random_int(1000, 9999),
                'document' => $r['doc'],
            ]);

            $subtotal = collect($r['items'])->sum(fn ($i) => $i[1] * $i[2]);
            $shipping = ($r['digital'] ?? false) ? 0 : 2190;
            $digital = $r['digital'] ?? false;

            $order = Order::updateOrCreate(
                ['channel_id' => $r['ch']->id, 'external_id' => 'demo-'.$r['n']],
                [
                    'customer_id' => $customer->id,
                    'external_number' => $r['n'],
                    'status' => $r['st'],
                    'payment_status' => $r['pay'],
                    'payment_method' => $r['pm'],
                    'channel_status' => 'processing',
                    'subtotal_cents' => $subtotal,
                    'shipping_cents' => $shipping,
                    'discount_cents' => 0,
                    'total_cents' => $subtotal + $shipping,
                    'currency' => 'BRL',
                    'requires_shipping' => ! $digital,
                    'ship_name' => $digital ? null : $r['name'],
                    'ship_street' => $digital ? null : 'Rua das Palmeiras',
                    'ship_number' => $digital ? null : (string) random_int(10, 999),
                    'ship_district' => $digital ? null : 'Centro',
                    'ship_city' => $r['city'],
                    'ship_state' => $r['uf'],
                    'ship_zip' => $r['zip_bad'] ?? $r['zip'],
                    'placed_at' => now()->subHours($r['ago']),
                    'paid_at' => $r['pay'] === 'paid' ? now()->subHours($r['ago'])->addMinutes(3) : null,
                    'raw' => ['demo' => true],
                ],
            );

            $order->items()->delete();
            foreach ($r['items'] as [$name, $qty, $unit]) {
                $order->items()->create([
                    'name' => $name,
                    'external_sku' => 'DEMO-'.strtoupper(substr(md5($name), 0, 6)),
                    'quantity' => $qty,
                    'unit_cents' => $unit,
                    'total_cents' => $qty * $unit,
                ]);
            }

            if (! empty($r['track'])) {
                Shipment::updateOrCreate(
                    ['order_id' => $order->id],
                    [
                        'status' => $r['st'] === OrderStatus::Shipped ? 'shipped' : 'label_generated',
                        'label_generated_at' => now()->subHours($r['ago'] - 1),
                        'shipped_at' => $r['st'] === OrderStatus::Shipped ? now()->subHours($r['ago'] - 2) : null,
                        'carrier' => 'Correios',
                        'service' => 'PAC',
                        'cost_cents' => 1870,
                        'tracking_code' => $r['track'],
                        'channel_notified' => $r['st'] === OrderStatus::Shipped,
                    ],
                );
            }
        }
    }
}
