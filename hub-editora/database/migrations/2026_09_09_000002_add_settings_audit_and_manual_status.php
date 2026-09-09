<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chaves de integração editáveis pelo painel. Valor criptografado com APP_KEY
        // (cast "encrypted" no model); o .env continua valendo como reserva.
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();   // ex.: woocommerce.key
            $table->text('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Quem fez a ação (null = automático via integração/agendador).
        Schema::table('sync_logs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('channel_id')->constrained()->nullOnDelete();
        });

        // Etapa travada por decisão humana: o sync do canal não a sobrescreve.
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('status_manual')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('status_manual'));
        Schema::table('sync_logs', function (Blueprint $t) {
            $t->dropConstrainedForeignId('user_id');
        });
        Schema::dropIfExists('settings');
    }
};
