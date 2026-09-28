<?php
/**
 * Envio do PDF pelo painel. O arquivo sobe em pedaços, porque um livro passa
 * fácil do limite de upload do PHP; cada pedaço cabe no limite do servidor.
 */

if (! defined('ABSPATH')) {
    exit;
}

/** Maior pedaço que o servidor aceita, com folga para os outros campos. */
function conexao_biblioteca_tamanho_pedaco(): int
{
    $limite = min(
        wp_convert_hr_to_bytes((string) ini_get('upload_max_filesize')),
        wp_convert_hr_to_bytes((string) ini_get('post_max_size'))
    );

    $pedaco = (int) floor($limite * 0.75);

    return max(256 * KB_IN_BYTES, min($pedaco, 8 * MB_IN_BYTES));
}

function conexao_biblioteca_tamanho_maximo(): int
{
    return (int) apply_filters('conexao_biblioteca_tamanho_maximo', 800 * MB_IN_BYTES);
}

/** Campo de envio, usado na tela do produto e na lista da biblioteca. */
function conexao_biblioteca_campo_envio(int $produto_id): void
{
    $dados = (array) get_post_meta($produto_id, CONEXAO_LIVRO_PDF_DADOS, true);
    $livro = conexao_biblioteca_livro($produto_id);
    ?>
    <div class="cb-envio" data-cb-envio data-produto="<?php echo esc_attr((string) $produto_id); ?>">
        <?php if ($livro['pdf']) : ?>
            <p class="cb-envio__atual">
                <strong><?php echo esc_html($dados['nome'] ?? basename($livro['pdf'])); ?></strong><br>
                <?php echo esc_html(size_format((int) filesize($livro['pdf']))); ?>
                <?php if (! empty($dados['data'])) : ?>
                    · enviado em <?php echo esc_html(wp_date('d/m/Y H:i', strtotime((string) $dados['data']))); ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <input type="file" accept="application/pdf,.pdf" data-cb-arquivo hidden>

        <p class="cb-envio__acoes">
            <button type="button" class="button button-primary" data-cb-escolher>
                <?php echo $livro['pdf'] ? 'Substituir PDF' : 'Enviar PDF'; ?>
            </button>

            <?php if ($livro['pdf']) : ?>
                <button type="button" class="button-link button-link-delete" data-cb-remover>Remover PDF</button>
            <?php endif; ?>
        </p>

        <div class="cb-envio__barra" hidden data-cb-barra><span data-cb-progresso></span></div>
        <p class="cb-envio__estado" data-cb-estado role="status"></p>
    </div>
    <?php
}

add_action('admin_enqueue_scripts', function (): void {
    $tela = get_current_screen();

    if (! $tela || ! in_array($tela->id, ['product', 'product_page_conexao-biblioteca'], true)) {
        return;
    }

    wp_enqueue_style('conexao-biblioteca-admin', CONEXAO_BIBLIOTECA_URL.'assets/admin.css', [], CONEXAO_BIBLIOTECA_VERSAO);
    wp_enqueue_script('conexao-biblioteca-admin', CONEXAO_BIBLIOTECA_URL.'assets/admin.js', [], CONEXAO_BIBLIOTECA_VERSAO, true);
    wp_localize_script('conexao-biblioteca-admin', 'conexaoBiblioteca', [
        'ajax' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('conexao_biblioteca_envio'),
        'pedaco' => conexao_biblioteca_tamanho_pedaco(),
        'maximo' => conexao_biblioteca_tamanho_maximo(),
    ]);
});

