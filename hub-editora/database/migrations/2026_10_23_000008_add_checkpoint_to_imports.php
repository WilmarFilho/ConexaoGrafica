<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ponto de retomada da importação com IA: leitura da obra e segmentação já pagas
 * ficam guardadas, e uma falha no meio (limite da API, queda de rede) continua
 * de onde parou em vez de pagar tudo de novo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->longText('checkpoint')->nullable()->after('usage');
        });
    }

    public function down(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->dropColumn('checkpoint');
        });
    }
};
