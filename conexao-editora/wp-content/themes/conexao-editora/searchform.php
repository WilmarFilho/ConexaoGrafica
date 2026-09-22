<?php
/**
 * Formulário de busca reaproveitado pelo WordPress.
 */

if (! defined('ABSPATH')) {
    exit;
}

$id = 'busca-'.wp_unique_id();
?>
<form class="busca" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
    <label class="tela-leitor" for="<?php echo esc_attr($id); ?>">Buscar no site</label>
    <input type="search" id="<?php echo esc_attr($id); ?>" name="s" value="<?php echo esc_attr(get_search_query()); ?>"
           placeholder="Busca por título, autor, ISBN ou palavra-chave">
    <button type="submit" aria-label="Buscar"><?php conexao_the_icon('lupa', 20); ?></button>
</form>
