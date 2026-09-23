<?php
/**
 * Contato: canais e formulário à esquerda, mapa à direita.
 */

if (! defined('ABSPATH')) {
    exit;
}

get_header();

$whatsapp = get_theme_mod('conexao_whatsapp', '5562998228022');
$telefones = get_theme_mod('conexao_telefones', '62 99822-8022 / 62 3229.6147');
$email_contato = get_theme_mod('conexao_email', 'contato@conexaoeditora.com.br');
$horario = get_theme_mod('conexao_horario', 'Seg. a sex: 8h às 18h');
$endereco = get_theme_mod('conexao_endereco', 'Rua 227 A, 20 — Setor Leste Universitário, Goiânia - GO');
$mapa = get_theme_mod('conexao_mapa', 'https://www.google.com/maps?q='.rawurlencode($endereco).'&output=embed');
$estado = isset($_GET['contato']) ? sanitize_key(wp_unslash($_GET['contato'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
?>
<div class="container pagina pagina--contato">
    <?php conexao_trilha('Contato'); ?>

    <div class="contato">
        <div class="contato__coluna">
            <h1 class="contato__titulo">Fale com a Conexão Editora</h1>
            <p class="contato__texto">Estamos prontos para entender seu projeto e entregar resultados que conectam.</p>

            <div class="canais">
                <a class="canais__zap" href="https://wa.me/<?php echo esc_attr($whatsapp); ?>" target="_blank" rel="noopener">
                    <span class="canal__icone"><?php conexao_the_icon('whatsapp', 26); ?></span>
                    <strong>WhatsApp</strong>
                    <?php foreach (array_map('trim', explode('/', $telefones)) as $telefone) : ?>
                        <span><?php echo esc_html($telefone); ?></span>
                    <?php endforeach; ?>
                </a>

                <div class="canais__coluna">
                    <div class="canal">
                        <span class="canal__icone"><?php conexao_the_icon('envelope', 19); ?></span>
                        <span>
                            <strong>E-mail</strong>
                            <a href="mailto:<?php echo esc_attr($email_contato); ?>"><?php echo esc_html($email_contato); ?></a>
                        </span>
                    </div>

                    <div class="canal">
                        <span class="canal__icone"><?php conexao_the_icon('relogio', 19); ?></span>
                        <span>
                            <strong>Horários</strong>
                            <span><?php echo esc_html($horario); ?></span>
                        </span>
                    </div>

                    <div class="canal">
                        <span class="canal__icone"><?php conexao_the_icon('pin', 19); ?></span>
                        <span>
                            <strong>Endereço</strong>
                            <span><?php echo esc_html($endereco); ?></span>
                        </span>
                    </div>
                </div>
            </div>

            <h2 class="contato__subtitulo">Envie sua mensagem</h2>

            <?php if ($estado === 'ok') : ?>
                <p class="aviso aviso--ok" role="status">Mensagem recebida. Entraremos em contato em breve.</p>
            <?php else : ?>
                <?php if ($estado === 'dados') : ?>
                    <p class="aviso aviso--erro" role="alert">Confira o nome e o e-mail e tente de novo.</p>
                <?php elseif ($estado === 'erro') : ?>
                    <p class="aviso aviso--erro" role="alert">Não foi possível enviar agora. Tente novamente em instantes.</p>
                <?php endif; ?>

                <p class="contato__texto">Preencha os campos abaixo e entraremos em contato.</p>

                <form class="form-contato" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="conexao_contato">
                    <?php wp_nonce_field('conexao_contato', 'conexao_contato_nonce'); ?>

                    <label class="tela-leitor" for="contato-nome">Nome</label>
                    <input class="campo__entrada" type="text" id="contato-nome" name="nome" placeholder="Nome" required autocomplete="name">

                    <label class="tela-leitor" for="contato-email">E-mail</label>
                    <input class="campo__entrada" type="email" id="contato-email" name="email" placeholder="E-mail" required autocomplete="email">

                    <label class="tela-leitor" for="contato-telefone">Telefone</label>
                    <input class="campo__entrada" type="tel" id="contato-telefone" name="telefone" placeholder="Telefone" autocomplete="tel">

                    <label class="tela-leitor" for="contato-assunto">Tipo de serviço</label>
                    <select class="campo__entrada campo__entrada--select" id="contato-assunto" name="assunto">
                        <option value="">Tipo de serviço</option>
                        <?php foreach (conexao_assuntos_contato() as $assunto) : ?>
                            <option value="<?php echo esc_attr($assunto); ?>"><?php echo esc_html($assunto); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label class="tela-leitor" for="contato-mensagem">Mensagem</label>
                    <textarea class="campo__entrada" id="contato-mensagem" name="mensagem" rows="5" placeholder="Mensagem"></textarea>

                    <div class="armadilha" aria-hidden="true">
                        <label for="contato-site">Deixe em branco</label>
                        <input type="text" id="contato-site" name="site" tabindex="-1" autocomplete="off">
                    </div>

                    <button class="btn btn--azul btn--bloco" type="submit">Enviar</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="contato__coluna">
            <iframe class="mapa" src="<?php echo esc_url($mapa); ?>" title="Localização da Conexão Editora"
                    loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
        </div>
    </div>
</div>
<?php
get_footer();
