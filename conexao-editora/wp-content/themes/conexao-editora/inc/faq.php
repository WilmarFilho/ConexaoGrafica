<?php
/**
 * Perguntas frequentes. Ficam aqui, e não no editor, porque o layout numera e
 * sanfona cada item; o filtro conexao_faq_itens permite trocar os textos sem
 * mexer no tema.
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * @return array<int, array{pergunta: string, resposta: string}>
 */
function conexao_faq_itens(): array
{
    $itens = [
        [
            'pergunta' => 'Como funciona a publicação do meu livro?',
            'resposta' => 'Você nos envia o original e nossa equipe editorial faz a leitura e a análise. Aprovado o projeto, combinamos o escopo (revisão, diagramação, capa, ISBN e tiragem), assinamos o contrato e seguimos para a produção. Cada etapa é aprovada por você antes de o livro ir para a gráfica.',
        ],
        [
            'pergunta' => 'Como faço para enviar meu manuscrito?',
            'resposta' => 'Pela página Publique conosco. Preencha o formulário com seus dados e uma sinopse e anexe o original em Word ou PDF. Você recebe a confirmação por e-mail e, em seguida, o retorno da análise editorial.',
        ],
        [
            'pergunta' => 'Quais serviços estão incluídos na publicação?',
            'resposta' => 'O pacote padrão reúne análise editorial, revisão, diagramação do miolo, projeto de capa, registro de ISBN e ficha catalográfica, prova digital e impressão. Serviços como tradução, ilustração e assessoria de lançamento entram conforme o projeto.',
        ],
        [
            'pergunta' => 'Vocês publicam livros físicos e digitais?',
            'resposta' => 'Sim. O livro pode sair só impresso, só em e-book ou nos dois formatos. Quem opta pelo digital recebe o arquivo preparado para as principais lojas, com o mesmo projeto gráfico da edição impressa.',
        ],
        [
            'pergunta' => 'Quanto tempo leva para publicar um livro?',
            'resposta' => 'Depende do tamanho do original e dos serviços contratados. Depois da aprovação do projeto, você recebe um cronograma com as datas de cada etapa, e ele só muda se alguma aprovação demorar mais que o previsto.',
        ],
        [
            'pergunta' => 'Vocês fazem revisão do texto?',
            'resposta' => 'Fazemos. A revisão cuida de ortografia, gramática, pontuação e padronização, sempre preservando o seu estilo. Todas as alterações voltam para a sua aprovação antes da diagramação.',
        ],
        [
            'pergunta' => 'Posso escolher como será a capa e o projeto gráfico?',
            'resposta' => 'Sim. Nosso time apresenta propostas de capa e de miolo a partir do tema e do público do livro, e o caminho só avança depois do seu aval. Se você já tiver uma arte pronta, também podemos adaptá-la às exigências da impressão.',
        ],
        [
            'pergunta' => 'Existe uma quantidade mínima de exemplares para impressão?',
            'resposta' => 'Trabalhamos com impressão digital, que permite tiragens pequenas, e com offset, que compensa a partir de volumes maiores. Na proposta você vê o custo por exemplar em cada faixa e escolhe a que faz sentido.',
        ],
        [
            'pergunta' => 'Meu livro ficará disponível para venda?',
            'resposta' => 'Sim. Os títulos da editora entram na nossa loja e ficam disponíveis para o público em geral. O autor também pode comprar exemplares com condição especial para vender ou distribuir por conta própria.',
        ],
        [
            'pergunta' => 'Vocês fazem distribuição do livro?',
            'resposta' => 'Cuidamos da venda pela nossa loja e do envio para todo o país. A entrada em livrarias e marketplaces é avaliada caso a caso, de acordo com o perfil da obra.',
        ],
        [
            'pergunta' => 'Como acompanho meu pedido?',
            'resposta' => 'Pela página Acompanhe seu pedido ou pela sua conta, em Meus pedidos. Assim que o pacote é postado, enviamos o código de rastreio por e-mail.',
        ],
        [
            'pergunta' => 'Quais formas de pagamento são aceitas?',
            'resposta' => 'Na loja você paga com cartão de crédito, Pix ou boleto. Para contratos de publicação, as condições e o parcelamento são combinados na proposta.',
        ],
    ];

    /**
     * Permite ajustar as perguntas sem editar o tema.
     *
     * @param array<int, array{pergunta: string, resposta: string}> $itens
     */
    return apply_filters('conexao_faq_itens', $itens);
}
