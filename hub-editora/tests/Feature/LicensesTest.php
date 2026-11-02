<?php

namespace Tests\Feature;

use App\Crossref\Validator;
use App\Crossref\XmlBuilder;
use App\Editorial\BookWorkflow;
use App\Editorial\Documents\SheetGenerator;
use App\Editorial\Import\Ai\AiGateway;
use App\Editorial\Import\Integrator;
use App\Editorial\License;
use App\Editorial\WordPress\Payload;
use App\Jobs\ProcessImport;
use App\Models\Import;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Tests\Support\FakeAiGateway;
use Tests\Support\MakesEditorialFixtures;
use Tests\Support\PdfFixture;
use Tests\TestCase;

/** Direitos autorais: licenças Creative Commons no livro e no capítulo (reunião de 02/11/2026). */
class LicensesTest extends TestCase
{
    use MakesEditorialFixtures, RefreshDatabase;

    public function test_catalog_normalizes_codes_and_builds_the_statement(): void
    {
        $this->assertCount(7, License::ALL);
        $this->assertSame(['cc-by', 'cc-by-nc'], License::normalize(['CC-BY-NC', 'cc-by', 'cc-by', 'gpl', '']), 'ordem do catálogo, sem repetir, sem desconhecidas');

        [$nc] = License::expand(['cc-by-nc'], 'Este livro está licenciado');
        $this->assertSame('CC BY-NC 4.0', $nc['name']);
        $this->assertSame(['by', 'nc'], $nc['elements']);
        $this->assertSame('https://creativecommons.org/licenses/by-nc/4.0/', $nc['url']);
        $this->assertStringStartsWith('Este livro está licenciado sob uma Licença Creative Commons Atribuição-NãoComercial 4.0 Internacional (CC BY-NC 4.0). Você tem', $nc['statement']);

        [$zero] = License::expand(['cc0']);
        $this->assertStringStartsWith('O autor renunciou', $zero['statement'], 'CC0 não "licencia": dedica ao domínio público');
        $this->assertSame(['https://creativecommons.org/publicdomain/zero/1.0/'], License::urls(['cc0']));
    }

    public function test_payload_sends_book_licenses_and_chapters_inherit_unless_they_have_their_own(): void
    {
        $book = $this->tratado();
        $book->update(['licenses' => ['cc-by-nc']]);
        $book->chapters()->where('sequence', 2)->update(['licenses' => json_encode(['cc-by', 'cc-by-sa'])]);

        $p = Payload::forBook($book->fresh());

        $this->assertSame(['cc-by-nc'], array_column($p['book']['licenses'], 'code'));
        $this->assertStringStartsWith('Este livro está licenciado', $p['book']['licenses'][0]['statement']);

        [$c1, $c2] = $p['chapters'];
        $this->assertSame(['cc-by-nc'], array_column($c1['licenses'], 'code'));
        $this->assertTrue($c1['licenses_inherited']);
        $this->assertStringStartsWith('Este capítulo está licenciado', $c1['licenses'][0]['statement']);
        $this->assertSame(['cc-by', 'cc-by-sa'], array_column($c2['licenses'], 'code'));
        $this->assertFalse($c2['licenses_inherited']);
        $chapters = $book->fresh()->chapters()->orderBy('sequence')->get();
        $this->assertSame(['cc-by-nc'], $chapters[0]->effectiveLicenses());
        $this->assertSame(['cc-by', 'cc-by-sa'], $chapters[1]->effectiveLicenses());
    }

    public function test_checklist_warns_without_license_but_does_not_block(): void
    {
        $book = $this->tratado();
        $item = collect(BookWorkflow::checklist($book))->firstWhere('key', 'licenses');
        $this->assertFalse($item['ok']);
        $this->assertSame('info', $item['stage'], 'aviso, não trava a revisão nem a publicação');

        $book->update(['licenses' => ['cc-by']]);
        $this->assertTrue(collect(BookWorkflow::checklist($book->fresh()))->firstWhere('key', 'licenses')['ok']);
    }

