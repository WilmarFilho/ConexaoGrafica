<?php
/**
 * Faixas de vendas corporativas e newsletter, rodapé e botões flutuantes.
 */

if (! defined('ABSPATH')) {
    exit;
}

$whatsapp = get_theme_mod('conexao_whatsapp', '5562998228022');
$telefones = get_theme_mod('conexao_telefones', '62 99822-8022 / 62 3229.8147');
$email_contato = get_theme_mod('conexao_email', 'contato@conexaoeditora.com.br');
?>
</main>

<section class="corporativo">
    <div class="container corporativo__grade">
        <div class="corporativo__marca">
            <img class="corporativo__icone" src="<?php echo esc_url(get_template_directory_uri().'/assets/img/icone-corporativo.png'); ?>" alt="" aria-hidden="true" width="139" height="125" loading="lazy">
            <div>
                <h2>Vendas Corporativas</h2>
                <p>Soluções completas em livros para empresas, instituições de ensino, bibliotecas e órgãos públicos.</p>
            </div>
        </div>

        <ul class="corporativo__vantagens">
            <li><?php conexao_the_icon('check', 20); ?> Descontos Especiais</li>
            <li><?php conexao_the_icon('check', 20); ?> Faturamento facilitado</li>
            <li><?php conexao_the_icon('check', 20); ?> Atendimento consultivo</li>
        </ul>

        <a class="btn btn--branco" href="<?php echo esc_url(home_url('/vendas-corporativas/')); ?>">Fale conosco</a>
    </div>
</section>

<section class="newsletter">
    <div class="container newsletter__grade">
        <img class="newsletter__icone" src="<?php echo esc_url(get_template_directory_uri().'/assets/img/icone-newsletter.png'); ?>" alt="" aria-hidden="true" width="109" height="87" loading="lazy">
        <div class="newsletter__texto">
            <h2>Receba novidades e lançamentos</h2>
            <p>Assine nossa newsletter e fique por dentro de tudo.</p>
        </div>

        <form class="newsletter__form" method="post" action="<?php echo esc_url(home_url('/newsletter/')); ?>">
            <label class="tela-leitor" for="newsletter-email">Seu e-mail</label>
            <input type="email" id="newsletter-email" name="email" required placeholder="Seu melhor e-mail">
            <button class="btn btn--escuro" type="submit">Enviar</button>
        </form>
    </div>
</section>

<footer class="rodape">
    <div class="container rodape__grade">
        <div class="rodape__marca">
            <?php conexao_logo('rodape'); ?>
            <p>Publicamos conhecimento que inspira, conecta pessoas e transforma ideias em livros de qualidade.</p>
        </div>

        <?php
        $colunas = [
            'rodape-catalogo' => 'Catálogo',
            'rodape-editora' => 'Editora',
            'rodape-ajuda' => 'Ajuda',
        ];

        foreach ($colunas as $local => $titulo) :
            if (! has_nav_menu($local)) {
                continue;
            }
            ?>
            <nav class="rodape__coluna" aria-label="<?php echo esc_attr($titulo); ?>">
                <h3><?php echo esc_html($titulo); ?></h3>
                <?php
                wp_nav_menu([
                    'theme_location' => $local,
                    'container' => false,
                    'menu_class' => 'rodape__links',
                    'depth' => 1,
                ]);
                ?>
            </nav>
        <?php endforeach; ?>

        <div class="rodape__coluna">
            <?php if (has_nav_menu('rodape-politicas')) : ?>
                <h3>Políticas</h3>
                <?php
                wp_nav_menu([
                    'theme_location' => 'rodape-politicas',
                    'container' => false,
                    'menu_class' => 'rodape__links',
                    'depth' => 1,
                ]);
                ?>
            <?php endif; ?>

            <h3 class="rodape__titulo-secundario">Pagamento</h3>
            <ul class="rodape__pagamento">
                <li>Cartão de crédito</li>
                <li>Pix</li>
                <li>Boleto</li>
            </ul>
            <p class="rodape__seguro"><?php conexao_the_icon('cadeado', 18); ?> Compra segura</p>
        </div>

        <div class="rodape__coluna">
            <h3>Contato</h3>
            <ul class="rodape__links rodape__links--contato">
                <li><?php echo esc_html($telefones); ?></li>
                <li><a href="mailto:<?php echo esc_attr($email_contato); ?>"><?php echo esc_html($email_contato); ?></a></li>
                <li>Seg. a sex. 8h às 18h</li>
                <li>Goiânia - GO</li>
            </ul>

            <h3 class="rodape__titulo-secundario">Redes sociais</h3>
            <ul class="rodape__sociais">
                <li><a href="#" aria-label="Instagram">in</a></li>
                <li><a href="#" aria-label="Facebook">f</a></li>
                <li><a href="#" aria-label="YouTube">yt</a></li>
            </ul>
        </div>
    </div>

    <div class="container rodape__base">
        <p>&copy; <?php echo esc_html(date_i18n('Y')); ?> Conexão Editora. Todos os direitos reservados.</p>
    </div>
</footer>

<div class="flutuantes">
    <a class="flutuante flutuante--whatsapp" href="https://wa.me/<?php echo esc_attr($whatsapp); ?>" aria-label="Falar no WhatsApp">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5.1-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.4-.7-1.7-.8-.2-.1-.4-.1-.5.1l-.7.9c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.1-.2 0-.4.1-.5l.4-.5c.1-.2.1-.3 0-.5l-.7-1.7c-.2-.4-.4-.4-.5-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.6 4 5 5 0 0 0 2.9.6 2.5 2.5 0 0 0 1.6-1.2 2 2 0 0 0 .1-1.2c0-.1-.2-.2-.4-.3z"/></svg>
    </a>
    <button class="flutuante flutuante--topo" type="button" aria-label="Voltar ao topo"><?php conexao_the_icon('topo', 22); ?></button>
</div>

<?php wp_footer(); ?>
</body>
</html>
