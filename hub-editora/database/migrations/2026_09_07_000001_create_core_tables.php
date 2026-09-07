<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Núcleo do Hub Editora: canais, clientes, produtos, pedidos e expedição.
 *
 * Decisões que valem para todas as tabelas:
 * - Todo registro que nasce fora do hub guarda (canal, id_externo). É a
 *   chave de idempotência: reprocessar um webhook nunca duplica.
 * - Dinheiro em centavos inteiros (amount_cents), nunca float.
 * - Status internos padronizados; a tradução para cada canal fica no código.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Canais de venda / integrações (WooCommerce, Pagar.me, Amazon, Bling, Melhor Envio).
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();     // woocommerce | pagarme | amazon | bling | melhor_envio
            $table->string('name', 80);
            $table->boolean('active')->default(true);
            $table->json('settings')->nullable();      // urls, ids de conta; NUNCA segredos (ficam no .env)
            $table->timestamp('last_sync_at')->nullable();
            $table->string('last_sync_status', 20)->nullable(); // ok | error
            $table->text('last_sync_message')->nullable();
            $table->timestamps();
        });

        // Cadastro-mestre de produtos: um livro, vários SKUs por canal.
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 60)->unique();       // SKU interno (ex.: FUND-OF-001)
            $table->string('name');
            $table->string('isbn', 20)->nullable()->index();
            $table->boolean('physical')->default(true); // false = e-book (não entra na expedição)
            $table->unsignedInteger('weight_grams')->nullable();
            $table->unsignedSmallInteger('width_cm')->nullable();
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->unsignedSmallInteger('depth_cm')->nullable();
            $table->integer('stock_physical')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Mapeamento produto ↔ identificador em cada canal.
        Schema::create('product_channel_refs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 120);        // id do produto no canal (post ID, ASIN, prod_xxx)
            $table->string('external_sku', 120)->nullable();
            $table->timestamps();
            $table->unique(['channel_id', 'external_id']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->string('phone', 30)->nullable();
            $table->string('document', 20)->nullable()->index(); // CPF/CNPJ só dígitos
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained();
            $table->string('external_id', 120);        // #10487 | or_xxx | 701-3391-0087
            $table->string('external_number', 60)->nullable(); // número amigável mostrado ao cliente
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            // Status interno padronizado.
            $table->string('status', 30)->index();     // ver App\Enums\OrderStatus
            $table->string('payment_status', 30)->index(); // pending | paid | failed | refunded
            $table->string('payment_method', 40)->nullable(); // pix | credit_card | boleto ...
            $table->string('channel_status', 60)->nullable();  // status cru como veio do canal

            $table->unsignedBigInteger('subtotal_cents')->default(0);
            $table->unsignedBigInteger('shipping_cents')->default(0);
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->char('currency', 3)->default('BRL');

            // Endereço de entrega desnormalizado: é o que estava valendo no pedido.
            $table->string('ship_name')->nullable();
            $table->string('ship_street')->nullable();
            $table->string('ship_number', 20)->nullable();
            $table->string('ship_complement')->nullable();
            $table->string('ship_district')->nullable();
            $table->string('ship_city')->nullable();
            $table->char('ship_state', 2)->nullable();
            $table->string('ship_zip', 9)->nullable();
            $table->string('ship_reference')->nullable();

            $table->boolean('requires_shipping')->default(true); // false = só e-books
            $table->timestamp('placed_at')->nullable()->index();  // quando o cliente comprou
            $table->timestamp('paid_at')->nullable();
            $table->json('raw')->nullable();                      // payload original, para auditoria
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['channel_id', 'external_id']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');                    // nome como veio do canal
            $table->string('external_sku', 120)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_cents')->default(0);
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->timestamps();
        });

        // Um envio por pedido (volumes múltiplos ficam para depois; um livro raramente precisa).
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->index();     // quoted | purchased | label_generated | shipped | delivered | problem | cancelled
            $table->string('carrier', 60)->nullable(); // Correios, Jadlog...
            $table->string('service', 60)->nullable(); // PAC, SEDEX...
            $table->unsignedBigInteger('cost_cents')->nullable();
            $table->string('melhor_envio_id', 80)->nullable()->index(); // id do envio no ME
            $table->string('tracking_code', 40)->nullable()->index();
            $table->string('label_url')->nullable();
            $table->unsignedInteger('weight_grams')->nullable();
            $table->unsignedSmallInteger('width_cm')->nullable();
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->unsignedSmallInteger('depth_cm')->nullable();
            $table->timestamp('label_generated_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->boolean('channel_notified')->default(false); // rastreio já foi devolvido ao canal?
            $table->text('problem')->nullable();       // motivo, quando status = problem
            $table->json('raw')->nullable();
            $table->timestamps();
        });

        // Trilha de auditoria de cada integração: o que foi chamado, quando, resultado.
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('subject');         // pedido, envio, produto...
            $table->string('direction', 10);           // in (webhook/import) | out (chamada nossa)
            $table->string('action', 60);              // order.imported | label.created | shipment.confirmed ...
            $table->string('level', 10)->default('info'); // info | warning | error
            $table->text('message')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('product_channel_refs');
        Schema::dropIfExists('products');
        Schema::dropIfExists('channels');
    }
};
