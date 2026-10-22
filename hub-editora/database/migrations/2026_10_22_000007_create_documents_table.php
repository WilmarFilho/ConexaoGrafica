<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conexão DOI, frente E: fichas científicas em PDF (livro e capítulo),
 * conforme a tabela `documents` da seção 2 do contrato. O arquivo fica em
 * `storage/app/private/documents/`; a cópia pública é o anexo no WordPress.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('documentable_type', 20);           // book | chapter
            $table->unsignedBigInteger('documentable_id');
            $table->string('type', 20);                        // book_sheet | chapter_sheet
            $table->string('template_version', 20);
            $table->string('hash', 64);                        // sha256 dos dados usados + versão do template
            $table->string('path');                            // privado, relativo ao disco local
            $table->unsignedBigInteger('wp_attachment_id')->nullable();
            $table->string('public_url')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->boolean('stale')->default(true);           // o site não tem (ou tem outra versão de) esta ficha
            $table->timestamps();

            $table->unique(['documentable_type', 'documentable_id', 'type'], 'documents_target_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
