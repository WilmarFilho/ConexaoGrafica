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

    $combo = isset($_POST['conexao_livro_combo']) ? sanitize_text_field(wp_unslash($_POST['conexao_livro_combo'])) : '';
    $ids = array_values(array_unique(array_filter(array_map('absint', preg_split('/[\s,;]+/', $combo)))));

    if ($ids) {
        update_post_meta($post_id, CONEXAO_LIVRO_COMBO, $ids);
    } else {
        delete_post_meta($post_id, CONEXAO_LIVRO_COMBO);
    }
});
