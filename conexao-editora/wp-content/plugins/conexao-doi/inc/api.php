<?php
/**
 * API REST que o Hub Editora chama (o WordPress nunca chama o Hub):
 *
 *   PUT  /wp-json/conexao-doi/v1/publicacoes/{hub_book_id}              cria/atualiza tudo
 *   GET  /wp-json/conexao-doi/v1/publicacoes/{hub_book_id}              estado atual
 *   POST /wp-json/conexao-doi/v1/publicacoes/{hub_book_id}/despublicar  fora de catálogo
 *   POST /wp-json/conexao-doi/v1/documentos                             PDF da ficha
 *
 * Autenticação sem usuário WP: X-Conexao-Timestamp (unix) e
 * X-Conexao-Signature = hex(hmac_sha256(timestamp . "\n" . corpo, segredo)),
 * segredo em CONEXAO_DOI_SECRET no wp-config.php, janela de 5 minutos.
 */

if (! defined('ABSPATH')) {
    exit;
}

const CONEXAO_DOI_JANELA = 300; // segundos de tolerância do timestamp

add_action('rest_api_init', function (): void {
    register_rest_route('conexao-doi/v1', '/publicacoes/(?P<hub_book_id>\d+)', [
        [
            'methods' => 'PUT',
            'callback' => 'conexao_doi_api_publicar',
            'permission_callback' => 'conexao_doi_api_autoriza',
        ],
        [
            'methods' => 'GET',
            'callback' => 'conexao_doi_api_estado',
            'permission_callback' => 'conexao_doi_api_autoriza',
        ],
    ]);

    register_rest_route('conexao-doi/v1', '/publicacoes/(?P<hub_book_id>\d+)/despublicar', [
        'methods' => 'POST',
        'callback' => 'conexao_doi_api_despublicar',
        'permission_callback' => 'conexao_doi_api_autoriza',
    ]);

    register_rest_route('conexao-doi/v1', '/documentos', [
        'methods' => 'POST',
        'callback' => 'conexao_doi_api_documento',
        'permission_callback' => 'conexao_doi_api_autoriza',
    ]);
});

// ---------------------------------------------------------------- assinatura

/**
 * Confere timestamp e assinatura HMAC. No multipart o PHP não entrega o corpo
 * bruto, então o que se assina é o sha256 (hex) do arquivo enviado.
 */
function conexao_doi_api_autoriza(WP_REST_Request $request)
{
    if (! defined('CONEXAO_DOI_SECRET') || strlen((string) CONEXAO_DOI_SECRET) < 32) {
        return new WP_Error('conexao_doi_sem_segredo', 'CONEXAO_DOI_SECRET não está definido no wp-config.php.', ['status' => 503]);
    }

    $timestamp = trim((string) $request->get_header('x_conexao_timestamp'));
    $assinatura = strtolower(trim((string) $request->get_header('x_conexao_signature')));

    if ($timestamp === '' || $assinatura === '') {
        return new WP_Error('conexao_doi_sem_assinatura', 'Faltam os cabeçalhos X-Conexao-Timestamp e X-Conexao-Signature.', ['status' => 401]);
    }

    if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > CONEXAO_DOI_JANELA) {
        return new WP_Error('conexao_doi_timestamp', 'Timestamp fora da janela de 5 minutos.', ['status' => 401]);
    }

    $corpo = conexao_doi_api_corpo_assinado($request);
    $esperada = hash_hmac('sha256', $timestamp."\n".$corpo, (string) CONEXAO_DOI_SECRET);

    if (! hash_equals($esperada, $assinatura)) {
        return new WP_Error('conexao_doi_assinatura', 'Assinatura inválida.', ['status' => 401]);
    }

    return true;
}

function conexao_doi_api_corpo_assinado(WP_REST_Request $request): string
{
    $arquivos = $request->get_file_params();

    if (! empty($arquivos['file']['tmp_name']) && is_uploaded_file($arquivos['file']['tmp_name'])) {
        return (string) hash_file('sha256', $arquivos['file']['tmp_name']);
    }

    return (string) $request->get_body();
}

