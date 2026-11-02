<?php

namespace Tests\Feature;

use App\Crossref\Fees;
use App\Models\Deposit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesEditorialFixtures;
use Tests\TestCase;

/** Virada da Crossref para produção: registro de homologação não conta mais. */
class CrossrefGoLiveTest extends TestCase
{
    use MakesEditorialFixtures, RefreshDatabase;

    private function deposit(int $bookId, string $env, string $batch): void
    {
        Deposit::create(['batch_id' => $batch, 'environment' => $env, 'book_id' => $bookId, 'xml_path' => 'x.xml', 'checksum' => str_repeat('a', 64), 'status' => 'done']);
    }

    public function test_go_live_resets_books_registered_only_in_test(): void
    {
        $test = $this->tratado();
        $test->update(['publication_year' => (int) date('Y')]);
        $test->forceFill(['doi_status' => 'registered'])->saveQuietly();
        $test->chapters()->update(['doi_status' => 'registered']);
        $this->deposit($test->id, Deposit::TEST, 'teste-1');

        $prod = $this->makeBook(['title' => 'Livro já em produção']);
        $prod->forceFill(['doi_status' => 'registered'])->saveQuietly();
        $this->deposit($prod->id, Deposit::PRODUCTION, 'prod-1');

        // ainda em homologação: recusa
        config(['hub.crossref.env' => 'test']);
        $this->artisan('hub:crossref-producao --aplicar')->assertFailed();

        config(['hub.crossref.env' => 'production']);
        $this->artisan('hub:crossref-producao')->assertSuccessful();
        $this->assertSame('registered', $test->fresh()->doi_status, 'sem --aplicar não grava');

        $this->artisan('hub:crossref-producao --aplicar')->assertSuccessful();

        $this->assertSame('validated', $test->fresh()->doi_status);
        $this->assertSame(['validated'], $test->chapters()->pluck('doi_status')->unique()->values()->all());
        $this->assertSame('registered', $prod->fresh()->doi_status);
        $this->assertGreaterThan(0, Fees::estimate($test->fresh())['total']);
    }
}
