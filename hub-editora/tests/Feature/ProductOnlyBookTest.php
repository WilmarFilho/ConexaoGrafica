<?php

namespace Tests\Feature;

use App\Crossref\BookDeposits;
use App\Crossref\Fees;
use App\Editorial\BookWorkflow;
use App\Editorial\Documents\SheetGenerator;
use App\Editorial\EditorialException;
use App\Editorial\WordPress\Payload;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Book;
use App\Models\Document;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\MakesEditorialFixtures;
use Tests\TestCase;

/**
 * Livro que é só produto da loja: o Hub guarda resumo, sumário e capítulos e
 * publica a página no site, sem DOI e sem depósito na Crossref.
 */
class ProductOnlyBookTest extends TestCase
{
    use MakesEditorialFixtures, RefreshDatabase;

    public function test_book_without_doi_has_no_doi_anywhere(): void
    {
        $book = $this->tratado();
        $book->update(['registers_doi' => false]);
        $book = $book->fresh();

        $this->assertSame('', $book->doi);
        $this->assertSame('', $book->doi_url);
        $this->assertSame('', $book->chapters()->first()->doi);

        // o checklist não cobra DOI, nem do livro nem dos capítulos
        $this->assertNotContains('doi', array_column(BookWorkflow::checklist($book), 'key'));
        $this->assertNotContains('sufixo DOI', BookWorkflow::chapterIssues($book->chapters()->first()));

        $payload = Payload::forBook($book);
        $this->assertSame('', $payload['book']['doi']);
        $this->assertSame([''], array_values(array_unique(array_column($payload['chapters'], 'doi'))));

        // a ficha sai sem DOI e sem quebrar
        $generator = app(SheetGenerator::class);
        $this->assertStringStartsWith('%PDF', $generator->renderBook($book));
        $this->assertStringStartsWith('%PDF', $generator->renderChapter($book->chapters()->first()));
    }

    public function test_book_without_doi_cannot_be_deposited(): void
    {
        $book = $this->tratado();
        $book->forceFill(['registers_doi' => false, 'status' => 'published', 'landing_url' => 'https://conexaoeditora.com.br/livro/x/'])->saveQuietly();

        $this->expectException(EditorialException::class);
        $this->expectExceptionMessage('sem DOI');

        BookDeposits::make()->register($book->fresh(), User::factory()->create(['role' => 'admin']));
    }

    public function test_crossref_fee_estimate_counts_only_new_records(): void
    {
        $book = $this->tratado();
        $book->update(['publication_year' => (int) date('Y')]);
        $chapters = $book->chapters()->count();

        $e = Fees::estimate($book->fresh());
        $this->assertSame(1, $e['book']);
        $this->assertSame($chapters, $e['chapters']);
        $this->assertEqualsWithDelta(1.00 + 0.25 * $chapters, $e['total'], 0.001);
        $this->assertStringContainsString('US$ 1,00 (livro)', Fees::summary($book->fresh()));

        // obra antiga: tarifa "back-year"; atualização de DOI registrado: grátis
        $book->update(['publication_year' => (int) date('Y') - 5]);
        $this->assertEqualsWithDelta(0.15 * ($chapters + 1), Fees::estimate($book->fresh())['total'], 0.001);

        $book->forceFill(['doi_status' => 'registered'])->saveQuietly();
        $book->chapters()->update(['doi_status' => 'registered']);
        $this->assertSame(0.0, Fees::estimate($book->fresh())['total']);
    }

    public function test_sheet_without_the_cover_is_generated_again_next_time(): void
    {
        config(['hub.wordpress.url' => 'https://conexaoeditora.test']);
        Http::fake(['conexaoeditora.test/*' => Http::sequence()->push('erro', 500)->push(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGD4DwABBAEAwS2OUAAAAABJRU5ErkJggg=='), 200, ['Content-Type' => 'image/png'])]);

        $book = $this->tratado();
        $book->update(['cover_url' => 'https://conexaoeditora.test/capa.png']);
        $generator = app(SheetGenerator::class);

        $first = $generator->forBook($book->fresh());
        $this->assertNotSame(SheetGenerator::hashOf($generator->bookData($book->fresh(), Payload::forBook($book->fresh()))), $first->hash);

        // a capa voltou a responder: sem --forcar, a ficha é refeita
        $second = $generator->forBook($book->fresh());
        $this->assertTrue($second->generated_at->gte($first->generated_at));
        $this->assertSame(SheetGenerator::hashOf($generator->bookData($book->fresh(), Payload::forBook($book->fresh()))), $second->hash);
        $this->assertSame(1, Document::where('type', Document::BOOK_SHEET)->count());
    }

    public function test_product_page_creates_the_book_without_doi(): void
    {
        $product = Product::create(['name' => 'E-book Manual de Teste - Impresso', 'sku' => 'WOO-9', 'isbn' => '978-85-333-0227-3', 'formats' => ['fisico'], 'active' => true]);

        $book = Book::createForProduct($product);

        $this->assertFalse($book->registersDoi());
        $this->assertSame('Manual de Teste', $book->title);
        $this->assertSame($product->id, $book->product_id);
        $this->assertSame('978-85-333-0227-3', $book->isbn_print);
        $this->assertSame($book->id, $product->fresh()->doiBook()->id);

        // a página do produto mostra o atalho certo para cada caso
        $this->actingAs(User::factory()->create(['role' => 'admin', 'app_authentication_secret' => 'segredo']));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $outro = Product::create(['name' => 'Sem livro', 'sku' => 'WOO-10', 'formats' => ['ebook'], 'active' => true]);
        Livewire::test(EditProduct::class, ['record' => $outro->getRouteKey()])
            ->assertSee('Cadastrar sumário e capítulos');

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertSee('Importar sumário e capítulos do PDF')
            ->assertSee('Editar sumário e capítulos');
    }
}