/** O JSON do corpo, independentemente do Content-Type que o Hub mandar. */
function conexao_doi_api_json(WP_REST_Request $request)
{
    $dados = $request->get_json_params();

    if (! is_array($dados)) {
        $dados = json_decode((string) $request->get_body(), true);
    }

    if (! is_array($dados)) {
        return new WP_Error('conexao_doi_json', 'O corpo precisa ser JSON válido.', ['status' => 400]);
    }

    return $dados;
}

// ---------------------------------------------------------------- rotas

function conexao_doi_api_publicar(WP_REST_Request $request)
{
    $payload = conexao_doi_api_json($request);

    if (is_wp_error($payload)) {
        return $payload;
    }

    $hub_id = (int) $request['hub_book_id'];

    if ((int) ($payload['book']['hub_id'] ?? 0) !== $hub_id) {
        return new WP_Error('conexao_doi_payload', 'book.hub_id não bate com o id da rota.', ['status' => 422]);
    }

    $resposta = conexao_doi_gravar_publicacao($payload);

    if (is_wp_error($resposta)) {
        return $resposta;
    }

    return new WP_REST_Response($resposta, 200);
}

function conexao_doi_api_estado(WP_REST_Request $request)
{
    $resposta = conexao_doi_estado((int) $request['hub_book_id']);

    return is_wp_error($resposta) ? $resposta : new WP_REST_Response($resposta, 200);
}

function conexao_doi_api_despublicar(WP_REST_Request $request)
{
    $resposta = conexao_doi_despublicar((int) $request['hub_book_id']);

    return is_wp_error($resposta) ? $resposta : new WP_REST_Response($resposta, 200);
}

/**
 * PDF da ficha. Dois jeitos de mandar:
 *  - multipart: campos type, hub_book_id, hub_chapter_id, version, generated_at
 *    e o arquivo em `file` (assinatura sobre o sha256 do arquivo);
 *  - JSON: os mesmos campos mais filename e content_base64 (assinatura sobre o corpo).
 * Devolve attachment_id e url e já grava o documento na página correspondente.
 */
function conexao_doi_api_documento(WP_REST_Request $request)
{
    $arquivos = $request->get_file_params();
    $campos = $request->get_params();

    if (! empty($arquivos['file']['tmp_name'])) {
        if (! empty($arquivos['file']['error'])) {
            return new WP_Error('conexao_doi_upload', 'Falha no envio do arquivo.', ['status' => 400]);
        }

        $bytes = (string) file_get_contents($arquivos['file']['tmp_name']);
        $nome = (string) ($arquivos['file']['name'] ?? 'ficha.pdf');
    } else {
        $json = conexao_doi_api_json($request);

        if (is_wp_error($json)) {
            return $json;
        }

        $campos = $json;
        $bytes = base64_decode((string) ($json['content_base64'] ?? ''), true);
        $nome = (string) ($json['filename'] ?? 'ficha.pdf');

        if ($bytes === false || $bytes === '') {
            return new WP_Error('conexao_doi_upload', 'content_base64 vazio ou inválido.', ['status' => 400]);
        }
    }

    $tipo = (string) ($campos['type'] ?? '');

    if (! in_array($tipo, ['book_sheet', 'chapter_sheet'], true)) {
        return new WP_Error('conexao_doi_upload', 'type precisa ser book_sheet ou chapter_sheet.', ['status' => 422]);
    }

    if (! str_starts_with($bytes, '%PDF-')) {
        return new WP_Error('conexao_doi_upload', 'Só PDF é aceito.', ['status' => 415]);
    }

    $hub_book_id = (int) ($campos['hub_book_id'] ?? 0);
    $hub_chapter_id = (int) ($campos['hub_chapter_id'] ?? 0);
    $alvo = $tipo === 'chapter_sheet'
        ? conexao_doi_post_por_hub_id('capitulo', $hub_chapter_id)
        : conexao_doi_post_por_hub_id('publicacao', $hub_book_id);

    $anexo = conexao_doi_guardar_pdf($bytes, $nome, $alvo ? $alvo->ID : 0);

    if (is_wp_error($anexo)) {
        return $anexo;
    }

    $documento = [
        'type' => $tipo,
        'attachment_id' => $anexo,
        'url' => wp_get_attachment_url($anexo),
        'version' => $campos['version'] ?? null,
        'generated_at' => $campos['generated_at'] ?? null,
    ];

    if ($alvo) {
        conexao_doi_registrar_documento($alvo, $documento);
    }

    return new WP_REST_Response($documento + ['wp_post_id' => $alvo ? $alvo->ID : null], 200);
}

