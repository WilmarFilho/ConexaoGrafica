<?php
/**
 * A Editora: banner, essência, história, áreas de atuação, chamada para
 * publicar e uma prateleira com livros do catálogo.
 */

if (! defined('ABSPATH')) {
    exit;
}

get_header();

the_post();

$img = get_template_directory_uri().'/assets/img/';
$loja = class_exists('WooCommerce') ? wc_get_page_permalink('shop') : home_url('/');
$publique = get_page_by_path('publique-conosco');
$quem_somos = get_page_by_path('quem-somos');

$essencia = [
    ['icone' => 'rigor', 'titulo' => 'Rigor editorial', 'texto' => 'Leitura crítica, revisão e padronização do texto em cada etapa do projeto.'],
    ['icone' => 'autores', 'titulo' => 'Autores no centro', 'texto' => 'Acompanhamento próximo e transparente, do original ao livro pronto.'],
    ['icone' => 'grafica', 'titulo' => 'Excelência gráfica', 'texto' => 'Papel, acabamento e impressão à altura do conteúdo de cada obra.'],
    ['icone' => 'alcance', 'titulo' => 'Conhecimento que alcança', 'texto' => 'Divulgação e distribuição para o livro chegar a quem precisa dele.'],
];

$areas = [
    ['icone' => 'saude', 'nome' => 'Saúde'],
    ['icone' => 'educacao', 'nome' => 'Educação'],
    ['icone' => 'direito', 'nome' => 'Direito'],
    ['icone' => 'humanidades', 'nome' => 'Humanidades'],
    ['icone' => 'sociais', 'nome' => 'Ciências Sociais'],
    ['icone' => 'outros', 'nome' => 'Outros temas'],
];

$livros = conexao_produtos('destaque', 5);
?>
<section class="editora-hero">
    <div class="container editora-hero__grade">
        <?php conexao_trilha('A Editora'); ?>

        <div class="editora-hero__texto">
            <h1>Conteúdo que conecta<br>conhecimento a pessoas.</h1>
            <p>A Conexão Editora publica obras que informam, inspiram e transformam. Reunimos conhecimento, qualidade editorial e compromisso com autores e leitores.</p>
            <a class="btn btn--branco" href="<?php echo esc_url($loja); ?>">Conheça nosso catálogo</a>
        </div>

        <img class="editora-hero__arte" src="<?php echo esc_url($img.'editora-hero.png'); ?>" alt=""
             aria-hidden="true" width="635" height="502" decoding="async">
    </div>
</section>

<section class="editora-essencia">
    <div class="container">
        <header class="editora-topo">
            <p class="editora-etiqueta">Nossa essência</p>
            <h2>Mais que livros, promovemos conhecimento.</h2>
            <p class="editora-topo__texto">Acreditamos no poder das ideias e no impacto da informação de qualidade. Trabalhamos para transformar manuscritos em obras relevantes e bem produzidas.</p>
        </header>

        <div class="editora-carrossel">
            <button class="editora-seta editora-seta--ant" type="button" aria-label="Anterior"
                    data-rolar="-1" data-trilho=".editora-pilares">
                <?php conexao_the_icon('seta-esquerda', 20); ?>
            </button>
            <button class="editora-seta editora-seta--prox" type="button" aria-label="Próximo"
                    data-rolar="1" data-trilho=".editora-pilares">
                <?php conexao_the_icon('seta-direita', 20); ?>
            </button>

        <div class="editora-pilares" data-arrastavel>
            <?php foreach ($essencia as $pilar) : ?>
                <article class="editora-pilar">
                    <span class="editora-pilar__icone"><?php conexao_a_svg_editora($pilar['icone'], 26); ?></span>

                    <div>
                        <h3><?php echo esc_html($pilar['titulo']); ?></h3>
                        <p><?php echo esc_html($pilar['texto']); ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        </div>
    </div>
</section>

<section class="editora-historia">
    <div class="container editora-dupla">
        <img class="editora-dupla__arte" src="<?php echo esc_url($img.'editora-historia.png'); ?>" alt=""
             aria-hidden="true" width="653" height="435" loading="lazy" decoding="async">

        <div class="editora-dupla__texto">
            <p class="editora-etiqueta">Nossa história</p>
            <h2>Compromisso com ideias<br>que fazem a diferença.</h2>
            <p>A Conexão Editora nasceu da vontade de publicar obras que informam, inspiram e transformam. Desde então reunimos conhecimento, qualidade editorial e compromisso com autores e leitores.</p>
            <a class="btn btn--azul" href="<?php echo esc_url($quem_somos ? get_permalink($quem_somos) : home_url('/quem-somos/')); ?>">Veja nossa trajetória</a>
        </div>
    </div>
</section>

<section class="editora-areas">
    <div class="container">
        <header class="editora-topo">
            <p class="editora-etiqueta">Nossas áreas de atuação</p>
            <h2>Publicamos para diferentes áreas do conhecimento.</h2>
        </header>

        <ul class="editora-areas__lista">
            <?php foreach ($areas as $area) : ?>
                <li>
                    <?php conexao_a_svg_editora($area['icone'], 40); ?>
                    <span><?php echo esc_html($area['nome']); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<section class="editora-publicar">
    <div class="container editora-dupla editora-dupla--invertida">
        <div class="editora-dupla__texto">
            <p class="editora-etiqueta">Quer publicar seu livro?</p>
            <h2>Transforme seu manuscrito em<br>uma obra de qualidade.</h2>
            <p>Acompanhamos você em todas as etapas do processo editorial: revisão, projeto gráfico, impressão e distribuição.</p>
            <a class="btn btn--azul" href="<?php echo esc_url($publique ? get_permalink($publique) : home_url('/publique-conosco/')); ?>">Enviar meu manuscrito</a>
        </div>

        <img class="editora-dupla__arte" src="<?php echo esc_url($img.'editora-publicar.png'); ?>" alt=""
             aria-hidden="true" width="634" height="476" loading="lazy" decoding="async">
    </div>
</section>

<?php if ($livros) : ?>
    <section class="editora-livros">
        <div class="container">
            <header class="editora-topo">
                <p class="editora-etiqueta">Nossos livros</p>
                <h2>Conheça algumas de nossas publicações</h2>
            </header>

            <div class="editora-livros__trilho" data-arrastavel>
                <?php foreach ($livros as $livro) : ?>
                    <article class="editora-livro">
                        <a class="editora-livro__capa" href="<?php echo esc_url($livro->get_permalink()); ?>" tabindex="-1" aria-hidden="true">
                            <?php echo $livro->get_image('woocommerce_thumbnail'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                        </a>

                        <h3><a href="<?php echo esc_url($livro->get_permalink()); ?>"><?php echo esc_html($livro->get_name()); ?></a></h3>

                        <?php $autores = conexao_autores($livro); ?>
                        <?php if ($autores) : ?>
                            <p class="editora-livro__autores"><?php echo esc_html('Autores: '.$autores); ?></p>
                        <?php endif; ?>

                        <p class="editora-livro__preco"><?php echo wp_kses_post($livro->get_price_html()); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>

            <p class="editora-livros__acao">
                <a class="btn btn--contorno" href="<?php echo esc_url($loja); ?>">Ver catálogo completo</a>
            </p>
        </div>
    </section>
<?php endif; ?>
<?php
get_footer();
