<?php
/**
 * Posts de demonstração do blog, para a listagem ter paginação e a nuvem de
 * tags ter o que mostrar enquanto a editora não publica os textos reais.
 *
 *   wp eval-file /scripts/seed-posts-demo.php
 *
 * Idempotente: quem já existe (pelo título) é deixado em paz. As fotos são
 * reaproveitadas das três capas que o seed-conteudos.sh já importou.
 */

if (! defined('ABSPATH')) {
    exit;
}

$autor = (int) (get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0] ?? 1);

$capas = [];

foreach (get_posts(['post_type' => 'post', 'numberposts' => -1, 'fields' => 'ids']) as $existente) {
    $capa = (int) get_post_thumbnail_id($existente);

    if ($capa) {
        $capas[] = $capa;
    }
}

$posts = [
    ['Por que o projeto gráfico é tão importante para um livro?', 'Design Editorial', ['diagramação', 'impressão de livros'], 'Capa, tipografia, diagramação e acabamento influenciam diretamente a experiência de leitura e a percepção de qualidade de uma obra.'],
    ['Como preparar seu manuscrito antes de enviar para a editora', 'Para Autores', ['manuscrito', 'para autores', 'escrita'], 'Organização dos capítulos, padronização das citações e uma última leitura atenta poupam semanas no processo editorial.'],
    ['ISBN, ficha catalográfica e depósito legal: o que você precisa saber', 'Processo Editorial', ['publicação de livros', 'processo editorial'], 'Os registros obrigatórios de um livro parecem burocracia, mas são o que permitem que ele seja encontrado, vendido e citado.'],
    ['Revisão de texto: o que muda entre revisar e reescrever', 'Processo Editorial', ['revisão de texto', 'escrita'], 'A revisão cuida da clareza e da norma sem apagar a voz do autor. Entenda até onde vai o trabalho do revisor.'],
    ['Capa que vende: o que funciona na estante e na tela', 'Design Editorial', ['diagramação', 'publicação de livros'], 'Uma boa capa precisa funcionar em tamanho grande na livraria e em miniatura no celular. Veja o que considerar.'],
    ['Tiragem pequena ou grande? Como decidir a impressão do seu livro', 'Mercado Editorial', ['impressão de livros', 'publicação de livros'], 'Impressão digital e offset atendem a necessidades diferentes. O custo por exemplar é só uma parte da conta.'],
    ['E-book ou impresso: o que faz sentido para a sua obra', 'Mercado Editorial', ['publicação de livros', 'literatura'], 'Cada formato tem seu público e seus custos. Na maioria dos projetos, os dois se complementam.'],
    ['Direitos autorais: o que o autor precisa combinar em contrato', 'Para Autores', ['para autores', 'publicação de livros'], 'Prazo, tiragem, royalties e direitos de tradução: os pontos que merecem atenção antes da assinatura.'],
    ['Como um livro chega às livrarias e marketplaces', 'Mercado Editorial', ['publicação de livros', 'processo editorial'], 'Da distribuição à vitrine, o caminho de um título até o leitor passa por decisões comerciais que começam na edição.'],
    ['Leitura na primeira infância: por que começar cedo', 'Conhecimento & Educação', ['literatura', 'escrita'], 'O contato com livros antes da alfabetização forma vocabulário, atenção e repertório para a vida escolar.'],
    ['Livros técnicos: como organizar conteúdo denso sem cansar o leitor', 'Conhecimento & Educação', ['diagramação', 'revisão de texto'], 'Hierarquia de títulos, boxes e imagens bem escolhidas transformam um conteúdo difícil em leitura possível.'],
    ['Diagramação: o que faz uma página ser confortável de ler', 'Design Editorial', ['diagramação', 'impressão de livros'], 'Margens, entrelinha e escolha tipográfica decidem se o leitor segue em frente ou abandona o livro no terceiro capítulo.'],
];

$dia = strtotime('2026-07-10 09:00:00');
$criados = 0;

/** Categoria pelo nome: taxonomia hierárquica precisa de ID, nome é ignorado. */
$categoria_id = static function (string $nome): int {
    $termo = get_term_by('name', $nome, 'category');

    if ($termo) {
        return (int) $termo->term_id;
    }

    $novo = wp_insert_term($nome, 'category');

    return is_wp_error($novo) ? 0 : (int) $novo['term_id'];
};

foreach ($posts as $i => [$titulo, $categoria, $tags, $resumo]) {
    $existente = get_page_by_title($titulo, OBJECT, 'post');

    if ($existente) {
        // já está publicado; só garante categoria e tags
        wp_set_post_terms($existente->ID, [$categoria_id($categoria)], 'category');
        wp_set_post_terms($existente->ID, $tags, 'post_tag');
        echo "  = $titulo\n";
        continue;
    }

    $corpo = '<p>'.$resumo."</p>\n\n".
        '<p>Este é um texto de demonstração, publicado para dar volume ao blog enquanto a editora prepara os conteúdos definitivos. '.
        'Ele serve para conferir o comportamento da listagem, da paginação e das páginas internas com títulos e resumos reais.</p>'."\n\n".
        '<h2>O que considerar</h2>'."\n\n".
        '<p>Cada projeto editorial tem suas particularidades, e as decisões tomadas no início costumam aparecer no resultado final. '.
        'Fale com a nossa equipe para entender o que faz sentido para a sua obra.</p>';

    $id = wp_insert_post([
        'post_type' => 'post',
        'post_status' => 'publish',
        'post_title' => $titulo,
        'post_excerpt' => $resumo,
        'post_content' => $corpo,
        'post_author' => $autor,
        'post_date' => gmdate('Y-m-d H:i:s', $dia - ($i * DAY_IN_SECONDS * 3)),
    ], true);

    if (is_wp_error($id)) {
        echo "  ! $titulo: ".$id->get_error_message()."\n";
        continue;
    }

    wp_set_post_terms($id, [$categoria_id($categoria)], 'category');
    wp_set_post_terms($id, $tags, 'post_tag');

    if ($capas) {
        set_post_thumbnail($id, $capas[$i % count($capas)]);
    }

    $criados++;
    echo "  + $titulo\n";
}

echo "posts criados: $criados\n";