    public function test_crossref_xml_carries_the_licenses_as_access_indicators_and_passes_the_xsd(): void
    {
        config([
            'hub.crossref.prefix' => '10.00000', 'hub.crossref.depositor_name' => 'Conexão Editora',
            'hub.crossref.depositor_email' => 'doi@conexaoeditora.com.br', 'hub.crossref.registrant' => 'Conexão Editora',
            'hub.crossref.landing_base' => 'https://conexaoeditora.com.br',
        ]);
        $payload = json_decode((string) file_get_contents(base_path('tests/Fixtures/crossref/publicacao-tratado.json')), true);
        $payload['book']['licenses'] = License::expand(['cc-by-nc', 'cc-by']);
        $payload['chapters'][0]['licenses'] = License::expand(['cc0']);
        $payload['chapters'][1]['licenses'] = [];

        $xml = XmlBuilder::fromConfig()->build($payload, 'conexao-1-licencas');

        $this->assertSame([], (new Validator)->errors($xml), 'ai:program na posição certa (antes do doi_data)');
        $this->assertStringContainsString('xmlns:ai="http://www.crossref.org/AccessIndicators.xsd"', $xml);
        $this->assertSame(2, substr_count($xml, '<ai:program name="AccessIndicators">'), 'livro e capítulo 1; o capítulo 2 vai sem');
        $this->assertStringContainsString('<ai:license_ref applies_to="vor">https://creativecommons.org/licenses/by/4.0/</ai:license_ref>', $xml);
        $this->assertStringContainsString('<ai:license_ref applies_to="vor">https://creativecommons.org/licenses/by-nc/4.0/</ai:license_ref>', $xml);
        $this->assertStringContainsString('<ai:license_ref applies_to="vor">https://creativecommons.org/publicdomain/zero/1.0/</ai:license_ref>', $xml);

        // Sem licença nenhuma, o XML continua igual ao de antes (nenhum ai:program).
        $this->assertStringNotContainsString('ai:program', XmlBuilder::fromConfig()->build(
            json_decode((string) file_get_contents(base_path('tests/Fixtures/crossref/publicacao-tratado.json')), true), 'conexao-1-sem'));
    }

    public function test_sheets_show_the_license_block(): void
    {
        $book = $this->tratado();
        $book->update(['licenses' => ['cc-by-nc-nd']]);
        $generator = app(SheetGenerator::class);
        $data = $generator->bookData($book->fresh(), Payload::forBook($book->fresh()));

        $html = View::make('documents.book', $data + ['qr' => $generator->qrDataUri('https://x'), 'generated_at' => now(), 'template_version' => SheetGenerator::TEMPLATE_VERSION])->render();

        $this->assertStringContainsString('Direitos autorais', $html);
        $this->assertStringContainsString('CC BY-NC-ND 4.0', $html);
        $this->assertStringContainsString('Este livro está licenciado sob uma Licença Creative Commons Atribuição-NãoComercial-SemDerivações', $html);
        $this->assertStringContainsString('https://creativecommons.org/licenses/by-nc-nd/4.0/', $html);
    }

    public function test_import_reads_a_printed_license_and_integration_saves_it_on_the_book(): void
    {
        Storage::fake('local');
        config(['hub.ai.key' => 'sk-proj-teste-nunca-usada', 'hub.ai.model' => 'gpt-6-astra', 'hub.ai.gs_bin' => null]);
        $ai = new class extends FakeAiGateway
        {
            public function frontMatter(): array
            {
                return ['licenses' => ['CC-BY-NC', 'inventada']] + parent::frontMatter();
            }
        };
        $this->app->instance(AiGateway::class, $ai);
        $pdf = PdfFixture::write();

        $import = Import::receive($pdf, 'livro.pdf');
        ProcessImport::dispatchSync($import->id);
        @unlink($pdf);
        $this->assertSame(['cc-by-nc'], $import->fresh()->result['book']['licenses']);
        $this->assertStringContainsString('Licença Creative Commons só se estiver impressa', $ai->calls[0]['instructions']);

        $r = $import->fresh()->result;
        $r['book']['approved'] = true;
        foreach ($r['chapters'] as $i => $c) {
            $r['chapters'][$i]['approved'] = true;
        }
        $import->forceFill(['result' => $r, 'status' => Import::APPROVED])->save();

        $book = Integrator::integrate($import->fresh(), User::factory()->create(['role' => 'editor']));
        $this->assertSame(['cc-by-nc'], $book->licenses);
    }
}
