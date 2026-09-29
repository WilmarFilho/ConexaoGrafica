<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Um produto por livro, com os formatos em que ele é vendido (físico, e-book,
 * físico + e-book). O vínculo com o canal e o item do pedido guardam qual
 * formato é aquele id, para a expedição saber o que tem peso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('formats')->nullable()->after('physical');
        });

        Schema::table('product_channel_refs', function (Blueprint $table) {
            $table->string('format', 20)->nullable()->after('external_sku');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('format', 20)->nullable()->after('external_sku');
        });

        DB::table('products')->where('physical', true)->update(['formats' => json_encode(['fisico'])]);
        DB::table('products')->where('physical', false)->update(['formats' => json_encode(['ebook'])]);

        // o canal agora é a loja da Conexão Editora
        DB::table('channels')->where('slug', 'woocommerce')->update(['name' => 'Loja Conexão Editora (WooCommerce)']);
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('format'));
        Schema::table('product_channel_refs', fn (Blueprint $table) => $table->dropColumn('format'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('formats'));
    }
};
