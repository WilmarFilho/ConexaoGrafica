<?php
/**
 * Tópicos da central de ajuda: alimentam os atalhos do topo e a sanfona.
 * O filtro conexao_ajuda_topicos permite trocar os textos sem editar o tema.
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * @return array<int, array{slug: string, titulo: string, atalho: string, resumo: string, perguntas: string[]}>
 */
function conexao_ajuda_topicos(): array
{
    $topicos = [
        [
            'slug' => 'central',
            'titulo' => 'Central de ajuda',
            'atalho' => 'Central de ajuda',
            'resumo' => 'Encontre respostas para as dúvidas mais comuns.',
            'perguntas' => [
                'Como faço uma compra?',
                'Como acompanho meu pedido?',
                'Posso trocar um produto?',
                'Como entro em contato?',
            ],
        ],
        [
            'slug' => 'comprar',
            'titulo' => 'Como comprar',
            'atalho' => 'Como Comprar',
            'resumo' => 'Confira o passo a passo para encontrar seu livro, finalizar seu pedido e receber sua compra.',
            'perguntas' => [
                'Como encontro um livro no site?',
                'Preciso criar uma conta para comprar?',
                'Como finalizo meu pedido?',
                'Posso comprar vários exemplares do mesmo título?',
            ],
        ],
        [
            'slug' => 'pagamento',
            'titulo' => 'Formas de pagamento',
            'atalho' => 'Formas de Pgto',
            'resumo' => 'Aceitamos diferentes formas de pagamento para facilitar sua experiência.',
            'perguntas' => [
                'Quais formas de pagamento são aceitas?',
                'Posso parcelar a compra?',
                'Quando o pagamento é confirmado?',
                'Recebo nota fiscal?',
            ],
        ],
        [
            'slug' => 'entrega',
            'titulo' => 'Entrega e prazos',
            'atalho' => 'Entrega e Prazos',
            'resumo' => 'Após a confirmação do pagamento, seu pedido será preparado e enviado.',
            'perguntas' => [
                'Qual é o prazo de entrega?',
                'Como o frete é calculado?',
                'Vocês entregam em todo o Brasil?',
                'E se eu não estiver em casa na entrega?',
            ],
        ],
        [
            'slug' => 'trocas',
            'titulo' => 'Trocas e devoluções',
            'atalho' => 'Trocas e Devoluções',
            'resumo' => 'Caso precise solicitar uma troca ou devolução, nossa equipe está pronta para orientar você.',
            'perguntas' => [
                'Qual é o prazo para pedir troca ou devolução?',
                'O que fazer se o livro chegou com defeito?',
                'Quem paga o frete da devolução?',
                'Como recebo o reembolso?',
            ],
        ],
        [
            'slug' => 'pedido',
            'titulo' => 'Acompanhe seu pedido',
            'atalho' => 'Acompanhe o Pedido',
            'resumo' => 'Após finalizar seu pedido, você poderá acompanhar as informações de envio e entrega.',
            'perguntas' => [
                'Onde vejo o status do meu pedido?',
                'Como recebo o código de rastreio?',
                'Meu pedido está atrasado, e agora?',
                'Posso mudar o endereço de entrega?',
            ],
        ],
    ];

    /**
     * @param array<int, array{slug: string, titulo: string, atalho: string, resumo: string, perguntas: string[]}> $topicos
     */
    return apply_filters('conexao_ajuda_topicos', $topicos);
}
