<?php

namespace Tests\Feature;

use App\Filament\Resources\Books\Schemas\BookWizard;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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

    public function test_book_product_selector_lists_only_the_site_store_and_keeps_the_saved_value(): void
    {
        config(['hub.wordpress.url' => 'https://loja.test', 'hub.wordpress.secret' => str_repeat('s', 40)]);
        Cache::forget('hub.wp.products');
        Http::fake(['loja.test/wp-json/conexao-doi/v1/produtos' => Http::response(['products' => [
            ['id' => 249, 'name' => 'Atualidades em Ultrassonografia', 'sku' => '', 'type' => 'simple', 'virtual' => true],
            ['id' => 300, 'name' => 'Tratado', 'sku' => 'T1', 'type' => 'variable', 'virtual' => false],
        ]])]);

        $options = BookWizard::wooProducts(7216);

        $this->assertSame([249, 300, 7216], array_keys($options));
        $this->assertSame('Atualidades em Ultrassonografia · E-book (ID 249)', $options[249]);
        $this->assertSame('Tratado · com variações (ID 300)', $options[300]);
        $this->assertSame('Produto fora da loja (ID 7216)', $options[7216]);
        Http::assertSent(fn ($request) => $request->hasHeader('X-Conexao-Signature') && $request->method() === 'GET');

        // Site fora do ar: volta ao catálogo do Hub em vez de quebrar o formulário.
        Cache::forget('hub.wp.products');
        Http::fake(['loja.test/*' => Http::response('erro', 500)]);
        $this->assertIsArray(BookWizard::wooProducts());
    }
}