/** Grava o PDF na biblioteca de mídia e devolve o id do anexo. */
function conexao_doi_guardar_pdf(string $bytes, string $nome, int $post_id)
{
    require_once ABSPATH.'wp-admin/includes/file.php';
    require_once ABSPATH.'wp-admin/includes/image.php';
    require_once ABSPATH.'wp-admin/includes/media.php';

    $nome = sanitize_file_name($nome);

    if (! str_ends_with(strtolower($nome), '.pdf')) {
        $nome .= '.pdf';
    }

    $gravado = wp_upload_bits($nome, null, $bytes);

    if (! empty($gravado['error'])) {
        return new WP_Error('conexao_doi_upload', (string) $gravado['error'], ['status' => 500]);
    }

    $anexo = wp_insert_attachment([
        'post_mime_type' => 'application/pdf',
        'post_title' => preg_replace('/\.pdf$/i', '', $nome),
        'post_content' => '',
        'post_status' => 'inherit',
    ], $gravado['file'], $post_id, true);

    if (is_wp_error($anexo)) {
        return $anexo;
    }

    wp_update_attachment_metadata($anexo, wp_generate_attachment_metadata($anexo, $gravado['file']));

    return (int) $anexo;
}

/**
 * O documento novo substitui o anterior do mesmo tipo nos dados da página (e
 * na cópia do capítulo que fica no livro). O anexo antigo é apagado.
 */
function conexao_doi_registrar_documento(WP_Post $alvo, array $documento): void
{
    $troca = static function (array $documentos) use ($documento): array {
        $lista = [];

        foreach ($documentos as $existente) {
            if (! is_array($existente) || ($existente['type'] ?? '') !== $documento['type']) {
                $lista[] = $existente;
                continue;
            }

            $antigo = (int) ($existente['attachment_id'] ?? 0);

            if ($antigo && $antigo !== $documento['attachment_id'] && get_post_type($antigo) === 'attachment') {
                wp_delete_attachment($antigo, true);
            }
        }

        $lista[] = $documento;

        return $lista;
    };

    $json = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    if ($alvo->post_type === 'publicacao') {
        $dados = conexao_doi_dados_livro($alvo->ID);
        $dados['book']['documents'] = $troca($dados['book']['documents'] ?? []);
        update_post_meta($alvo->ID, CONEXAO_DOI_META_DADOS, wp_slash(wp_json_encode($dados, $json)));

        return;
    }

    $capitulo = conexao_doi_capitulo($alvo->ID);
    $capitulo['documents'] = $troca($capitulo['documents'] ?? []);
    update_post_meta($alvo->ID, CONEXAO_DOI_META_DADOS, wp_slash(wp_json_encode($capitulo, $json)));

    $livro = conexao_doi_livro_do_capitulo($alvo);

    if ($livro) {
        $dados = conexao_doi_dados_livro($livro->ID);

        foreach ($dados['chapters'] ?? [] as $i => $c) {
            if ((int) ($c['hub_id'] ?? 0) === (int) ($capitulo['hub_id'] ?? -1)) {
                $dados['chapters'][$i]['documents'] = $capitulo['documents'];
            }
        }

        update_post_meta($livro->ID, CONEXAO_DOI_META_DADOS, wp_slash(wp_json_encode($dados, $json)));
    }
}
