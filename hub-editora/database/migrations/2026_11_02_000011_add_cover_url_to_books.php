<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Capa do livro (imagem do produto da loja), devolvida pelo site a cada publicação.
 * Só as fichas em PDF usam: o Hub baixa a imagem e embute no arquivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('cover_url', 500)->nullable()->after('landing_url');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn('cover_url');
        });
    }
};
