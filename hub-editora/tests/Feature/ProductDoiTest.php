<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\MakesEditorialFixtures;
use Tests\TestCase;

/** Catálogo de produtos: quais já têm livro DOI e em que situação. */
class ProductDoiTest extends TestCase
{
    use MakesEditorialFixtures, RefreshDatabase;

    /** @return array{0: Product, 1: Product, 2: Product} sem livro, livro cadastrado, DOI registrado */
    private function catalog(): array
    {
        $sem = Product::create(['name' => 'Sem DOI', 'sku' => 'WOO-1', 'formats' => ['ebook'], 'active' => true]);
        $cadastrado = Product::create(['name' => 'Cadastrado', 'sku' => 'WOO-2', 'formats' => ['ebook'], 'active' => true]);
        $registrado = Product::create(['name' => 'Registrado', 'sku' => 'WOO-3', 'formats' => ['ebook'], 'active' => true]);

        $this->makeBook(['title' => 'Livro em rascunho', 'product_id' => $cadastrado->id]);
        $this->makeBook(['title' => 'Livro com DOI', 'product_id' => $registrado->id, 'isbn_print' => '978-0-306-40615-7'])
            ->forceFill(['status' => 'published', 'doi_status' => 'registered'])->saveQuietly();

        return [$sem, $cadastrado, $registrado];
    }

    private function admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'app_authentication_secret' => 'segredo']));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_products_are_split_by_doi_situation(): void
    {
        [$sem, $cadastrado, $registrado] = $this->catalog();

        $ids = fn (string $situation) => Product::query()->doiSituation($situation)->pluck('id')->all();

        $this->assertSame([$sem->id], $ids('sem'));
        $this->assertSame([$cadastrado->id], $ids('cadastrado'));
        $this->assertSame([$registrado->id], $ids('registrado'));
        $this->assertCount(3, Product::query()->doiSituation(null)->get());
        $this->assertSame('Livro com DOI', $registrado->doiBook()->title);
        $this->assertNull($sem->doiBook());
    }

    public function test_product_list_filters_and_shows_the_doi_situation(): void
    {
        [$sem, $cadastrado, $registrado] = $this->catalog();
        $this->admin();

        Livewire::test(ListProducts::class)
            ->assertCanSeeTableRecords([$sem, $cadastrado, $registrado])
            ->assertSee('Registrado')
            ->filterTable('doi', 'registrado')
            ->assertCanSeeTableRecords([$registrado])
            ->assertCanNotSeeTableRecords([$sem, $cadastrado])
            ->filterTable('doi', 'sem')
            ->assertCanSeeTableRecords([$sem])
            ->assertCanNotSeeTableRecords([$cadastrado, $registrado]);
    }

    public function test_product_page_tells_whether_a_doi_book_is_linked(): void
    {
        [$sem, , $registrado] = $this->catalog();
        $this->admin();

        Livewire::test(EditProduct::class, ['record' => $registrado->getRouteKey()])
            ->assertSee('Livro DOI')
            ->assertSee('Livro com DOI')
            ->assertSee($registrado->doiBook()->doi)
            ->assertSee('Registrado');

        Livewire::test(EditProduct::class, ['record' => $sem->getRouteKey()])
            ->assertSee('Nenhum livro DOI ligado a este produto');
    }
}
