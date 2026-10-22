<?php

namespace App\Http\Controllers;

use App\Models\Import;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Entrega o PDF privado de uma importação (storage/app/private/imports) para o
 * iframe da tela de revisão (Conexão DOI, frente E2). Só quem edita o módulo
 * Editorial (admin, editor, revisor) vê o arquivo; o login é exigido pela rota.
 *
 * `inline` para o navegador abrir no visualizador; cache privado para o
 * iframe poder ser recriado (troca de página via #page=N) sem baixar de novo.
 */
class ImportPdfController extends Controller
{
    public function __invoke(Request $request, Import $import): StreamedResponse
    {
        abort_unless((bool) $request->user()?->canEditEditorial(), 403);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($import->file_path), 404, 'O PDF desta importação não está mais no disco.');

        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $import->original_name ?: 'importacao.pdf') ?: 'importacao.pdf';

        return $disk->response($import->file_path, $name, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
