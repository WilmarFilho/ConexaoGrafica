<?php
/**
 * Cópia marcada do e-book: cada leitor recebe o PDF com o nome, o e-mail e o
 * número do pedido no rodapé de todas as páginas e em diagonais claras. Quem
 * tirar o arquivo pelo inspecionar elemento leva uma cópia que diz de quem é.
 *
 * O Ghostscript grava a marca (uns 40 s num livro de 150 páginas), em segundo
 * plano: a tela de leitura mostra "preparando a sua cópia" e se recarrega até
 * ficar pronta. A cópia fica em biblioteca/marcados e é refeita quando o PDF
 * original, o nome ou o e-mail mudam; a que ficar 45 dias sem uso é apagada.
 * Também é preparada assim que o pedido do e-book é pago.
 */

if (! defined('ABSPATH')) {
    exit;
}

const CONEXAO_BIBLIOTECA_MARCA_VERSAO = '3';

/** Caminho do Ghostscript; sem ele a marca fica desligada e o leitor entrega o original. */
function conexao_biblioteca_gs(): string
{
    $gs = defined('CONEXAO_BIBLIOTECA_GS') ? (string) CONEXAO_BIBLIOTECA_GS : '/usr/bin/gs';

    return is_executable($gs) && function_exists('proc_open') ? $gs : '';
}

function conexao_biblioteca_marca_ativa(): bool
{
    return (bool) apply_filters('conexao_biblioteca_marca_ativa', conexao_biblioteca_gs() !== '');
}

/** "Licenciado para Ana Souza · ana@exemplo.com · pedido 8339" (sem acento: a fonte do PDF é a padrão). */
function conexao_biblioteca_marca_texto(WP_User $usuario, int $produto_id): string
{
    $partes = [trim($usuario->display_name) ?: $usuario->user_login, $usuario->user_email];
    $cpf = conexao_biblioteca_cpf_do_leitor($usuario->ID);

    if ($cpf !== '') {
        $partes[] = 'CPF '.$cpf;
    }

    $pedido = conexao_biblioteca_pedido_do_livro($usuario->ID, $produto_id);

    if ($pedido) {
        $partes[] = 'pedido '.$pedido;
    }

    $texto = remove_accents('Licenciado para '.implode(' - ', array_filter($partes)));

    return trim((string) preg_replace('/[^\x20-\x7E]/', '', $texto));
}

/**
 * CPF completo do leitor (123.456.789-09). A conta é pessoal: o número inteiro
 * na página identifica sem dúvida quem repassou a cópia (decisão da editora).
 */
function conexao_biblioteca_cpf_do_leitor(int $usuario_id): string
{
    $cpf = (string) preg_replace('/\D/', '', (string) get_user_meta($usuario_id, 'billing_cpf', true));

    return strlen($cpf) === 11 ? substr($cpf, 0, 3).'.'.substr($cpf, 3, 3).'.'.substr($cpf, 6, 3).'-'.substr($cpf, 9, 2) : '';
}

/** Número do pedido pago mais recente com este livro (ou o combo que o libera). */
function conexao_biblioteca_pedido_do_livro(int $usuario_id, int $produto_id): string
{
    $direitos = function_exists('conexao_biblioteca_livros_do_usuario') ? conexao_biblioteca_livros_do_usuario($usuario_id) : [];
    $referencia = (string) ($direitos[$produto_id]['referencia'] ?? '');

    if (($direitos[$produto_id]['origem'] ?? '') === 'pedido' && $referencia !== '') {
        return $referencia;
    }

    return '';
}

function conexao_biblioteca_marcado_caminho(int $produto_id, WP_User $usuario): string
{
    $livro = conexao_biblioteca_livro($produto_id);

    if (! $livro['pdf']) {
        return '';
    }

    $chave = sha1(implode('|', [
        CONEXAO_BIBLIOTECA_MARCA_VERSAO,
        $livro['pdf'],
        (string) @filemtime($livro['pdf']),
        (string) @filesize($livro['pdf']),
        conexao_biblioteca_marca_texto($usuario, $produto_id),
    ]));

    return conexao_biblioteca_subpasta('marcados').'/'.conexao_biblioteca_produto_base($produto_id).'/'.$usuario->ID.'-'.substr($chave, 0, 16).'.pdf';
}

/**
 * A cópia marcada do leitor: o caminho quando pronta, '' quando ainda está
 * sendo preparada (e já manda preparar). Sem Ghostscript, devolve o original.
 */
function conexao_biblioteca_pdf_do_leitor(int $produto_id, WP_User $usuario): string
{
    $livro = conexao_biblioteca_livro($produto_id);

    if (! $livro['pdf']) {
        return '';
    }

    if (! conexao_biblioteca_marca_ativa()) {
        return $livro['pdf'];
    }

    $destino = conexao_biblioteca_marcado_caminho($produto_id, $usuario);

    if (is_file($destino)) {
        @touch($destino); // a limpeza apaga só o que ficou sem uso

        return $destino;
    }

    conexao_biblioteca_prepara_marcado($livro['pdf'], $destino, conexao_biblioteca_marca_texto($usuario, $produto_id));

    return '';
}

