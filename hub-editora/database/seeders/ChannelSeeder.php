<?php

namespace Database\Seeders;

use App\Models\Channel;
use Illuminate\Database\Seeder;

class ChannelSeeder extends Seeder
{
    public function run(): void
    {
        $channels = [
            [Channel::WOOCOMMERCE, 'WooCommerce (Pubcon)'],
            [Channel::PAGARME, 'Pagar.me (landing pages)'],
            [Channel::AMAZON, 'Amazon Seller'],
            [Channel::BLING, 'Bling (fiscal / ponte Amazon)'],
            [Channel::MELHOR_ENVIO, 'Melhor Envio'],
        ];

        foreach ($channels as [$slug, $name]) {
            Channel::updateOrCreate(['slug' => $slug], ['name' => $name]);
        }
    }
}
