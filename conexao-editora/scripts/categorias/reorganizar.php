<?php
/**
 * Reorganização das categorias da loja (relatório "Reorganização das categorias
 * da loja", exportação de 09/10/2026).
 *
 * - cria a árvore nova (categoria principal › subcategoria), reaproveitando os
 *   termos que já existem com o mesmo assunto para manter os endereços;
 * - põe cada livro na subcategoria e na categoria principal (só categorias:
 *   preço, estoque, SKU, imagens e variações não são tocados);
 * - passa CBO e SBUS das categorias antigas para a taxonomia de coleções;
 * - refaz o menu "Categorias" com as categorias principais.
 *
 * Os IDs são os da loja em produção. Se o ID não bate com o título (banco local),
 * o livro é procurado pelo título. As categorias de antes ficam guardadas na
 * opção conexao_categorias_antes, para conferência ou volta.
 *
 * Uso: wp eval-file reorganizar.php            (aplica)
 *      CONEXAO_SIMULAR=1 wp eval-file ...     (só mostra o que faria)
 *      CONEXAO_PARCIAL=1 wp eval-file ...     (banco de teste: aplica aos que achar)
 */

if (! defined('WP_CLI')) {
    exit;
}

$simular = (bool) getenv('CONEXAO_SIMULAR');

/** slug => [nome, descrição, [subcategorias]] */
$arvore = [
    'medicina' => ['Medicina', 'Livros de referência para estudantes e profissionais das especialidades médicas.', [
        'ginecologia-e-obstetricia' => ['Ginecologia e Obstetrícia', 'Fertilidade, perinatologia, medicina fetal e ultrassonografia ginecológica.'],
        'ultrassonografia' => ['Ultrassonografia', 'Técnicas, diretrizes e laudos em ultrassonografia.'],
        'oftalmologia' => ['Oftalmologia', 'Da Série Oftalmologia CBO aos manuais de condutas em oculoplástica.'],
        'mastologia' => ['Mastologia', 'Diagnóstico, tratamento e ultrassonografia mamária.'],
        'dermatologia' => ['Dermatologia', 'Ultrassonografia aplicada à dermatologia e à cosmiatria.'],
        'genetica-medica' => ['Genética Médica', 'Doenças raras e genética clínica.'],
    ]],
    'historia' => ['História', 'A memória de instituições, profissões e municípios.', [
        'historia-da-medicina-e-das-instituicoes' => ['História da Medicina e das Instituições', 'A trajetória de entidades médicas, hospitais e faculdades.'],
        'historia-de-goias' => ['História de Goiás', 'Municípios, personagens e memórias goianas.'],
    ]],
    'literatura' => ['Literatura', 'Biografias, memórias e crônicas.', [
        'biografia' => ['Biografias', 'Histórias de vida e memórias.'],
        'cronica' => ['Crônicas', 'Textos curtos sobre o cotidiano.'],
    ]],
    'educacao' => ['Educação', 'Livros sobre formação, família e aprendizagem.', [
        'educacao-familiar' => ['Educação Familiar', 'Vínculos entre pais e filhos e a vida em família.'],
    ]],
    'religiao' => ['Religião e Espiritualidade', 'Fé, espiritualidade e autoconhecimento.', [
        'fe' => ['Fé e Espiritualidade', 'Reflexões sobre fé e propósito.'],
    ]],
    'direito' => ['Direito, Política e Sociedade', 'Instituições, política e sociedade brasileira.', [
        'politica' => ['Política e Sociedade', 'Análises sobre poder, instituições e o país.'],
    ]],
];

