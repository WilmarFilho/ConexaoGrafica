<?php
/**
 * Pop-up da ficha em PDF: nome, e-mail e o aceite da política de privacidade
 * antes de abrir a ficha (coleta de leads do plugin conexao-doi, inc/leads.php).
 * O botão que abre é qualquer [data-ficha="ID do livro ou capítulo"].
 */

if (! defined('ABSPATH')) {
    exit;
}

$privacidade = get_privacy_policy_url();
?>
<dialog class="ficha-modal" data-ficha-modal aria-labelledby="ficha-modal-titulo">
    <form class="ficha-modal__caixa" method="dialog" data-ficha-form data-api="<?php echo esc_url(rest_url('conexao-doi/v1/ficha')); ?>" novalidate>
        <button class="ficha-modal__fechar" type="button" data-ficha-fechar aria-label="Fechar">
            <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M2 2l12 12M14 2 2 14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </button>

        <span class="ficha-modal__icone"><?php echo conexao_livro_svg('ficha'); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <h2 class="ficha-modal__titulo" id="ficha-modal-titulo">Ficha <span data-ficha-rotulo>do livro</span> em PDF</h2>
        <p class="ficha-modal__texto">Informe seu nome e e-mail para abrir a ficha editorial com resumo, dados bibliográficos e como citar.</p>

        <label class="ficha-modal__campo">
            <span>Nome</span>
            <input type="text" name="nome" autocomplete="name" required maxlength="160">
        </label>

        <label class="ficha-modal__campo">
            <span>E-mail</span>
            <input type="email" name="email" autocomplete="email" required maxlength="190">
        </label>

        <!-- campo isca para robôs: fica fora da tela -->
        <label class="ficha-modal__isca" aria-hidden="true">
            Site <input type="text" name="site" tabindex="-1" autocomplete="off">
        </label>

        <label class="ficha-modal__marca">
            <input type="checkbox" name="aceite" required>
            <span>Li e aceito a <?php if ($privacidade) : ?><a href="<?php echo esc_url($privacidade); ?>" target="_blank" rel="noopener">política de privacidade</a><?php else : ?>política de privacidade<?php endif; ?>.</span>
        </label>

        <label class="ficha-modal__marca">
            <input type="checkbox" name="novidades">
            <span>Quero receber novidades e lançamentos da Conexão Editora por e-mail.</span>
        </label>

        <p class="ficha-modal__erro" data-ficha-erro role="alert" hidden></p>

        <button class="ficha-modal__enviar" type="submit" data-ficha-enviar>Abrir a ficha</button>
    </form>
</dialog>
