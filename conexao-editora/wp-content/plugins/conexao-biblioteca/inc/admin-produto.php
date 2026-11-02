<?php
/**
 * Caixa "Livro digital" na tela do produto: o modelo em uso, o envio do PDF e
 * os campos do legado (pasta do flipbook e livros de uma coleção).
 */

if (! defined('ABSPATH')) {
    exit;
}

add_action('add_meta_boxes', function (): void {
    add_meta_box(
        'conexao-biblioteca',
        'Livro digital',
        'conexao_biblioteca_caixa_produto',
        'product',
        'side',
        'default'
    );
});

function conexao_biblioteca_rotulo_modelo(string $modelo): string
{
    return match ($modelo) {
        'pdf' => '<span class="cb-modelo cb-modelo--pdf">PDF (modelo novo)</span>',
        'flipbook' => '<span class="cb-modelo cb-modelo--flipbook">Flipbook (legado)</span>',
        default => '<span class="cb-modelo cb-modelo--vazio">Sem arquivo</span>',
    };
}

function conexao_biblioteca_caixa_produto(WP_Post $post): void
{
    $livro = conexao_biblioteca_livro($post->ID);
    $pasta = (string) get_post_meta($post->ID, CONEXAO_LIVRO_FLIPBOOK, true);
    $combo = implode(', ', conexao_biblioteca_combo($post->ID));

    wp_nonce_field('conexao_biblioteca_produto', 'conexao_biblioteca_nonce');
    ?>
    <p>
        Leitura hoje: <?php echo conexao_biblioteca_rotulo_modelo($livro['modelo']); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    </p>

    <?php if ($livro['modelo']) : ?>
        <p><a href="<?php echo esc_url(conexao_biblioteca_url_leitor($post->ID)); ?>" target="_blank" rel="noopener">Abrir no leitor</a></p>
    <?php endif; ?>

    <?php if ($post->post_status === 'auto-draft') : ?>
        <p class="description">Salve o produto para enviar o PDF.</p>
    <?php else : ?>
        <?php conexao_biblioteca_campo_envio($post->ID); ?>
    <?php endif; ?>

    <p class="cb-campo">
        <label for="conexao-livro-flipbook">Pasta do flipbook (legado)</label>
        <input type="text" id="conexao-livro-flipbook" name="conexao_livro_flipbook" value="<?php echo esc_attr($pasta); ?>">
        <span class="description">Usada enquanto o livro não tem PDF. Com PDF enviado, vale o PDF.</span>
    </p>

    <p class="cb-campo">
        <label for="conexao-livro-sumario">Sumário do leitor</label>
        <?php $do_hub = function_exists('conexao_biblioteca_capitulos_do_hub') ? conexao_biblioteca_capitulos_do_hub($post->ID) : []; ?>
        <?php if ($do_hub && $livro['pdf'] && ! conexao_biblioteca_sumario_digitado($post->ID)) : ?>
            <?php $deslocamento = conexao_biblioteca_deslocamento($post->ID); ?>
            <span class="description" style="display:block;margin-bottom:6px">
                <?php if ($deslocamento !== null) : ?>
                    Usando o sumário do Hub (<?php echo count($do_hub); ?> capítulos), com <?php echo (int) $deslocamento; ?> página(s) de diferença entre o PDF e o livro impresso. Só preencha abaixo para trocar por outro.
                <?php else : ?>
                    O livro tem <?php echo count($do_hub); ?> capítulos no Hub, mas não achei no PDF onde começa o capítulo 1. Informe a diferença de páginas abaixo ou digite o sumário.
                <?php endif; ?>
            </span>
        <?php endif; ?>
        <textarea id="conexao-livro-sumario" name="conexao_livro_sumario" rows="8" style="width:100%;font-family:monospace;font-size:12px" placeholder="1 | Capa&#10;5 | Sumário&#10;9 | Capítulo 1 - Introdução"><?php echo esc_textarea(conexao_biblioteca_sumario_para_texto(conexao_biblioteca_sumario_digitado($post->ID))); ?></textarea>
        <span class="description">Uma linha por item: <code>página | título</code>, com o número da página <strong>do PDF</strong> (a que o leitor mostra). Usado quando o PDF não traz marcadores próprios.</span>
    </p>

    <?php if ($do_hub) : ?>
        <p class="cb-campo">
            <?php $guardado = get_post_meta($post->ID, CONEXAO_LIVRO_DESLOCAMENTO, true); ?>
            <label for="conexao-livro-deslocamento">Diferença de páginas (PDF − impresso)</label>
            <input type="number" id="conexao-livro-deslocamento" name="conexao_livro_deslocamento" step="1" min="-50" max="200"
                   value="<?php echo is_array($guardado) && ! empty($guardado['manual']) ? (int) $guardado['valor'] : ''; ?>" placeholder="automático">
            <span class="description">Vazio: o sistema acha sozinho no PDF. Ex.: se a página impressa 1 é a página 9 do PDF, a diferença é 8.</span>
        </p>
    <?php endif; ?>

    <p class="cb-campo">
        <label for="conexao-livro-combo">Coleção: libera também</label>
        <input type="text" id="conexao-livro-combo" name="conexao_livro_combo" value="<?php echo esc_attr($combo); ?>" placeholder="IDs separados por vírgula">
        <span class="description">Quem compra este produto passa a ler os livros listados.</span>
    </p>
    <?php
}

