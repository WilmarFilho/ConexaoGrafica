<?php

namespace App\Editorial\Documents;

use App\Editorial\Citations;
use App\Editorial\WordPress\Payload;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Document;
use App\Models\SyncLog;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Closure;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/**
 * Fichas científicas em PDF (dompdf + QR Code), uma por livro e uma por
 * capítulo aprovado. Os dados vêm do mesmo payload que vai ao site, por isso
 * as citações saem iguais às da página pública.
 *
 * O hash sha256 dos dados usados (mais a versão do template) fica no
 * `Document`: a ficha só é gerada de novo quando ele muda, quando o arquivo
 * sumiu ou quando se pede à força. Toda regeneração marca `stale`, porque o
 * site ainda tem a versão anterior (o PublishSheets limpa ao enviar).
 */
class SheetGenerator
{
    /** Mude ao alterar o layout: força a regeneração de todas as fichas. */
    public const TEMPLATE_VERSION = '2.0';

    /** Capa maior que isso não entra na ficha (o PDF ficaria pesado demais). */
    private const MAX_COVER_BYTES = 6 * 1024 * 1024;

    public const DIR = 'documents';

    public function forBook(Book $book, bool $force = false): Document
    {
        $payload = Payload::forBook($book);
        $data = $this->bookData($book, $payload);

        return $this->generate($book, Document::BOOK_SHEET, $data, $force,
            fn () => $this->renderBook($book, $payload, $data),
            self::DIR."/livro-{$book->id}/ficha-livro-{$book->slug}.pdf");
    }

    public function forChapter(Chapter $chapter, bool $force = false): Document
    {
        $book = $chapter->book;
        $payload = Payload::forBook($book);
        $data = $this->chapterData($chapter, $payload);
        $nn = str_pad((string) $chapter->sequence, 2, '0', STR_PAD_LEFT);

        return $this->generate($chapter, Document::CHAPTER_SHEET, $data, $force,
            fn () => $this->renderChapter($chapter, $payload, $data),
            self::DIR."/livro-{$book->id}/ficha-capitulo-{$nn}-{$chapter->slug}.pdf");
    }

    /** PDF em bytes, sem gravar nada (prévia). */
    public function renderBook(Book $book, ?array $payload = null, ?array $data = null): string
    {
        $payload ??= Payload::forBook($book);
        $data ??= $this->bookData($book, $payload);

        return $this->pdf(View::make('documents.book', $this->viewData($data))->render());
    }

    public function renderChapter(Chapter $chapter, ?array $payload = null, ?array $data = null): string
    {
        $payload ??= Payload::forBook($chapter->book);
        $data ??= $this->chapterData($chapter, $payload);

        return $this->pdf(View::make('documents.chapter', $this->viewData($data))->render());
    }

