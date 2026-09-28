<?php
/**
 * Roda NO SITE NOVO: completa a ficha dos livros com o que existe na Pubcon —
 * páginas, idioma, ISBN, editora, ano, autores, etiquetas, peso e medidas,
 * capa e páginas de amostra (galeria do produto).
 *
 * Só preenche o que está vazio: o que já foi editado aqui não é sobrescrito.
 *
 *   wp eval-file importar-produtos.php                     simulação
 *   CONEXAO_APLICAR=1 wp eval-file importar-produtos.php   para valer
 *
 * CONEXAO_PACOTE  pacote gerado pelo exportar-produtos.php
 * CONEXAO_MIDIA   pasta com as imagens (mesma estrutura de uploads da Pubcon);
 *                 sem ela, cada imagem é baixada pelo endereço original
 */

global $wpdb;

require_once ABSPATH.'wp-admin/includes/image.php';
require_once ABSPATH.'wp-admin/includes/file.php';
require_once ABSPATH.'wp-admin/includes/media.php';

$aplicar = (bool) getenv('CONEXAO_APLICAR');
$arquivo = getenv('CONEXAO_PACOTE') ?: (getenv('HOME').'/pubcon-produtos.json');
$midia = rtrim((string) getenv('CONEXAO_MIDIA'), '/');
$pacote = file_exists($arquivo) ? json_decode((string) file_get_contents($arquivo), true) : null;

if (! is_array($pacote) || empty($pacote['produtos'])) {
    WP_CLI::error('pacote de produtos não encontrado ou inválido');
}

WP_CLI::log($aplicar ? '== APLICANDO' : '== SIMULAÇÃO (nada será gravado)');

$feito = array_fill_keys(['campos', 'autores', 'etiquetas', 'medidas', 'capas', 'amostras', 'imagens', 'textos'], 0);
$avisos = [];

/** Traz a imagem para a biblioteca de mídia, uma vez só por endereço de origem. */
$importa = static function (array $img, int $produto_id, string $titulo) use ($wpdb, $midia, $aplicar, &$feito, &$avisos): int {
    $ja = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_pubcon_origem' AND meta_value = %s LIMIT 1",
        $img['url']
    ));

    if ($ja && get_post($ja)) {
        return $ja;
    }

    $feito['imagens']++;

    if (! $aplicar) {
        return -1;
    }

    $local = $midia !== '' ? $midia.'/'.$img['relativo'] : '';

    if ($local !== '' && file_exists($local)) {
        $tmp = wp_tempnam(basename($local));
        copy($local, $tmp);
    } else {
        $tmp = download_url($img['url'], 60);

        if (is_wp_error($tmp)) {
            $avisos[] = "imagem não veio: {$img['relativo']} (".$tmp->get_error_message().')';

            return 0;
        }
    }

    $id = media_handle_sideload(['name' => basename($img['relativo']), 'tmp_name' => $tmp], $produto_id, $titulo);

    if (is_wp_error($id)) {
        @unlink($tmp);
        $avisos[] = "imagem recusada: {$img['relativo']} (".$id->get_error_message().')';

        return 0;
    }

    update_post_meta($id, '_pubcon_origem', $img['url']);
    update_post_meta($id, '_wp_attachment_image_alt', $titulo);

    return (int) $id;
};

$vazio = static fn (int $id, string $chave): bool => trim((string) get_post_meta($id, $chave, true)) === '';

// impressos primeiro: num título unificado, a ficha do impresso tem preferência
usort($pacote['produtos'], static fn ($a, $b) => [(int) $a['virtual'], $a['id']] <=> [(int) $b['virtual'], $b['id']]);

