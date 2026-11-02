<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Direitos autorais (licenças Creative Commons, App\Editorial\License): lista de
 * códigos no livro e no capítulo. Capítulo com null herda as do livro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->json('licenses')->nullable()->after('keywords_en');
        });
        Schema::table('chapters', function (Blueprint $table) {
            $table->json('licenses')->nullable()->after('keywords');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn('licenses');
        });
        Schema::table('chapters', function (Blueprint $table) {
            $table->dropColumn('licenses');
        });
    }
};