    public static function hashOf(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).'|'.self::TEMPLATE_VERSION);
    }

    // ---- Dados ---------------------------------------------------------------

    /**
     * Tudo que aparece na ficha do livro, e nada mais: situação, ids do WP e
     * documentos ficam de fora para não regenerar à toa.
     */
    public function bookData(Book $book, array $payload): array
    {
        $b = $payload['book'];
        unset($b['documents'], $b['status'], $b['doi_status'], $b['hub_id']);

        $chapters = collect($payload['chapters'])
            ->filter(fn ($c) => $c['status'] === 'approved')
            ->map(function ($c) {
                unset($c['documents'], $c['status'], $c['doi_status'], $c['references'], $c['hub_id']);

                return $c;
            })->values()->all();

        $cit = Citations::forBook($payload['book']);
        $b['citations'] = array_map(fn ($c) => $c['texto'], $cit);
        $b['citations_html'] = array_map(fn ($c) => $c['html'], $cit);

        return [
            'kind' => 'book',
            'book' => $b,
            'chapters' => $chapters,
            'landing_url' => $book->landing_url,
            'qr_url' => $book->landing_url ?: $book->doi_url,
            'organizers_label' => $book->type === 'edited_book' ? 'Organizadores' : 'Autores',
            'cover_url' => $book->cover_url,
        ];
    }

    public function chapterData(Chapter $chapter, array $payload): array
    {
        $b = $payload['book'];
        unset($b['documents'], $b['status'], $b['doi_status'], $b['hub_id'], $b['toc'], $b['references']);

        $c = collect($payload['chapters'])->firstWhere('hub_id', $chapter->id)
            ?? Payload::chapter($chapter, $chapter->book);
        unset($c['documents'], $c['status'], $c['doi_status'], $c['hub_id']);

        $cit = Citations::forChapter($c, $payload['book']);
        $c['citations'] = array_map(fn ($x) => $x['texto'], $cit);
        $c['citations_html'] = array_map(fn ($x) => $x['html'], $cit);

        // Afiliações numeradas, como no mockup: "Nome¹" e a lista 1. Instituição, cidade/UF.
        $affiliations = [];
        $authors = [];
        foreach (Citations::list($c['contributors']) as $person) {
            $aff = $person['affiliation'] ?? null;
            $index = null;
            if ($aff && filled($aff['name'] ?? null)) {
                $label = trim(implode(', ', array_filter([$aff['name'], trim(implode('/', array_filter([$aff['city'] ?? null, $aff['state'] ?? null]))) ?: null])));
                $index = array_search($label, $affiliations, true);
                if ($index === false) {
                    $affiliations[] = $label;
                    $index = count($affiliations) - 1;
                }
            }
            $authors[] = ['name' => $person['name'], 'orcid' => $person['orcid'] ?? null, 'affiliation' => $index === null ? null : $index + 1, 'role' => $person['role'] ?? 'author'];
        }

        return [
            'kind' => 'chapter',
            'book' => $b,
            'chapter' => $c,
            'authors' => $authors,
            'affiliations' => $affiliations,
            'landing_url' => $chapter->landing_url,
            'book_landing_url' => $chapter->book->landing_url,
            'qr_url' => $chapter->landing_url ?: ('https://doi.org/'.$c['doi']),
            'organizers_label' => $chapter->book->type === 'edited_book' ? 'Organizadores' : 'Autores',
            'cover_url' => $chapter->book->cover_url,
        ];
    }

    // ---- Geração ---------------------------------------------------------------

    private function generate(Book|Chapter $target, string $type, array $data, bool $force, Closure $render, string $path): Document
    {
        $hash = self::hashOf($data);

        /** @var Document $doc */
        $doc = Document::query()->firstOrNew([
            'documentable_type' => $target->getMorphClass(),
            'documentable_id' => $target->getKey(),
            'type' => $type,
        ]);

        $unchanged = $doc->exists && $doc->hash === $hash && $doc->template_version === self::TEMPLATE_VERSION && $doc->fileExists();

        if ($unchanged && ! $force) {
            return $doc;
        }

        $pdf = $render();
        $disk = Storage::disk(Document::DISK);

        if ($doc->exists && filled($doc->path) && $doc->path !== $path) {
            $disk->delete($doc->path); // o slug mudou: não deixa arquivo órfão
        }

        $disk->put($path, $pdf);

        $doc->fill([
            'template_version' => self::TEMPLATE_VERSION,
            'hash' => $hash,
            'path' => $path,
            'generated_at' => now(),
            'stale' => true,
        ])->save();

        SyncLog::record(null, SyncLog::OUT, 'document.generated', $target,
            Document::TYPES[$type].' gerada ('.number_format(strlen($pdf) / 1024, 0, ',', '.').' KB)',
            ['document_id' => $doc->id, 'hash' => substr($hash, 0, 12), 'template_version' => self::TEMPLATE_VERSION, 'forced' => $force]);

        return $doc;
    }

    private function viewData(array $data): array
    {
        return $data + [
            'qr' => $this->qrDataUri($data['qr_url']),
            'generated_at' => now(),
            'template_version' => self::TEMPLATE_VERSION,
            'logo' => self::fileDataUri(resource_path('images/logo-conexao-editora.png')),
            'cover' => $this->coverDataUri($data['cover_url'] ?? null),
        ];
    }

    /** Imagem local como data URI (o dompdf roda sem acesso remoto). */
    private static function fileDataUri(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }
        $mime = (string) (mime_content_type($path) ?: 'image/png');

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
    }

    /**
     * Capa do produto da loja, baixada e embutida. Só imagens do próprio site;
     * sem capa (ou com falha na rede) a faixa do topo sai sem ela.
     */
    public function coverDataUri(?string $url): ?string
    {
        if (blank($url) || ! str_starts_with((string) $url, 'https://')) {
            return null;
        }

        $site = (string) parse_url((string) config('hub.wordpress.url'), PHP_URL_HOST);
        $host = (string) parse_url((string) $url, PHP_URL_HOST);
        if ($site === '' || ($host !== $site && ! str_ends_with($host, '.'.$site))) {
            return null;
        }

        try {
            $response = Http::timeout(15)->get($url);
        } catch (\Throwable) {
            return null;
        }

        $mime = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        if (! $response->successful() || ! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true) || strlen($response->body()) > self::MAX_COVER_BYTES) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($response->body());
    }

    /** QR Code em SVG embutido (sem Imagick; o dompdf lê SVG). */
    public function qrDataUri(string $url): string
    {
        $style = new RendererStyle(240, 0, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(11, 37, 69)));
        $svg = (new Writer(new ImageRenderer($style, new SvgImageBackEnd)))->writeString($url);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    private function pdf(string $html): string
    {
        $cache = storage_path('framework/cache/dompdf');
        if (! is_dir($cache)) {
            @mkdir($cache, 0775, true);
        }

        // O CLI do servidor tem memory_limit=128M; o dompdf com as fontes DejaVu
        // e um sumário de 70+ capítulos passa disso. Só sobe, nunca desce.
        $limit = self::bytes((string) ini_get('memory_limit'));
        if ($limit > 0 && $limit < 256 * 1024 * 1024) {
            @ini_set('memory_limit', '256M');
        }

        $options = new Options;
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsRemoteEnabled(false);
        $options->setIsHtml5ParserEnabled(true);
        $options->setFontCache($cache);
        $options->setTempDir($cache);
        // as fontes Nunito ficam em resources/fonts; o resto do disco continua fora de alcance
        $options->setChroot([storage_path(), resource_path('fonts')]);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        // "01 / 05" à direita do rodapé, em azul (o CSS do dompdf não conta páginas).
        $canvas = $dompdf->getCanvas();
        $canvas->page_script(function (int $page, int $total, $canvas, $metrics) {
            $font = $metrics->getFont('Nunito Black') ?? $metrics->getFont('DejaVu Sans', 'bold');
            $text = sprintf('%02d / %02d', $page, $total);
            $width = $metrics->getTextWidth($text, $font, 7);
            // A4 = 595.28 x 841.89 pt; margem lateral de 18 mm (51 pt); na altura do texto do rodapé, abaixo da linha
            $canvas->text(595.28 - 51 - $width, 841.89 - 62.4 + 8 + 2 + 11, $text, $font, 7, [0.004, 0.592, 0.831]);
        });

        return (string) $dompdf->output();
    }

    /** "128M" → bytes (-1 = sem limite). */
    private static function bytes(string $ini): int
    {
        $ini = trim($ini);
        if ($ini === '' || $ini === '-1') {
            return -1;
        }
        $unit = strtolower(substr($ini, -1));
        $n = (int) $ini;

        return match ($unit) {
            'g' => $n * 1024 * 1024 * 1024,
            'm' => $n * 1024 * 1024,
            'k' => $n * 1024,
            default => $n,
        };
    }

    /** Nome amigável para download. */
    public static function downloadName(Document $doc): string
    {
        return Str::ascii($doc->filename());
    }
}