add_action('save_post_product', function (int $post_id): void {
    if (! isset($_POST['conexao_biblioteca_nonce'])
        || ! wp_verify_nonce(sanitize_key(wp_unslash($_POST['conexao_biblioteca_nonce'])), 'conexao_biblioteca_produto')
        || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        || ! current_user_can('edit_post', $post_id)) {
        return;
    }

    $pasta = isset($_POST['conexao_livro_flipbook']) ? sanitize_text_field(wp_unslash($_POST['conexao_livro_flipbook'])) : '';
    $pasta = preg_replace('/[^A-Za-z0-9._-]/', '', $pasta);

    if ($pasta !== '') {
        update_post_meta($post_id, CONEXAO_LIVRO_FLIPBOOK, $pasta);
    } else {
        delete_post_meta($post_id, CONEXAO_LIVRO_FLIPBOOK);
    }

    // diferença de páginas: número = manual; vazio = volta para o automático (refeito ao salvar)
    if (isset($_POST['conexao_livro_deslocamento'])) {
        $informado = trim((string) wp_unslash($_POST['conexao_livro_deslocamento']));
        $guardado = get_post_meta($post_id, CONEXAO_LIVRO_DESLOCAMENTO, true);

        if ($informado !== '' && is_numeric($informado)) {
            update_post_meta($post_id, CONEXAO_LIVRO_DESLOCAMENTO, ['arquivo' => '', 'valor' => (int) $informado, 'manual' => true]);
        } elseif (is_array($guardado) && ! empty($guardado['manual'])) {
            delete_post_meta($post_id, CONEXAO_LIVRO_DESLOCAMENTO);
        }
    }

    $sumario = isset($_POST['conexao_livro_sumario']) ? conexao_biblioteca_sumario_do_texto((string) wp_unslash($_POST['conexao_livro_sumario'])) : [];

    if ($sumario) {
        update_post_meta($post_id, CONEXAO_LIVRO_SUMARIO, $sumario);
    } else {
        delete_post_meta($post_id, CONEXAO_LIVRO_SUMARIO);
    }

    $combo = isset($_POST['conexao_livro_combo']) ? sanitize_text_field(wp_unslash($_POST['conexao_livro_combo'])) : '';
    $ids = array_values(array_unique(array_filter(array_map('absint', preg_split('/[\s,;]+/', $combo)))));

    if ($ids) {
        update_post_meta($post_id, CONEXAO_LIVRO_COMBO, $ids);
    } else {
        delete_post_meta($post_id, CONEXAO_LIVRO_COMBO);
    }
});