/** subcategoria => [ID na produção => título] */
$livros = [
    'historia-da-medicina-e-das-instituicoes' => [
        43 => 'A História da Associação Médica de Goiás', 45 => 'A História do Cremego', 53 => 'A História da Faculdade de Medicina da UFG',
        55 => 'A História da SBUS 30', 58 => 'História do Hospital e Maternidade Dona Íris', 210 => 'A História da SBUS 30 anos',
        222 => 'A História do Hospital e Maternidade Dona Íris', 226 => 'Residência Médica no Brasil - A História da Cerem-Goiás',
        228 => 'A História do Cremego - Segunda Edição', 230 => 'A Covid-19 e o Cremego', 234 => 'A História da Faculdade de Medicina UFG',
    ],
    'historia-de-goias' => [
        47 => 'Traços e Histórias de Goiás: Montividiu – Volume 1', 243 => 'Escrevendo a História dos Municípios Goianos - Volume 1',
        245 => 'Escrevendo a História dos Municípios Goianos - Volume 2', 255 => 'Escrevendo a História dos Municípios Goianos - Volume 3',
        257 => 'Escrevendo a História dos Municípios Goianos - Volume 4', 259 => 'Escrevendo a História dos Municípios Goianos - Volume 5',
    ],
    'fe' => [44 => 'LEMBRE-SE DE QUEM VOCÊ É'],
    'politica' => [
        46 => 'O Vácuo do Poder: Colapso Institucional e Ascensão do Crime Organizado no Brasil',
        251 => 'A Esquerdopatia na Medicina, na Vida Geral e em Particular',
    ],
    'ginecologia-e-obstetricia' => [
        48 => 'Em Busca da Fertilidade', 50 => 'Manual de Perinatologia', 133 => 'Atlas Multimídia de Anomalias Fetais',
        218 => 'Tratado de Ultrassonografia em Ginecologia',
    ],
    'ultrassonografia' => [
        49 => 'Ultrassonografia Pediátrica', 127 => 'Ultrassonografia do I Trimestre Pirâmide de Oportunidades',
        129 => 'Diretrizes Para Laudos de Ultrassonografia', 131 => 'Sistematização dos Exames e Laudos em Ultrassonografia',
        141 => 'Ultrassonografia de Tireoide', 249 => 'Atualidades em Ultrassonografia: Da Propedêutica ao Relatório',
        266 => 'Do Achado ao Laudo: Estratégias de Diagnóstico por Imagem em Endometriose',
    ],
    'oftalmologia' => [
        95 => 'Oculoplástica e Oncologia Ocular', 99 => 'Teleoftalmologia, Telemedicina e Inovação',
        109 => '1º Manual de Condutas em Blefaroplastia', 111 => '2º Manual de Condutas em Ptose Palpebral',
        113 => '3º Manual de Condutas - Urgências em Oculoplástica', 147 => 'Saúde Pública Ocular: Assistência Primária e Ensino',
        151 => 'Série Oftalmologia CBO', 153 => 'Série CBO - 01 Anatomia Fisiologia e Farmacologia Ocular',
        157 => 'Série CBO - 02 Semiologia Básica em Oftalmologia', 161 => 'Série CBO – 03 Embriologia e Genética Ocular',
        165 => 'Série CBO – 04 Órbita, Sistema Lacrimal e Oculoplástica', 169 => 'Série CBO – 05 Doenças Externas Oculares e Córnea',
        173 => 'Série o CBO – 06 Cristalino e Catarata', 175 => 'Série CBO – 06 Cristalino e Catarata',
        177 => 'Série CBO - 07 Retina e Vítreo', 181 => 'Série CBO – 08 Oftalmologia Pediátrica e Estrabismo',
        185 => 'Série CBO – 09 Refratometria e Visão Subnormal', 189 => 'Série CBO – 10 Lentes de Contato',
        193 => 'Série CBO – 11 Cirurgia Refrativa', 197 => 'Série CBO 12 – Glaucoma', 201 => 'Série CBO 13 - Uveítes',
        205 => 'Série CBO – 14 Neuroftalmologia', 214 => 'Série CBO 15 – Tumores e Patologia Ocular',
        241 => '4º Manual de Condutas Doenças da Órbita', 272 => 'Neuro-oftalmologia', 274 => 'Fundamentos da Oftalmologia: Uma Revisão Geral',
    ],
    'mastologia' => [
        97 => 'Mastologia do Diagnóstico ao Tratamento',
        135 => 'E-Book Ultrassonografia Mamária: Dos conceitos de Mastologia e Oncologia à Prática Clínica e Intervencionalista',
        137 => 'Ultrassonografia Mamária: Dos conceitos de Mastologia e Oncologia', 145 => 'Mastologia: Do Diagnóstico ao Tratamento - 1ª Edição',
    ],
    'dermatologia' => [123 => 'Ultrassonografia Aplicada à Dermatologia e à Cosmiatria'],
    'genetica-medica' => [268 => 'Tratado de Doenças Raras'],
    'educacao-familiar' => [51 => 'Vínculos Parentais e o Fluxo da Vida'],
    'biografia' => [52 => 'Porfia Memórias de Um Médico Oncologista'],
    'cronica' => [107 => 'Questão de Tempo, Questão de Amor'],
];

