<?php
/**
 * Fotos e ordem dos autores em destaque (o painel do menu). Mesma lista do
 * scripts/seed-autores.sh, em PHP para rodar direto no servidor:
 *
 *   wp eval-file completar-autores.php
 *
 * Idempotente: quem já tem foto é deixado em paz.
 */

if (! defined('ABSPATH') || ! class_exists('WP_CLI')) {
    exit;
}

$fotos = dirname(__DIR__).'/scripts/autores';

$autores = [
    'Edemundo Dias' => 'edemundo-dias.png',
    'Rui Gilberto Ferreira' => 'rui-gilberto-ferreira.png',
    'Paulo Roberto Cunha' => 'paulo-roberto-cunha.png',
    'Leonardo Reis' => 'leonardo-reis.png',
    'Roberto Murillo Limongi' => 'roberto-murillo-limongi.png',
    'Fábio Bagnoli' => 'fabio-bagnoli.png',
    'Waldemar Naves do Amaral' => 'waldemar-naves-do-amaral.png',
];

$ordem = 0;

foreach ($autores as $nome => $arquivo) {
    $ordem++;
    $termo = get_term_by('name', $nome, 'autor');

    if (! $termo) {
        $novo = wp_insert_term($nome, 'autor');

        if (is_wp_error($novo)) {
            echo "  ! $nome: ".$novo->get_error_message()."\n";
            continue;
        }

        $termo = get_term($novo['term_id'], 'autor');
        echo "  + autor $nome\n";
    }

    update_term_meta($termo->term_id, 'conexao_ordem', $ordem);

    if (get_term_meta($termo->term_id, 'conexao_foto', true)) {
        echo "  = $nome\n";
        continue;
    }

    $caminho = $fotos.'/'.$arquivo;

    if (! file_exists($caminho)) {
        echo "  ! foto não encontrada: $caminho\n";
        continue;
    }

    $anexo = WP_CLI::runcommand(
        'media import '.escapeshellarg($caminho).' --title='.escapeshellarg($nome).' --porcelain',
        ['return' => true, 'exit_error' => false]
    );

    $anexo = (int) trim((string) $anexo);

    if (! $anexo) {
        echo "  ! não consegui importar a foto de $nome\n";
        continue;
    }

    update_term_meta($termo->term_id, 'conexao_foto', $anexo);
    echo "  + foto de $nome (anexo $anexo)\n";
}
