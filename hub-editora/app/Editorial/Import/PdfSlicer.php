<?php

namespace App\Editorial\Import;

use Illuminate\Support\Facades\Process;

/**
 * Recorta as primeiras N páginas de um PDF para mandar ao modelo como documento
 * (o layout da capa, ficha catalográfica e sumário importa). Usa o Ghostscript do
 * servidor (/usr/bin/gs). Sem ele: PDF pequeno vai inteiro; grande vai como texto.
 */
class PdfSlicer
{
    public const MODE_PDF = 'pdf';          // recorte via Ghostscript
    public const MODE_WHOLE = 'whole_pdf';  // PDF inteiro (poucas páginas)
    public const MODE_TEXT = 'text';        // só o texto extraído

    /**
     * @return array{mode:string, path:?string} caminho de um PDF temporário (apagar depois) ou null
     */
    public function firstPages(string $path, int $pages, int $totalPages): array
    {
        if ($totalPages > 0 && $totalPages <= $pages) {
            return ['mode' => self::MODE_WHOLE, 'path' => $path];
        }

        $gs = (string) config('hub.ai.gs_bin');
        if ($gs !== '') {
            $tmp = (string) tempnam(sys_get_temp_dir(), 'hub-fm-');
            $out = $tmp.'.pdf';
            try {
                $result = Process::timeout(120)->run([
                    $gs, '-q', '-dNOPAUSE', '-dBATCH', '-dSAFER', '-sDEVICE=pdfwrite',
                    '-dFirstPage=1', '-dLastPage='.$pages, '-sOutputFile='.$out, $path,
                ]);
                if ($result->successful() && is_file($out) && filesize($out) > 100) {
                    return ['mode' => self::MODE_PDF, 'path' => $out];
                }
            } catch (\Throwable) {
                // sem Ghostscript: cai nas alternativas abaixo
            } finally {
                @unlink($tmp);
            }
            @unlink($out);
        }

        if ($totalPages <= (int) config('hub.ai.whole_pdf_max_pages', 100)) {
            return ['mode' => self::MODE_WHOLE, 'path' => $path];
        }

        return ['mode' => self::MODE_TEXT, 'path' => null];
    }
}
