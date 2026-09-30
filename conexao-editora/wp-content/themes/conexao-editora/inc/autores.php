<?php
/**
 * Página de autores (/autores/): lista com filtro por inicial, e o campo de
 * foto no cadastro do autor (a biografia é a descrição do próprio termo).
 */

if (! defined('ABSPATH')) {
    exit;
}

const CONEXAO_AUTORES_POR_PAGINA = 9;

/** Inicial para o filtro: sem acento, A–Z; o resto cai em "#". */
function conexao_inicial_autor(string $nome): string
{
    $letra = strtoupper(substr(remove_accents(trim($nome)), 0, 1));

    return preg_match('/^[A-Z]$/', $letra) ? $letra : '#';
}

/**
 * Autores com livro publicado, em ordem alfabética, opcionalmente só os de
 * uma inicial.
 *
 * @return array{autores: WP_Term[], iniciais: array<string, int>}
 */
function conexao_autores_lista(string $letra = ''): array
{
    $todos = get_terms([
        'taxonomy' => 'autor',
        'hide_empty' => true,
        'orderby' => 'name',
        'order' => 'ASC',
    ]);

    if (is_wp_error($todos)) {
        return ['autores' => [], 'iniciais' => []];
    }

    $iniciais = [];

    foreach ($todos as $autor) {
        $inicial = conexao_inicial_autor($autor->name);
        $iniciais[$inicial] = ($iniciais[$inicial] ?? 0) + 1;
    }

    // ordem alfabética sem tropeçar em acento ("Álvaro" junto dos "A")
    usort($todos, static fn ($a, $b) => strcasecmp(remove_accents($a->name), remove_accents($b->name)));

    if ($letra !== '') {
        $todos = array_values(array_filter($todos, static fn ($a) => conexao_inicial_autor($a->name) === $letra));
    }

    return ['autores' => $todos, 'iniciais' => $iniciais];
}

/** Livros publicados de um autor, mais recentes primeiro. */
function conexao_livros_do_autor(WP_Term $autor, int $quantos = 2): array
{
    if (! function_exists('wc_get_products')) {
        return [];
    }

    return wc_get_products([
        'status' => 'publish',
        'limit' => $quantos,
        'orderby' => 'date',
        'order' => 'DESC',
        'tax_query' => [['taxonomy' => 'autor', 'field' => 'term_id', 'terms' => $autor->term_id]],
    ]);
}

/** Foto do autor ou, sem foto, as iniciais num círculo. */
function conexao_foto_autor(WP_Term $autor, int $tamanho = 74): string
{
    $foto = (int) get_term_meta($autor->term_id, 'conexao_foto', true);

    if ($foto && wp_attachment_is_image($foto)) {
        return wp_get_attachment_image($foto, 'thumbnail', false, [
            'class' => 'autor-card__imagem',
            'alt' => $autor->name,
            'loading' => 'lazy',
            'width' => $tamanho,
            'height' => $tamanho,
        ]);
    }

    $partes = preg_split('/\s+/', trim(remove_accents($autor->name))) ?: [];
    $partes = array_values(array_filter($partes, static fn ($p) => mb_strlen($p) > 2 || count($partes) === 1));
    $iniciais = strtoupper(substr($partes[0] ?? $autor->name, 0, 1).(count($partes) > 1 ? substr(end($partes), 0, 1) : ''));

    return sprintf('<span class="autor-card__iniciais" aria-hidden="true">%s</span>', esc_html($iniciais));
}

// ---- foto no cadastro do autor (Produtos → Autores)
add_action('admin_enqueue_scripts', function (string $tela): void {
    $atual = get_current_screen();

    if (! $atual || $atual->taxonomy !== 'autor' || ! in_array($tela, ['edit-tags.php', 'term.php'], true)) {
        return;
    }

    wp_enqueue_media();
    wp_add_inline_script('media-editor', <<<'JS'
document.addEventListener('click', function (evento) {
    var escolher = evento.target.closest('[data-foto-escolher]');
    var remover = evento.target.closest('[data-foto-remover]');
    var caixa = evento.target.closest('[data-foto-autor]');

    if (!caixa || (!escolher && !remover)) {
        return;
    }

    evento.preventDefault();

    var campo = caixa.querySelector('input[type=hidden]');
    var previa = caixa.querySelector('[data-foto-previa]');

    if (remover) {
        campo.value = '';
        previa.innerHTML = '';
        return;
    }

    var janela = wp.media({ title: 'Foto do autor', button: { text: 'Usar esta foto' }, library: { type: 'image' }, multiple: false });

    janela.on('select', function () {
        var anexo = janela.state().get('selection').first().toJSON();
        var url = (anexo.sizes && anexo.sizes.thumbnail) ? anexo.sizes.thumbnail.url : anexo.url;
        campo.value = anexo.id;
        previa.innerHTML = '<img src="' + url + '" alt="" style="width:80px;height:80px;border-radius:50%;object-fit:cover">';
    });

    janela.open();
});
JS);
});

function conexao_campo_foto_autor(int $foto = 0): void
{
    $imagem = $foto ? wp_get_attachment_image($foto, 'thumbnail', false, ['style' => 'width:80px;height:80px;border-radius:50%;object-fit:cover']) : '';
    ?>
    <div data-foto-autor>
        <input type="hidden" name="conexao_foto" value="<?php echo esc_attr($foto ?: ''); ?>">
        <?php wp_nonce_field('conexao_foto_autor', 'conexao_foto_nonce'); ?>
        <div data-foto-previa style="margin-bottom:8px"><?php echo $imagem; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
        <button type="button" class="button" data-foto-escolher>Escolher foto</button>
        <button type="button" class="button-link button-link-delete" data-foto-remover>Remover</button>
        <p class="description">Aparece na página de autores, no menu e na página do livro. Sem foto, mostramos as iniciais.</p>
    </div>
    <?php
}

add_action('autor_add_form_fields', function (): void {
    echo '<div class="form-field"><label>Foto</label>';
    conexao_campo_foto_autor();
    echo '</div>';
});

add_action('autor_edit_form_fields', function (WP_Term $termo): void {
    echo '<tr class="form-field"><th scope="row"><label>Foto</label></th><td>';
    conexao_campo_foto_autor((int) get_term_meta($termo->term_id, 'conexao_foto', true));
    echo '</td></tr>';
});

$conexao_salva_foto = static function (int $termo_id): void {
    if (! isset($_POST['conexao_foto_nonce'])
        || ! wp_verify_nonce(sanitize_key(wp_unslash($_POST['conexao_foto_nonce'])), 'conexao_foto_autor')
        || ! current_user_can('manage_product_terms')) {
        return;
    }

    $foto = isset($_POST['conexao_foto']) ? absint($_POST['conexao_foto']) : 0;

    if ($foto) {
        update_term_meta($termo_id, 'conexao_foto', $foto);
    } else {
        delete_term_meta($termo_id, 'conexao_foto');
    }
};

add_action('created_autor', $conexao_salva_foto);
add_action('edited_autor', $conexao_salva_foto);

// a biografia é a "Descrição" do autor: o rótulo diz para que serve
add_action('admin_head-term.php', 'conexao_rotulo_bio_autor');
add_action('admin_head-edit-tags.php', 'conexao_rotulo_bio_autor');

function conexao_rotulo_bio_autor(): void
{
    $tela = get_current_screen();

    if ($tela && $tela->taxonomy === 'autor') {
        echo '<script>document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll(\'label[for="tag-description"],label[for="description"]\').forEach(function(l){l.textContent="Biografia"});});</script>';
    }
}
