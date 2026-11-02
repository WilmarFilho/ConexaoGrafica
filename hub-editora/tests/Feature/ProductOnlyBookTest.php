<?php

namespace Tests\Feature;

use App\Crossref\BookDeposits;
use App\Editorial\BookWorkflow;
use App\Editorial\Documents\SheetGenerator;
use App\Editorial\EditorialException;
use App\Editorial\WordPress\Payload;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Book;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
