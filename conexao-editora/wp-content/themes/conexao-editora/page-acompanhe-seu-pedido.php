<?php
/**
 * Acompanhe seu pedido: formulário (número e e-mail) à esquerda; à direita, a
 * situação do pedido encontrado ou, antes da busca, como funciona e a ajuda.
 */

if (! defined('ABSPATH')) {
    exit;
}

[$pedido, $erro] = conexao_acompanhar_envio();

$numero_digitado = isset($_POST['pedido']) ? sanitize_text_field(wp_unslash($_POST['pedido'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$email_digitado = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification

if ($email_digitado === '' && is_user_logged_in()) {
    $email_digitado = wp_get_current_user()->user_email;
}

$whatsapp = get_theme_mod('conexao_whatsapp', '5562998228022');
$email_contato = get_theme_mod('conexao_email', 'contato@conexaoeditora.com.br');

get_header();
?>
<div class="container pagina pagina--acompanhar">
    <?php conexao_trilha(get_the_title()); ?>

    <div class="acompanhar<?php echo $pedido ? ' acompanhar--resultado' : ''; ?>" id="acompanhar">
        <section class="acompanhar__busca">
            <h1 class="contato__titulo">Acompanhe seu pedido</h1>
            <p class="contato__texto">Informe o número do pedido e o e-mail usado na compra. Os dois estão no e-mail de confirmação que enviamos.</p>

            <?php if ($erro) : ?>
                <p class="aviso aviso--erro" role="alert"><?php echo esc_html($erro); ?></p>
            <?php endif; ?>

            <form class="form-contato" method="post" action="<?php echo esc_url(get_permalink().'#acompanhar'); ?>">
                <?php wp_nonce_field('conexao_acompanhar', 'conexao_acompanhar_nonce'); ?>

                <label class="tela-leitor" for="acompanhar-pedido">Número do pedido</label>
                <input class="campo__entrada" type="text" id="acompanhar-pedido" name="pedido" placeholder="Número do pedido" required inputmode="numeric" value="<?php echo esc_attr($numero_digitado); ?>">

                <label class="tela-leitor" for="acompanhar-email">E-mail da compra</label>
                <input class="campo__entrada" type="email" id="acompanhar-email" name="email" placeholder="E-mail da compra" required autocomplete="email" value="<?php echo esc_attr($email_digitado); ?>">

                <button class="btn btn--azul btn--bloco" type="submit">Acompanhar pedido</button>
            </form>

            <p class="acompanhar__conta">
                <?php if (is_user_logged_in()) : ?>
                    Todos os seus pedidos estão em <a href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>">Minha conta › Pedidos</a>.
                <?php else : ?>
                    Tem cadastro? <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>">Entre na sua conta</a> para ver todos os seus pedidos.
                <?php endif; ?>
            </p>
        </section>

        <section class="acompanhar__painel" aria-live="polite">
            <?php if ($pedido) : ?>
                <?php
                $etapas = conexao_etapas_pedido($pedido);
                $rastreio = conexao_rastreio_pedido($pedido);
                ?>
                <div class="pedido-status">
                    <header class="pedido-status__topo">
                        <div>
                            <h2>Pedido nº <?php echo esc_html($pedido->get_order_number()); ?></h2>
                            <p>Feito em <?php echo esc_html(wc_format_datetime($pedido->get_date_created(), 'd/m/Y')); ?></p>
                        </div>
                        <span class="pedido-status__selo pedido-status__selo--<?php echo esc_attr($pedido->get_status()); ?>"><?php echo esc_html(wc_get_order_status_name($pedido->get_status())); ?></span>
                    </header>

                    <?php if ($etapas) : ?>
                        <ol class="etapas">
                            <?php foreach ($etapas as [$rotulo, $detalhe, $estado]) : ?>
                                <li class="etapas__item etapas__item--<?php echo esc_attr($estado); ?>"<?php echo $estado === 'atual' ? ' aria-current="step"' : ''; ?>>
                                    <span class="etapas__marca" aria-hidden="true"><?php $estado === 'feito' ? conexao_the_icon('check', 14) : null; ?></span>
                                    <span class="etapas__texto">
                                        <strong><?php echo esc_html($rotulo); ?></strong>
                                        <?php if ($detalhe !== '') : ?>
                                            <small><?php echo esc_html($detalhe); ?></small>
                                        <?php endif; ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php else : ?>
                        <p class="aviso aviso--erro">Este pedido está como "<?php echo esc_html(wc_get_order_status_name($pedido->get_status())); ?>". Se tiver alguma dúvida, fale com a gente.</p>
                    <?php endif; ?>

                    <?php if ($rastreio !== '') : ?>
                        <p class="pedido-status__rastreio">
                            <?php conexao_the_icon('caminhao', 20); ?>
                            <span>Código de rastreio: <strong><?php echo esc_html($rastreio); ?></strong></span>
                            <a class="btn btn--contorno btn--pequeno" href="<?php echo esc_url('https://www.melhorrastreio.com.br/rastreio/'.rawurlencode($rastreio)); ?>" target="_blank" rel="noopener noreferrer">Rastrear entrega</a>
                        </p>
                    <?php endif; ?>

                    <h3 class="pedido-status__subtitulo">Itens do pedido</h3>
                    <ul class="pedido-status__itens">
                        <?php foreach ($pedido->get_items() as $item) : ?>
                            <li>
                                <span><?php echo esc_html($item->get_name()); ?> <small>× <?php echo esc_html($item->get_quantity()); ?></small></span>
                                <span><?php echo wp_kses_post($pedido->get_formatted_line_subtotal($item)); ?></span>
                            </li>
                        <?php endforeach; ?>

                        <?php if ((float) $pedido->get_shipping_total() > 0) : ?>
                            <li class="pedido-status__frete">
                                <span>Frete <small><?php echo esc_html($pedido->get_shipping_method()); ?></small></span>
                                <span><?php echo wp_kses_post(wc_price($pedido->get_shipping_total(), ['currency' => $pedido->get_currency()])); ?></span>
                            </li>
                        <?php endif; ?>

                        <li class="pedido-status__total">
                            <span>Total</span>
                            <span><?php echo wp_kses_post($pedido->get_formatted_order_total()); ?></span>
                        </li>
                    </ul>
                </div>
            <?php else : ?>
                <div class="acompanhar__ajuda">
                    <h2>Como funciona</h2>
                    <ol class="acompanhar__passos">
                        <li><strong>Pagamento aprovado.</strong> Assim que o pagamento é confirmado, o pedido entra em preparação.</li>
                        <li><strong>Envio.</strong> Os livros impressos saem com código de rastreio, que aparece aqui e no seu e-mail.</li>
                        <li><strong>E-books.</strong> Ficam na sua biblioteca, em Minha conta, logo após a confirmação do pagamento.</li>
                    </ol>

                    <h2>Precisa de ajuda?</h2>
                    <div class="acompanhar__canais">
                        <a href="https://wa.me/<?php echo esc_attr($whatsapp); ?>" target="_blank" rel="noopener"><?php conexao_the_icon('whatsapp', 20); ?> Falar no WhatsApp</a>
                        <a href="mailto:<?php echo esc_attr($email_contato); ?>"><?php conexao_the_icon('envelope', 20); ?> <?php echo esc_html($email_contato); ?></a>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php
get_footer();
