<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conexão DOI, frente D: depósitos de metadados na Crossref.
 * Um `deposit` é um arquivo XML (doi_batch) enviado; cada `deposit_item` é um DOI
 * daquele arquivo (o livro e cada capítulo), com o resultado individual que a
 * Crossref devolve no relatório de processamento.
 * Contrato: conexao-editora/docs/conexao-doi-contrato.md, seção 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id', 100)->unique();          // doi_batch_id do XML
            $table->string('environment', 10);                  // test | production
            // Sem FK por enquanto: a tabela `books` é da frente A e chega em paralelo.
            // Quando existir, trocar por foreignId('book_id')->constrained().
            $table->unsignedBigInteger('book_id')->index();
            $table->string('xml_path');                         // relativo ao disco `local` (storage/app/private)
            $table->string('checksum', 64);                     // sha256 do XML gravado
            $table->string('status', 20)->default('queued')->index(); // queued | sent | processing | done | failed
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('response')->nullable();               // resposta bruta (HTML do depósito ou XML do resultado)
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('result_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable(); // users.id
            $table->timestamps();
        });

        Schema::create('deposit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deposit_id')->constrained()->cascadeOnDelete();
            $table->string('target_type', 10);                  // book | chapter
            $table->unsignedBigInteger('target_id');            // books.id ou chapters.id (sem FK por enquanto)
            $table->string('doi')->index();
            $table->string('status', 20)->default('pending');   // pending | registered | failed
            $table->text('message')->nullable();                // msg do record_diagnostic
            $table->timestamps();

            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_items');
        Schema::dropIfExists('deposits');
    }
};
