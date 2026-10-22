<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conexão DOI, frente C: importação de livros em PDF com IA.
 * Tabela `imports` do contrato (conexao-doi-contrato.md, seção 2). O PDF fica em
 * storage/app/private/imports/ e a proposta completa (livro + capítulos + pessoas,
 * com evidência e confiança por campo) vai em `result`. Nada aqui grava em
 * books/chapters: isso é a aprovação, feita pela frente A.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            // books ainda não existe nesta frente: sem constraint, a frente A liga depois.
            $table->unsignedBigInteger('book_id')->nullable()->index();
            $table->string('file_path');
            $table->string('original_name');
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('pages')->nullable();
            $table->string('status', 20)->default('received')->index();
            $table->string('model', 60)->nullable();
            $table->string('prompt_version', 40)->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->json('usage')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};
