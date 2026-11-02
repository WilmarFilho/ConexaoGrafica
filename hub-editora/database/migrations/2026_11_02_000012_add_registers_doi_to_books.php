<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Livro que é só produto da loja (sem DOI): o Hub guarda sumário, capítulos e
 * ficha, publica a página no site, mas não deposita na Crossref.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->boolean('registers_doi')->default(true)->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('books', fn (Blueprint $table) => $table->dropColumn('registers_doi'));
    }
};