add_action('wp_ajax_conexao_biblioteca_envio', function (): void {
    check_ajax_referer('conexao_biblioteca_envio', 'nonce');

    $produto_id = isset($_POST['produto']) ? absint($_POST['produto']) : 0;
    $envio = isset($_POST['envio']) ? preg_replace('/[^a-z0-9]/', '', strtolower((string) wp_unslash($_POST['envio']))) : '';
    $indice = isset($_POST['indice']) ? absint($_POST['indice']) : 0;
    $total = isset($_POST['total']) ? absint($_POST['total']) : 0;
    $nome = isset($_POST['nome']) ? sanitize_file_name((string) wp_unslash($_POST['nome'])) : 'livro.pdf';

    if (! $produto_id || get_post_type($produto_id) !== 'product' || ! current_user_can('edit_post', $produto_id)) {
        wp_send_json_error(['mensagem' => 'Sem permissão para este produto.'], 403);
    }

    if (strlen($envio) < 8 || ! $total || $indice >= $total) {
        wp_send_json_error(['mensagem' => 'Envio inválido.'], 400);
    }

    $pedaco = $_FILES['pedaco'] ?? null;

    if (! $pedaco || (int) $pedaco['error'] !== UPLOAD_ERR_OK || ! is_uploaded_file($pedaco['tmp_name'])) {
        wp_send_json_error(['mensagem' => 'O servidor não recebeu o pedaço do arquivo.'], 400);
    }

    conexao_biblioteca_prepara_pasta();

    $parcial = conexao_biblioteca_subpasta('envio')."/{$produto_id}-{$envio}.parte";
    $controle = $parcial.'.json';
    $estado = file_exists($controle) ? (array) json_decode((string) file_get_contents($controle), true) : ['proximo' => 0];

    if ($indice === 0) {
        @unlink($parcial);
        $estado = ['proximo' => 0];

        // um PDF de verdade começa com %PDF-
        $cabeca = (string) file_get_contents($pedaco['tmp_name'], false, null, 0, 5);

        if ($cabeca !== '%PDF-') {
            wp_send_json_error(['mensagem' => 'O arquivo não é um PDF.'], 415);
        }
    }

    if ((int) $estado['proximo'] !== $indice) {
        wp_send_json_error(['mensagem' => 'Os pedaços chegaram fora de ordem. Envie de novo.'], 409);
    }

    $destino = fopen($parcial, $indice === 0 ? 'wb' : 'ab');
    $origem = fopen($pedaco['tmp_name'], 'rb');

    if (! $destino || ! $origem) {
        wp_send_json_error(['mensagem' => 'Não consegui gravar na pasta da biblioteca.'], 500);
    }

    stream_copy_to_stream($origem, $destino);
    fclose($origem);
    fclose($destino);
    clearstatcache(true, $parcial);

    if (filesize($parcial) > conexao_biblioteca_tamanho_maximo()) {
        @unlink($parcial);
        @unlink($controle);
        wp_send_json_error(['mensagem' => 'O arquivo passa do tamanho máximo permitido.'], 413);
    }

    if ($indice + 1 < $total) {
        file_put_contents($controle, wp_json_encode(['proximo' => $indice + 1]));
        wp_send_json_success(['recebido' => $indice + 1, 'total' => $total]);
    }

    // último pedaço: o arquivo passa a valer
    @unlink($controle);

    $relativo = sprintf('%d-%s.pdf', $produto_id, wp_generate_password(10, false));
    $final = conexao_biblioteca_subpasta('pdf').'/'.$relativo;

    if (! @rename($parcial, $final)) {
        wp_send_json_error(['mensagem' => 'Não consegui finalizar o arquivo.'], 500);
    }

    $antigo = (string) get_post_meta($produto_id, CONEXAO_LIVRO_PDF, true);

    update_post_meta($produto_id, CONEXAO_LIVRO_PDF, $relativo);
    update_post_meta($produto_id, CONEXAO_LIVRO_PDF_DADOS, [
        'nome' => $nome,
        'tamanho' => (int) filesize($final),
        'data' => current_time('mysql'),
        'usuario' => get_current_user_id(),
    ]);

    if ($antigo && $antigo !== $relativo) {
        $caminho_antigo = conexao_biblioteca_caminho_seguro(conexao_biblioteca_subpasta('pdf'), $antigo);

        if ($caminho_antigo) {
            @unlink($caminho_antigo);
        }
    }

    wp_send_json_success([
        'concluido' => true,
        'nome' => $nome,
        'tamanho' => size_format((int) filesize($final)),
    ]);
});

add_action('wp_ajax_conexao_biblioteca_remove_pdf', function (): void {
    check_ajax_referer('conexao_biblioteca_envio', 'nonce');

    $produto_id = isset($_POST['produto']) ? absint($_POST['produto']) : 0;

    if (! $produto_id || ! current_user_can('edit_post', $produto_id)) {
        wp_send_json_error(['mensagem' => 'Sem permissão para este produto.'], 403);
    }

    $atual = (string) get_post_meta($produto_id, CONEXAO_LIVRO_PDF, true);
    $caminho = $atual ? conexao_biblioteca_caminho_seguro(conexao_biblioteca_subpasta('pdf'), $atual) : '';

    if ($caminho) {
        @unlink($caminho);
    }

    delete_post_meta($produto_id, CONEXAO_LIVRO_PDF);
    delete_post_meta($produto_id, CONEXAO_LIVRO_PDF_DADOS);

    wp_send_json_success(['removido' => true]);
});
