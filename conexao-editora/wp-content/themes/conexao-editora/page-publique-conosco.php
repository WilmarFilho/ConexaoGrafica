<?php
/**
 * Publique conosco: banner, motivos, as etapas do processo e o formulário de
 * envio do original.
 */

if (! defined('ABSPATH')) {
    exit;
}

get_header();

the_post();

$img = get_template_directory_uri().'/assets/img/';
$arte = get_template_directory().'/assets/img/publique-hero.png';
$regras = conexao_arquivo_manuscrito();
$estado = isset($_GET['manuscrito']) ? sanitize_key(wp_unslash($_GET['manuscrito'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification

$motivos = [
    ['icone' => 'credibilidade', 'titulo' => 'Experiência e credibilidade', 'texto' => 'Anos de atuação no mercado editorial e centenas de títulos publicados com excelência.'],
    ['icone' => 'atendimento', 'titulo' => 'Atendimento próximo', 'texto' => 'Acompanhamento individualizado e transparente em todas as etapas do processo.'],
    ['icone' => 'qualidade', 'titulo' => 'Qualidade reconhecida', 'texto' => 'Padrões elevados de edição, revisão, projeto gráfico e impressão.'],
    ['icone' => 'autor', 'titulo' => 'Foco no autor', 'texto' => 'Valorizamos sua história e trabalhamos para que seu livro alcance mais leitores.'],
];

$etapas = [
    ['icone' => 'envio', 'titulo' => 'Envio do manuscrito', 'texto' => 'Você envia seu original pelo formulário.'],
    ['icone' => 'analise', 'titulo' => 'Análise editorial', 'texto' => 'Nossa equipe avalia o conteúdo e o potencial da obra.'],
    ['icone' => 'proposta', 'titulo' => 'Proposta personalizada', 'texto' => 'Enviamos uma proposta alinhada aos seus objetivos.'],
    ['icone' => 'producao', 'titulo' => 'Produção do livro', 'texto' => 'Edição, revisão, projeto gráfico e impressão com qualidade.'],
    ['icone' => 'lancamento', 'titulo' => 'Lançamento e divulgação', 'texto' => 'Seu livro chega ao mercado e aos leitores.'],
];

$avisos = [
    'ok' => ['ok', 'Manuscrito recebido. Nossa equipe editorial entra em contato em breve.'],
    'dados' => ['erro', 'Confira o nome e o e-mail e tente de novo.'],
    'formato' => ['erro', 'O arquivo precisa ser PDF, DOC ou DOCX.'],
    'tamanho' => ['erro', 'O arquivo passa de 25MB. Envie uma versão mais leve.'],
    'arquivo' => ['erro', 'Não consegui receber o arquivo. Tente enviar de novo.'],
    'erro' => ['erro', 'Não foi possível enviar agora. Tente novamente em instantes.'],
];
?>
<div class="container pagina pagina--publique">
    <?php conexao_trilha('Publique Conosco'); ?>

    <section class="publique-hero">
        <div class="publique-hero__texto">
            <h1>Transformarmos seu manuscrito<br>em <strong>livro de qualidade.</strong></h1>
            <p>Apoiamos autores em todas as etapas da publicação, com profissionalismo, cuidado e paixão por livros.</p>

            <div class="publique-hero__acoes">
                <a class="btn btn--branco" href="#envie-seu-manuscrito">Enviar meu manuscrito</a>
                <a class="btn btn--vazado" href="#como-funciona">Como funciona</a>
            </div>
        </div>

        <?php if (file_exists($arte)) : ?>
            <img class="publique-hero__arte" src="<?php echo esc_url($img.'publique-hero.png'); ?>" alt=""
                 aria-hidden="true" width="561" height="374" decoding="async">
        <?php endif; ?>
    </section>

    <section class="publique-motivos">
        <?php conexao_secao_titulo('Por que publicar conosco?'); ?>

        <div class="publique-motivos__grade">
            <?php foreach ($motivos as $motivo) : ?>
                <article class="publique-motivo">
                    <span class="publique-motivo__icone"><?php conexao_a_svg_publique($motivo['icone'], 26); ?></span>

                    <div>
                        <h3><?php echo esc_html($motivo['titulo']); ?></h3>
                        <p><?php echo esc_html($motivo['texto']); ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="publique-etapas" id="como-funciona">
        <?php conexao_secao_titulo('Como funciona?'); ?>

        <ol class="publique-etapas__lista">
            <?php foreach ($etapas as $i => $etapa) : ?>
                <li class="publique-etapa">
                    <?php conexao_a_svg_publique($etapa['icone'], 38); ?>
                    <h3><?php echo esc_html($etapa['titulo']); ?></h3>
                    <p><?php echo esc_html($etapa['texto']); ?></p>

                    <?php if ($i < count($etapas) - 1) : ?>
                        <span class="publique-etapa__seta" aria-hidden="true"><?php conexao_the_icon('seta-direita', 16); ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>

    <section class="publique-form" id="envie-seu-manuscrito">
        <?php conexao_secao_titulo('Envie seu manuscrito'); ?>

        <p class="publique-form__apoio">Preencha o formulário abaixo e nossa equipe entrará em contato.</p>

        <?php if (isset($avisos[$estado])) : ?>
            <p class="aviso aviso--<?php echo esc_attr($avisos[$estado][0]); ?>" role="<?php echo $avisos[$estado][0] === 'ok' ? 'status' : 'alert'; ?>">
                <?php echo esc_html($avisos[$estado][1]); ?>
            </p>
        <?php endif; ?>

        <form class="form-manuscrito" method="post" enctype="multipart/form-data"
              action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="conexao_manuscrito">
            <?php wp_nonce_field('conexao_manuscrito', 'conexao_manuscrito_nonce'); ?>

            <div class="form-manuscrito__campos">
                <label class="tela-leitor" for="manuscrito-nome">Nome</label>
                <input class="campo__entrada" type="text" id="manuscrito-nome" name="nome" placeholder="Nome" required autocomplete="name">

                <label class="tela-leitor" for="manuscrito-email">E-mail</label>
                <input class="campo__entrada" type="email" id="manuscrito-email" name="email" placeholder="E-mail" required autocomplete="email">

                <label class="tela-leitor" for="manuscrito-telefone">Telefone/WhatsApp</label>
                <input class="campo__entrada" type="tel" id="manuscrito-telefone" name="telefone" placeholder="Telefone/WhatsApp" autocomplete="tel">

                <label class="tela-leitor" for="manuscrito-genero">Gênero da obra</label>
                <select class="campo__entrada campo__entrada--select" id="manuscrito-genero" name="genero">
                    <option value="">Gênero da obra</option>
                    <?php foreach (conexao_generos_obra() as $genero) : ?>
                        <option value="<?php echo esc_attr($genero); ?>"><?php echo esc_html($genero); ?></option>
                    <?php endforeach; ?>
                </select>

                <label class="tela-leitor" for="manuscrito-obra">Título provisório da obra</label>
                <input class="campo__entrada" type="text" id="manuscrito-obra" name="obra" placeholder="Título provisório da obra">

                <label class="tela-leitor" for="manuscrito-mensagem">Mensagem</label>
                <textarea class="campo__entrada" id="manuscrito-mensagem" name="mensagem" rows="6" placeholder="Mensagem"></textarea>
            </div>

            <div class="form-manuscrito__lado">
                <label class="arquivo" for="manuscrito-arquivo" data-arquivo>
                    <?php conexao_a_svg_publique('upload', 30); ?>
                    <span class="arquivo__chamada">Arraste seu arquivo aqui<br>ou clique para selecionar</span>
                    <span class="arquivo__regras">
                        Formatos aceitos: <?php echo esc_html(strtoupper(implode(', ', $regras['extensoes']))); ?><br>
                        Tamanho máximo: <?php echo esc_html(size_format($regras['limite'])); ?>
                    </span>
                    <input type="file" id="manuscrito-arquivo" name="arquivo"
                           accept=".<?php echo esc_attr(implode(',.', $regras['extensoes'])); ?>">
                </label>

                <div class="armadilha" aria-hidden="true">
                    <label for="manuscrito-site">Deixe em branco</label>
                    <input type="text" id="manuscrito-site" name="site" tabindex="-1" autocomplete="off">
                </div>

                <button class="btn btn--azul btn--bloco" type="submit">Enviar manuscrito</button>
            </div>
        </form>
    </section>
</div>
<?php
get_footer();
