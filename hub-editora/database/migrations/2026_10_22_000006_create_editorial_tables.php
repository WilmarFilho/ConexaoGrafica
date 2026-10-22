<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Editorial (Conexão DOI, frente A): pessoas, instituições, livros,
 * capítulos, contribuições e links comerciais. Campos e valores seguem a
 * seção 2 do contrato (conexao-editora/docs/conexao-doi-contrato.md).
 *
 * Os "enums" são strings curtas, como em `orders.status`; os valores válidos
 * e os rótulos ficam nas constantes dos models. As tabelas `imports`,
 * `deposits`, `deposit_items` e `documents` pertencem às outras frentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Autores, organizadores, tradutores... O e-mail é interno, nunca publicado.
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('name');                            // como aparece na obra
            $table->string('sort_name');                       // "SOBRENOME, Nome", gerado se vazio
            $table->string('orcid', 19)->nullable()->index();  // 0000-0000-0000-000X, validado
            $table->string('email')->nullable();
            $table->text('bio')->nullable();
            $table->unsignedBigInteger('wp_term_id')->nullable(); // termo da taxonomia `autor` no WP
            $table->timestamps();
        });

        Schema::create('institutions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('acronym', 30)->nullable();
            $table->string('city', 120);
            $table->string('state', 60);
            $table->char('country', 2)->default('BR');         // ISO-2
            $table->string('ror_id', 40)->nullable();
            $table->timestamps();
        });

        Schema::create('books', function (Blueprint $table) {
            $table->id();
            // vínculo com o catálogo do Hub (que já aponta para o produto Woo)
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            // obra
            $table->string('title', 500);
            $table->string('subtitle', 500)->nullable();
            $table->string('alt_title', 500)->nullable();
            $table->string('alt_title_lang', 5)->default('en');
            $table->string('type', 20)->default('edited_book'); // edited_book | monograph | reference | other
            $table->string('series_title')->nullable();
            $table->string('series_issn', 9)->nullable();
            $table->string('volume', 20)->nullable();
            $table->unsignedSmallInteger('edition_number')->nullable();
            $table->string('language', 5)->default('pt');      // ISO-639-1
            $table->json('extra_languages')->nullable();
            $table->string('cover_path')->nullable();           // privado; a capa pública vem do WP/produto

            // publicação
            $table->string('publisher')->default('Conexão Editora');
            $table->string('publication_place')->default('Goiânia');
            $table->unsignedSmallInteger('publication_year');
            $table->date('publication_date')->nullable();
            $table->unsignedInteger('pages')->nullable();
            $table->string('format', 10)->default('both');     // print | ebook | both
            $table->string('subject_area');
            $table->string('subject_subarea')->nullable();
            $table->string('audience', 500)->nullable();

            // identificadores
            $table->string('isbn_print', 17)->nullable();
            $table->string('isbn_ebook', 17)->nullable();
            $table->json('other_isbns')->nullable();           // [{type, value}]
            $table->string('doi_prefix', 20);
            $table->string('doi_suffix', 60);
            $table->string('landing_url')->nullable();         // preenchida pela publicação no WP

            // textos
            $table->text('abstract');                          // resumo científico
            $table->text('abstract_en')->nullable();
            $table->text('synopsis')->nullable();              // texto comercial, não vai no XML
            $table->json('keywords')->nullable();
            $table->json('keywords_en')->nullable();
            $table->json('references')->nullable();            // [{text, doi?}]

            // estado
            $table->string('status', 15)->default('draft');    // draft | review | approved | published | unpublished
            $table->string('doi_status', 15)->default('none'); // none | validated | ready | sent | processing | registered | failed
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('wp_post_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('slug')->unique();                  // gerado do título; imutável após doi_status >= sent
            $table->timestamps();

            $table->unique(['doi_prefix', 'doi_suffix']);      // o DOI ({prefixo}/{sufixo}) é único
            $table->index(['status', 'doi_status']);
        });

        // Capítulo pertence a exatamente um livro; todo capítulo recebe DOI.
        Schema::create('chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');          // ordem no livro
            $table->string('section')->nullable();             // parte/seção do sumário
            $table->string('number', 20)->nullable();          // número impresso
            $table->string('title', 500);
            $table->string('subtitle', 500)->nullable();
            $table->string('title_en', 500)->nullable();
            $table->string('language', 5)->nullable();         // herda do livro
            $table->unsignedInteger('first_page');             // números impressos
            $table->unsignedInteger('last_page');
            $table->date('publication_date')->nullable();      // herda do livro
            $table->text('abstract_original')->nullable();
            $table->text('abstract_ai')->nullable();
            $table->text('abstract_approved')->nullable();     // o único que é publicado
            $table->json('keywords')->nullable();
            $table->json('references')->nullable();
            $table->string('doi_suffix', 70);                  // {sufixo do livro}.{NN}
            $table->string('landing_url')->nullable();
            $table->string('slug');
            $table->string('status', 15)->default('draft');    // draft | review | approved
            $table->string('doi_status', 15)->default('none'); // mesmo enum do livro
            $table->unsignedBigInteger('wp_post_id')->nullable();
            $table->json('ai_evidence')->nullable();           // por campo: página e trecho
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['book_id', 'doi_suffix']);
            $table->unique(['book_id', 'slug']);
            $table->index(['book_id', 'sequence']);
        });

        // Quem contribuiu em quê (livro ou capítulo), com papel e ordem.
        Schema::create('contributions', function (Blueprint $table) {
            $table->id();
            $table->string('contributable_type', 20);          // book | chapter
            $table->unsignedBigInteger('contributable_id');
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('role', 15)->default('author');     // author | editor (organizador) | translator | reviewer | other
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->string('affiliation_text')->nullable();    // quando a instituição não está cadastrada
            $table->timestamps();

            $table->index(['contributable_type', 'contributable_id', 'sequence'], 'contributions_target_sequence');
            $table->unique(['contributable_type', 'contributable_id', 'person_id', 'role'], 'contributions_person_role_unique');
        });

        // Caixa de compra: produto Woo pelo ID (preço e estoque reais) ou, sem produto, URL da loja.
        Schema::create('commercial_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('modality', 10);                    // print | ebook | combo
            $table->string('label', 120);
            $table->unsignedBigInteger('woo_product_id')->nullable();
            $table->string('url')->nullable();                 // só se não houver produto Woo; HTTPS, domínio da editora
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();

            $table->index(['book_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_links');
        Schema::dropIfExists('contributions');
        Schema::dropIfExists('chapters');
        Schema::dropIfExists('books');
        Schema::dropIfExists('institutions');
        Schema::dropIfExists('people');
    }
};