/** Coleções a partir das categorias antigas (antes da troca). */
$colecoes = [
    'cbo' => ['Conselho Brasileiro de Oftalmologia (CBO)', ['livro-da-cbo', 'ebook-da-cbo']],
    'sbus' => ['Sociedade Brasileira de Ultrassonografia (SBUS)', ['livro-da-sbus', 'ebook-sbus']],
];

$normaliza = static fn (string $t): string => trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower(remove_accents(html_entity_decode($t)))));

// 1. localizar os livros
$porTitulo = [];
foreach (get_posts(['post_type' => 'product', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids']) as $pid) {
    $porTitulo[$normaliza(get_the_title($pid))][] = $pid;
}

$plano = []; // id do produto => subcategoria
$problemas = [];
foreach ($livros as $sub => $lista) {
    foreach ($lista as $id => $titulo) {
        $alvo = null;

        if (get_post_type($id) === 'product' && $normaliza(get_the_title($id)) === $normaliza($titulo)) {
            $alvo = $id;
        } elseif (count($porTitulo[$normaliza($titulo)] ?? []) === 1) {
            $alvo = $porTitulo[$normaliza($titulo)][0];
        } elseif (get_post_type($id) === 'product' && ! isset($porTitulo[$normaliza($titulo)])) {
            // título mudou na loja, mas o ID é de um produto: vale o ID
            $alvo = $id;
            WP_CLI::warning("{$id}: título na loja é \"".get_the_title($id)."\" (relatório: \"{$titulo}\"); usando o ID");
        }

        if (! $alvo) {
            $problemas[] = "{$id} {$titulo}";
            continue;
        }

        if (isset($plano[$alvo])) {
            $problemas[] = "{$alvo} aparece duas vezes ({$plano[$alvo]} e {$sub})";
        }

        $plano[$alvo] = $sub;
    }
}

if ($problemas && ! getenv('CONEXAO_PARCIAL')) {
    WP_CLI::error("livros não localizados ou repetidos; nada alterado:\n  ".implode("\n  ", $problemas));
}

// CONEXAO_PARCIAL=1: só para testar o tema num banco que não é cópia da loja
if ($problemas) {
    WP_CLI::warning('modo parcial, ignorando: '.implode('; ', $problemas));
}

$principais = wc_get_products(['limit' => -1, 'status' => ['publish', 'private', 'draft', 'pending'], 'return' => 'ids', 'type' => ['simple', 'variable', 'external', 'grouped']]);
$fora = array_diff($principais, array_keys($plano));
WP_CLI::log(sprintf('livros localizados: %d de %d produtos principais', count($plano), count($principais)));
if ($fora) {
    WP_CLI::warning('produtos fora do relatório (não alterados): '.implode(', ', array_map(fn ($i) => $i.' '.get_the_title($i), $fora)));
}

if ($simular) {
    foreach ($plano as $pid => $sub) {
        WP_CLI::log("  {$pid} ".get_the_title($pid)." → {$sub}");
    }
    WP_CLI::success('simulação: nada foi alterado');
    return;
}

// 2. guardar as categorias de antes (só na primeira vez)
if (! get_option('conexao_categorias_antes')) {
    $antes = [];
    foreach ($principais as $pid) {
        $antes[$pid] = wp_get_post_terms($pid, 'product_cat', ['fields' => 'slugs']);
    }
    update_option('conexao_categorias_antes', $antes, false);
    WP_CLI::log('categorias de antes guardadas em conexao_categorias_antes');
}
$antes = get_option('conexao_categorias_antes');

// 3. árvore de categorias
$termo = static function (string $slug, string $nome, string $descricao, int $pai) {
    $existente = get_term_by('slug', $slug, 'product_cat');

    if ($existente) {
        wp_update_term($existente->term_id, 'product_cat', ['name' => $nome, 'description' => $descricao, 'parent' => $pai]);

        return (int) $existente->term_id;
    }

    $novo = wp_insert_term($nome, 'product_cat', ['slug' => $slug, 'description' => $descricao, 'parent' => $pai]);

    if (is_wp_error($novo)) {
        WP_CLI::error("categoria {$slug}: ".$novo->get_error_message());
    }

    return (int) $novo['term_id'];
};

$ids = [];
$paiDe = [];
$ordem = 0;
foreach ($arvore as $slug => [$nome, $descricao, $filhas]) {
    $ids[$slug] = $termo($slug, $nome, $descricao, 0);
    update_term_meta($ids[$slug], 'order', $ordem++);

    foreach ($filhas as $sub => [$nomeSub, $descSub]) {
        $ids[$sub] = $termo($sub, $nomeSub, $descSub, $ids[$slug]);
        update_term_meta($ids[$sub], 'order', $ordem++);
        $paiDe[$sub] = $slug;
    }
}
WP_CLI::log('árvore de categorias pronta: '.count($ids).' termos');

// 4. livros: subcategoria + categoria principal, no lugar das categorias antigas
foreach ($plano as $pid => $sub) {
    wp_set_object_terms($pid, [$ids[$sub], $ids[$paiDe[$sub]]], 'product_cat', false);
}
WP_CLI::log('categorias dos livros trocadas: '.count($plano));

// 5. coleções
foreach ($colecoes as $slug => [$nome, $antigas]) {
    $col = get_term_by('slug', $slug, 'colecao');
    $colId = $col ? (int) $col->term_id : (int) (wp_insert_term($nome, 'colecao', ['slug' => $slug])['term_id'] ?? 0);

    if (! $colId) {
        WP_CLI::error("coleção {$slug} não criada");
    }

    $n = 0;
    foreach ($antes as $pid => $slugsAntes) {
        if (array_intersect($slugsAntes, $antigas)) {
            wp_set_object_terms((int) $pid, [$colId], 'colecao', true);
            $n++;
        }
    }
    WP_CLI::log("coleção {$nome}: {$n} livros");
}

// 6. menu "Categorias": as categorias principais e o "Todos os livros"
$locais = get_nav_menu_locations();
$menu = $locais['principal'] ?? 0;
$itens = $menu ? (wp_get_nav_menu_items($menu) ?: []) : [];
$categorias = null;
foreach ($itens as $it) {
    if ((int) $it->menu_item_parent === 0 && trim(wp_strip_all_tags($it->title)) === 'Categorias') {
        $categorias = $it;
    }
}

if (! $categorias) {
    WP_CLI::warning('item "Categorias" não encontrado no menu principal; menu não alterado');
} else {
    $todos = null;
    foreach ($itens as $it) {
        if ((int) $it->menu_item_parent !== (int) $categorias->ID) {
            continue;
        }

        if ($it->object === 'product_cat') {
            wp_delete_post($it->ID, true); // item de menu, não o livro nem a categoria
        } else {
            $todos = $it;
        }
    }

    $pos = (int) $categorias->menu_order;
    foreach (array_keys($arvore) as $slug) {
        wp_update_nav_menu_item($menu, 0, [
            'menu-item-object' => 'product_cat',
            'menu-item-object-id' => $ids[$slug],
            'menu-item-type' => 'taxonomy',
            'menu-item-parent-id' => $categorias->ID,
            'menu-item-position' => ++$pos,
            'menu-item-status' => 'publish',
        ]);
    }

    if ($todos) {
        wp_update_post(['ID' => $todos->ID, 'menu_order' => ++$pos]);
    }
    WP_CLI::log('menu "Categorias" refeito com '.count($arvore).' categorias principais');
}

// 7. conferência
$semNova = [];
foreach ($plano as $pid => $sub) {
    $agora = wp_get_post_terms($pid, 'product_cat', ['fields' => 'slugs']);
    if (! in_array($sub, $agora, true)) {
        $semNova[] = $pid;
    }
}
$variacoes = count(get_posts(['post_type' => 'product_variation', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids']));
WP_CLI::log(sprintf('conferência: %d livros na subcategoria certa, %d com problema; %d variações (intocadas)', count($plano) - count($semNova), count($semNova), $variacoes));

foreach ($arvore as $slug => [$nome, , $filhas]) {
    $linha = $nome.' ('.get_term($ids[$slug], 'product_cat')->count.')';
    foreach (array_keys($filhas) as $sub) {
        $linha .= "\n    ".get_term($ids[$sub], 'product_cat')->name.' ('.get_term($ids[$sub], 'product_cat')->count.')';
    }
    WP_CLI::log($linha);
}

delete_transient('wc_term_counts');
wc_delete_product_transients();
WP_CLI::success('reorganização aplicada');
