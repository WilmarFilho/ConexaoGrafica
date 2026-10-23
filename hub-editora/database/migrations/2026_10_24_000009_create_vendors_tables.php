<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multivendor: autores/parceiros que vendem livros pela editora. O vendedor
 * é dono de produtos do catálogo; cada venda desses produtos gera comissão
 * (a parte do vendedor) e as comissões disponíveis são pagas em repasses.
 * Substitui o MVX da loja: o hub enxerga os três canais.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');                              // nome público (autor, selo)
            $table->string('legal_name')->nullable();            // razão social / nome completo para o repasse
            $table->string('document', 20)->nullable();          // CPF ou CNPJ, só dígitos
            $table->string('email', 190)->nullable();
            $table->string('phone', 30)->nullable();
            $table->decimal('commission_pct', 5, 2)->default(65); // parte do vendedor sobre o valor do item
            $table->string('payout_method', 20)->nullable();     // pix | bank
            $table->text('payout_details')->nullable();          // chave PIX ou dados bancários (cifrado no model)
            $table->date('commissions_start_at')->nullable();    // pedidos antes disso não geram comissão no hub
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('mvx_vendor_id')->nullable()->unique(); // id do vendedor no MVX (loja antiga)
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('vendor_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->decimal('vendor_commission_pct', 5, 2)->nullable()->after('vendor_id'); // sobrepõe o % do vendedor
        });

        Schema::table('users', function (Blueprint $table) {
            // perfil "vendedor": a conta só enxerga as vendas e repasses deste vendedor
            $table->foreignId('vendor_id')->nullable()->after('role')->constrained()->nullOnDelete();
        });

        Schema::create('vendor_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->index();               // draft | paid | cancelled
            $table->date('period_until');                        // comissões até esta data
            $table->bigInteger('amount_cents')->default(0);
            $table->unsignedInteger('commissions_count')->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->string('method', 20)->nullable();            // pix | bank | other
            $table->string('reference', 190)->nullable();        // id da transferência, comprovante
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('vendor_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete(); // nome fica em product_name
            $table->string('kind', 10)->default('sale');          // sale | reversal (estorno de comissão já repassada)
            $table->string('product_name');
            $table->unsignedInteger('quantity')->default(1);
            $table->bigInteger('base_cents')->default(0);         // valor dos itens (sem frete, já com desconto)
            $table->decimal('rate', 5, 2);
            $table->bigInteger('amount_cents');                   // negativo no estorno
            $table->string('status', 20)->index();                // pending | available | paid | cancelled
            $table->timestamp('earned_at')->nullable()->index();  // data do pedido
            $table->foreignId('payout_id')->nullable()->constrained('vendor_payouts')->nullOnDelete();
            $table->timestamps();

            $table->unique(['order_id', 'product_id', 'kind']);
            $table->index(['vendor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_commissions');
        Schema::dropIfExists('vendor_payouts');
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('vendor_id'));
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendor_id');
            $table->dropColumn('vendor_commission_pct');
        });
        Schema::dropIfExists('vendors');
    }
};