/** Dispara o Ghostscript em segundo plano (uma vez por cópia; trava de 15 min). */
function conexao_biblioteca_prepara_marcado(string $origem, string $destino, string $texto): void
{
    $gs = conexao_biblioteca_gs();
    $trava = $destino.'.trava';

    if ($gs === '' || (is_file($trava) && filemtime($trava) > time() - 15 * MINUTE_IN_SECONDS)) {
        return;
    }

    $pasta = dirname($destino);
    wp_mkdir_p($pasta);
    conexao_biblioteca_fecha_pasta(conexao_biblioteca_subpasta('marcados'));
    file_put_contents($trava, (string) time());

    // cópias antigas do mesmo leitor (PDF trocado, nome ou e-mail mudados) saem
    foreach (glob($pasta.'/'.strtok(basename($destino), '-').'-*.pdf') ?: [] as $antiga) {
        if ($antiga !== $destino) {
            @unlink($antiga);
        }
    }

    $ps = $destino.'.ps';
    file_put_contents($ps, conexao_biblioteca_marca_ps($texto));
    $temporario = $destino.'.parcial';

    $comando = sprintf(
        '( (%s -q -dSAFER -dBATCH -dNOPAUSE -dALLOWPSTRANSPARENCY -sDEVICE=pdfwrite -dPassThroughJPEGImages=true -dPassThroughJPXImages=true -dAutoRotatePages=/None -o %s %s -f %s && mv -f %s %s); rm -f %s %s ) </dev/null >/dev/null 2>&1 &',
        escapeshellarg($gs),
        escapeshellarg($temporario),
        escapeshellarg($ps),
        escapeshellarg($origem),
        escapeshellarg($temporario),
        escapeshellarg($destino),
        escapeshellarg($ps),
        escapeshellarg($trava)
    );

    $processo = proc_open(['/bin/sh', '-c', $comando], [['file', '/dev/null', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $tubos);

    if (is_resource($processo)) {
        proc_close($processo); // o sh volta na hora; o gs segue sozinho
    }
}

/** PostScript que desenha a marca no fim de cada página. */
function conexao_biblioteca_marca_ps(string $texto): string
{
    // string PostScript: só ASCII imprimível, com \ ( ) escapados
    $ps = strtr($texto, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);

    return "%!PS\n"
        ."/MarcaTexto ($ps) def\n"
        ."/MarcaFonte /Helvetica findfont 7.5 scalefont def\n"
        ."/MarcaDiag /Helvetica-Bold findfont 13 scalefont def\n"
        ."<<\n"
        ."  /EndPage {\n"
        ."    exch pop\n"
        ."    0 eq {\n"
        ."      gsave\n"
        ."        initmatrix\n"
        ."        currentpagedevice /PageSize get aload pop /MarcaH exch def /MarcaW exch def\n"
        ."        MarcaFonte setfont 0.45 setgray\n"
        ."        MarcaW 2 div MarcaTexto stringwidth pop 2 div sub 10 moveto MarcaTexto show\n"
        ."        /.setfillconstantalpha where { pop 0.10 .setfillconstantalpha 0.35 setgray } { 0.88 setgray } ifelse\n"
        ."        MarcaDiag setfont\n"
        ."        MarcaW 2 div MarcaH 2 div translate 32 rotate\n"
        ."        -1 1 1 { /MarcaI exch def MarcaTexto stringwidth pop 2 div neg MarcaI MarcaH 0.32 mul mul moveto MarcaTexto show } for\n"
        ."      grestore\n"
        ."      true\n"
        ."    } { false } ifelse\n"
        ."  } bind\n"
        .">> setpagedevice\n";
}

/** A pasta das cópias segue a regra das outras: nada de acesso direto. */
function conexao_biblioteca_fecha_pasta(string $pasta): void
{
    wp_mkdir_p($pasta);

    if (! file_exists($pasta.'/.htaccess')) {
        file_put_contents($pasta.'/.htaccess', "Require all denied\n");
    }

    if (! file_exists($pasta.'/index.php')) {
        file_put_contents($pasta.'/index.php', "<?php\n// Silêncio.\n");
    }
}

// pedido pago com e-book: a cópia do comprador já começa a ser preparada
add_action('woocommerce_order_status_processing', 'conexao_biblioteca_marca_do_pedido');
add_action('woocommerce_order_status_completed', 'conexao_biblioteca_marca_do_pedido');

function conexao_biblioteca_marca_do_pedido(int $pedido_id): void
{
    $pedido = wc_get_order($pedido_id);
    $usuario = $pedido ? get_user_by('id', (int) $pedido->get_customer_id()) : null;

    if (! $usuario || ! conexao_biblioteca_marca_ativa()) {
        return;
    }

    foreach ($pedido->get_items() as $item) {
        $produto_id = conexao_biblioteca_produto_base((int) $item->get_product_id());

        if (conexao_biblioteca_livro($produto_id)['pdf'] && conexao_biblioteca_pode_ler($usuario->ID, $produto_id)) {
            conexao_biblioteca_pdf_do_leitor($produto_id, $usuario);
        }
    }
}

// limpeza diária: cópia sem uso há 45 dias sai (é refeita se a pessoa voltar)
add_action('init', function (): void {
    if (! wp_next_scheduled('conexao_biblioteca_limpa_marcados')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'conexao_biblioteca_limpa_marcados');
    }
});

add_action('conexao_biblioteca_limpa_marcados', function (): void {
    $limite = time() - 45 * DAY_IN_SECONDS;

    foreach (glob(conexao_biblioteca_subpasta('marcados').'/*/*') ?: [] as $arquivo) {
        if (is_file($arquivo) && filemtime($arquivo) < $limite) {
            @unlink($arquivo);
        }
    }
});
