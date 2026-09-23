<?php
/**
 * Entrar e criar conta, lado a lado (substitui o template do WooCommerce).
 */

if (! defined('ABSPATH')) {
    exit;
}

do_action('woocommerce_before_customer_login_form');

$cadastro_aberto = 'yes' === get_option('woocommerce_enable_myaccount_registration');
$sociais = apply_filters('conexao_login_social', [
    'google' => ['rotulo' => 'Google', 'url' => ''],
    'facebook' => ['rotulo' => 'Facebook', 'url' => ''],
]);
?>
<div class="conta<?php echo $cadastro_aberto ? '' : ' conta--so-login'; ?>">

    <section class="conta__lado" id="conta-entrar">
        <h1 class="conta__titulo">Acesse sua conta</h1>
        <p class="conta__apoio">Entre para aproveitar uma experiência completa no Conexão Editora.</p>

        <form class="conta__form woocommerce-form woocommerce-form-login login" method="post">
            <?php do_action('woocommerce_login_form_start'); ?>

            <p class="campo">
                <label class="tela-leitor" for="username">E-mail</label>
                <input type="text" class="campo__entrada" name="username" id="username" autocomplete="username"
                       placeholder="E-mail" value="<?php echo ! empty($_POST['username']) ? esc_attr(wp_unslash($_POST['username'])) : ''; // phpcs:ignore ?>" required>
            </p>

            <p class="campo">
                <label class="tela-leitor" for="password">Senha</label>
                <input class="campo__entrada" type="password" name="password" id="password"
                       autocomplete="current-password" placeholder="Senha" required>
            </p>

            <div class="conta__linha">
                <label class="caixa">
                    <input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever">
                    <span>Lembre-me</span>
                </label>
                <a class="conta__link" href="<?php echo esc_url(wp_lostpassword_url()); ?>">Esqueci minha senha</a>
            </div>

            <?php do_action('woocommerce_login_form'); ?>

            <?php wp_nonce_field('woocommerce-login', 'woocommerce-login-nonce'); ?>
            <button type="submit" class="btn btn--azul btn--bloco" name="login" value="Entrar">Entrar</button>

            <?php do_action('woocommerce_login_form_end'); ?>
        </form>

        <?php if ($sociais) : ?>
            <p class="conta__ou-linha"><span>OU</span></p>

            <div class="conta__sociais">
                <?php foreach ($sociais as $chave => $rede) : ?>
                    <?php if ($rede['url']) : ?>
                        <a class="botao-social botao-social--<?php echo esc_attr($chave); ?>" href="<?php echo esc_url($rede['url']); ?>">
                            <?php conexao_the_icon($chave === 'google' ? 'google' : 'facebook', 18); ?>
                            <?php echo esc_html($rede['rotulo']); ?>
                        </a>
                    <?php else : ?>
                        <button class="botao-social botao-social--<?php echo esc_attr($chave); ?>" type="button" disabled
                                title="Entrar com <?php echo esc_attr($rede['rotulo']); ?> ainda não está configurado">
                            <?php conexao_the_icon($chave === 'google' ? 'google' : 'facebook', 18); ?>
                            <?php echo esc_html($rede['rotulo']); ?>
                        </button>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($cadastro_aberto) : ?>
            <p class="conta__rodape">Ainda não tem conta? <a class="conta__link" href="#conta-cadastrar">Cadastre-se</a></p>
        <?php endif; ?>
    </section>

    <?php if ($cadastro_aberto) : ?>
        <div class="conta__divisor" aria-hidden="true"><span>OU</span></div>

        <section class="conta__lado" id="conta-cadastrar">
            <h2 class="conta__titulo">Crie sua conta</h2>
            <p class="conta__apoio">Cadastre-se e descubra livros, conteúdos e soluções para você.</p>

            <form method="post" class="conta__form woocommerce-form woocommerce-form-register register" <?php do_action('woocommerce_register_form_tag'); ?>>
                <?php do_action('woocommerce_register_form_start'); ?>

                <p class="campo">
                    <label class="tela-leitor" for="reg_conexao_nome">Nome</label>
                    <input type="text" class="campo__entrada" name="conexao_nome" id="reg_conexao_nome" autocomplete="name"
                           placeholder="Nome" value="<?php echo ! empty($_POST['conexao_nome']) ? esc_attr(wp_unslash($_POST['conexao_nome'])) : ''; // phpcs:ignore ?>" required>
                </p>

                <p class="campo">
                    <label class="tela-leitor" for="reg_email">E-mail</label>
                    <input type="email" class="campo__entrada" name="email" id="reg_email" autocomplete="email"
                           placeholder="E-mail" value="<?php echo ! empty($_POST['email']) ? esc_attr(wp_unslash($_POST['email'])) : ''; // phpcs:ignore ?>" required>
                </p>

                <?php if ('no' === get_option('woocommerce_registration_generate_password')) : ?>
                    <p class="campo">
                        <label class="tela-leitor" for="reg_password">Senha</label>
                        <input type="password" class="campo__entrada" name="password" id="reg_password"
                               autocomplete="new-password" placeholder="Senha" required>
                    </p>

                    <p class="campo">
                        <label class="tela-leitor" for="reg_password2">Confirmar senha</label>
                        <input type="password" class="campo__entrada" name="conexao_password2" id="reg_password2"
                               autocomplete="new-password" placeholder="Confirmar senha" required>
                    </p>
                <?php else : ?>
                    <p class="conta__apoio">A senha será enviada para o seu e-mail.</p>
                <?php endif; ?>

                <label class="caixa caixa--termos">
                    <input type="checkbox" name="conexao_termos" id="reg_termos" value="1" required>
                    <span>
                        Li e aceito os
                        <a class="conta__link" href="<?php echo esc_url(home_url('/termos-de-uso/')); ?>">Termos de Uso</a> e
                        <a class="conta__link" href="<?php echo esc_url(home_url('/politica-de-privacidade/')); ?>">Política de Privacidade</a>
                    </span>
                </label>

                <?php do_action('woocommerce_register_form'); ?>

                <?php wp_nonce_field('woocommerce-register', 'woocommerce-register-nonce'); ?>
                <button type="submit" class="btn btn--azul btn--bloco" name="register" value="Cadastrar">Cadastrar</button>

                <?php do_action('woocommerce_register_form_end'); ?>
            </form>

            <p class="conta__rodape">Já tem uma conta? <a class="conta__link" href="#conta-entrar">Entrar</a></p>
        </section>
    <?php endif; ?>
</div>

<?php do_action('woocommerce_after_customer_login_form'); ?>