foreach ($pacote['produtos'] as $p) {
    $local = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_pubcon_id' AND meta_value = %s LIMIT 1",
        (string) $p['id']
    ));

    if (! $local) {
        $avisos[] = "sem par no site novo: #{$p['id']} {$p['nome']}";
        continue;
    }

    $alvo = (int) get_post_meta($local, '_conexao_unificado_em', true) ?: $local;
    $produto = wc_get_product($alvo);

    if (! $produto) {
        continue;
    }

    // ---- campos do livro
    $isbn = preg_replace('/[^0-9Xx-]/', '', (string) $p['isbn']);
    $digitos = strlen(preg_replace('/[^0-9Xx]/', '', $isbn));
    $campos = [
        '_conexao_paginas' => preg_replace('/\D/', '', (string) $p['paginas']),
        '_conexao_idioma' => $p['idioma'],
        '_conexao_editora' => $p['editora'],
        '_conexao_publicacao' => preg_match('/^(19|20)\d{2}$/', (string) $p['ano']) ? $p['ano'] : '',
        '_conexao_isbn13' => $digitos === 13 ? $isbn : '',
        '_conexao_isbn10' => $digitos === 10 ? $isbn : '',
    ];

    foreach ($campos as $chave => $valor) {
        if ($valor !== '' && $vazio($alvo, $chave)) {
            $feito['campos']++;

            if ($aplicar) {
                update_post_meta($alvo, $chave, $valor);
            }
        }
    }

    // ---- autores e etiquetas
    if ($p['autores'] !== '' && ! wp_get_post_terms($alvo, 'autor', ['fields' => 'ids'])) {
        $nomes = array_filter(array_map('trim', preg_split('/\s*(?:,|;|\se\s)\s*/u', $p['autores'])));
        $feito['autores']++;

        if ($aplicar && $nomes) {
            $ids = [];

            foreach ($nomes as $nome) {
                $termo = term_exists($nome, 'autor') ?: wp_insert_term($nome, 'autor');

                if (! is_wp_error($termo)) {
                    $ids[] = (int) $termo['term_id'];
                }
            }

            wp_set_object_terms($alvo, $ids, 'autor');
        }
    }

    if ($p['etiquetas']) {
        $faltam = array_diff($p['etiquetas'], wp_get_post_terms($alvo, 'product_tag', ['fields' => 'names']));

        if ($faltam) {
            $feito['etiquetas']++;

            if ($aplicar) {
                wp_set_object_terms($alvo, array_values($faltam), 'product_tag', true);
            }
        }
    }

    // ---- textos
    foreach (['resumo' => 'short_description', 'descricao' => 'description'] as $origem => $prop) {
        if (trim((string) $p[$origem]) !== '' && trim((string) $produto->{"get_{$prop}"}()) === '') {
            $feito['textos']++;

            if ($aplicar) {
                $produto->{"set_{$prop}"}($p[$origem]);
                $produto->save();
            }
        }
    }

    // ---- peso e medidas: valem para o impresso
    if (! $p['virtual'] && ($p['peso'] !== '' || array_filter($p['medidas']))) {
        $itens = [$produto];

        if ($produto->is_type('variable')) {
            foreach ($produto->get_children() as $filha) {
                $v = wc_get_product($filha);

                if ($v && ! $v->is_virtual()) {
                    $itens[] = $v;
                }
            }
        }

        foreach ($itens as $item) {
            $mudou = false;

            if ($p['peso'] !== '' && $item->get_weight('edit') === '') {
                $item->set_weight($p['peso']);
                $mudou = true;
            }

            foreach ($p['medidas'] as $lado => $valor) {
                if ($valor !== '' && $item->{"get_{$lado}"}('edit') === '') {
                    $item->{"set_{$lado}"}($valor);
                    $mudou = true;
                }
            }

            if ($mudou) {
                $feito['medidas']++;

                if ($aplicar) {
                    $item->save();
                }
            }
        }
    }

    // ---- capa
    $capa_atual = (int) get_post_thumbnail_id($alvo);
    $capa_ok = $capa_atual && file_exists((string) get_attached_file($capa_atual));

    if (! $capa_ok && $p['capa']) {
        $feito['capas']++;
        $nova = $importa($p['capa'], $alvo, $p['nome']);

        if ($aplicar && $nova > 0) {
            set_post_thumbnail($alvo, $nova);
        }
    }

    // ---- amostras: viram a galeria do produto
    if ($p['amostras'] && $vazio($alvo, '_product_image_gallery')) {
        $capa_nome = $p['capa'] ? $p['capa']['relativo'] : '';
        $galeria = [];

        foreach ($p['amostras'] as $img) {
            if ($img['relativo'] === $capa_nome) {
                continue; // a capa repetida dentro das amostras não entra
            }

            $id = $importa($img, $alvo, $p['nome'].' — amostra');

            if ($id) {
                $galeria[] = $id;
            }
        }

        if ($galeria) {
            $feito['amostras']++;

            if ($aplicar) {
                update_post_meta($alvo, '_product_image_gallery', implode(',', array_unique($galeria)));
            }
        }
    }
}

foreach ($feito as $o_que => $n) {
    WP_CLI::log(sprintf('   %-10s %d', $o_que, $n));
}

foreach (array_slice($avisos, 0, 30) as $aviso) {
    WP_CLI::warning($aviso);
}

WP_CLI::success($aplicar ? 'Fichas completadas.' : 'Simulação concluída. Rode com CONEXAO_APLICAR=1 para gravar.');
