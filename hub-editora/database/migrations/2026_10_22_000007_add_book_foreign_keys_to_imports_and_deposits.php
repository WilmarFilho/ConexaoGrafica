<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conexão DOI, frente E1: as frentes C (imports) e D (deposits) nasceram em paralelo
 * à frente A e deixaram `book_id` sem chave estrangeira. Agora que `books` existe:
 *   imports.book_id  → books.id, nullOnDelete  (a importação sobrevive sem o livro)
 *   deposits.book_id → books.id, cascadeOnDelete (depósito não existe sem o livro;
 *                      e livro com DOI enviado não é apagável pelo painel)
 *
 * SQLite (suíte de testes) não acrescenta FK em tabela existente sem recriá-la, e
 * os testes da frente D gravam depósitos com `book_id` sem livro: a FK fica só
 * para MariaDB/MySQL (produção).
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->isSqlite()) {
            return;
        }

        Schema::table('imports', function (Blueprint $table) {
            $table->foreign('book_id')->references('id')->on('books')->nullOnDelete();
        });

        Schema::table('deposits', function (Blueprint $table) {
            $table->foreign('book_id')->references('id')->on('books')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if ($this->isSqlite()) {
            return;
        }

        Schema::table('deposits', function (Blueprint $table) {
            $table->dropForeign(['book_id']);
        });

        Schema::table('imports', function (Blueprint $table) {
            $table->dropForeign(['book_id']);
        });
    }

    private function isSqlite(): bool
    {
        return Schema::getConnection()->getDriverName() === 'sqlite';
    }
};
