<?php

namespace App\ReaderApp;

use App\Enums\OrderStatus;
use App\Models\Channel;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shipment;
use Illuminate\Support\Carbon;

/**
 * Pedido no formato do app do leitor (conexao-editora/docs/app-leitor-contrato.md).
 * Só o que interessa a quem comprou: situação, itens, valores pagos e rastreio.
 * Nada interno (custo do frete, problemas da expedição, observações).
 */
class OrderPresenter
{
    /** Etapa interna → linha do tempo do app. */
    public const SITUACOES = [
        'aguardando_pagamento' => 'Aguardando pagamento',
        'pago' => 'Pagamento confirmado',
        'em_separacao' => 'Preparando o envio',
        'a_caminho' => 'A caminho',
        'entregue' => 'Entregue',
        'cancelado' => 'Cancelado',
        'problema' => 'Precisa de atenção',
        'concluido_digital' => 'Disponível na estante',
    ];

    public static function situacao(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::New, OrderStatus::AwaitingPayment => 'aguardando_pagamento',
            OrderStatus::Paid => 'pago',
            OrderStatus::Picking, OrderStatus::Checked, OrderStatus::LabelGenerated, OrderStatus::ReadyToShip => 'em_separacao',
            OrderStatus::Shipped => 'a_caminho',
            OrderStatus::Delivered => 'entregue',
            OrderStatus::Cancelled => 'cancelado',
            OrderStatus::Problem => 'problema',
            OrderStatus::Fulfilled => 'concluido_digital',
        };
    }

    /** Nome do canal como o leitor reconhece (não o nome interno da integração). */
    public static function canal(?Channel $channel): string
    {
        return match ($channel?->slug) {
            Channel::WOOCOMMERCE => 'Loja Conexão Editora',
            Channel::PAGARME => 'Página de venda da editora',
            Channel::AMAZON => 'Amazon',
            default => 'Conexão Editora',
        };
    }

    public static function present(Order $order): array
    {
        $situacao = self::situacao($order->status);
        $shipment = $order->shipment;

        return [
            'ref' => 'hub-'.$order->id,
            'numero' => self::numero($order),
            'canal' => self::canal($order->channel),
            'feito_em' => self::iso($order->placed_at ?? $order->created_at),
            'total_centavos' => (int) $order->total_cents,
            'situacao' => $situacao,
            'situacao_rotulo' => self::SITUACOES[$situacao],
            'pagamento' => match ($order->payment_status) {
                'paid' => 'pago',
                'refunded' => 'estornado',
                default => 'pendente',
            },
            'digital' => ! $order->requires_shipping,
            'itens' => $order->items->map(fn (OrderItem $item) => [
                'nome' => $item->product?->name ?? $item->name,
                'quantidade' => (int) $item->quantity,
                'formato' => self::formato($item),
                'livro_id' => null,
            ])->values()->all(),
            'envio' => $order->requires_shipping ? self::envio($shipment) : null,
            'eventos' => self::eventos($order, $shipment, $situacao),
        ];
    }

    private static function numero(Order $order): string
    {
        $n = (string) ($order->external_number ?: $order->external_id);

        return $order->channel?->slug === Channel::WOOCOMMERCE && ctype_digit(ltrim($n, '#')) ? '#'.ltrim($n, '#') : $n;
    }

    private static function formato(OrderItem $item): ?string
    {
        return match ($item->format) {
            'ebook' => 'E-book',
            'fisico' => 'Impresso',
            'fisico_ebook' => Product::FORMATS['fisico_ebook'],
            default => null,
        };
    }

    private static function envio(?Shipment $shipment): ?array
    {
        if (! $shipment || $shipment->status === 'cancelled') {
            return null;
        }

        return [
            'transportadora' => $shipment->carrier,
            'servico' => $shipment->service,
            'codigo' => $shipment->tracking_code,
            'url_rastreio' => Shipment::trackingUrl($shipment->tracking_code),
            'postado_em' => self::iso($shipment->shipped_at),
            'entregue_em' => self::iso($shipment->delivered_at),
            'previsao' => null,
        ];
    }

    /** Linha do tempo com o que já aconteceu, na ordem. */
    private static function eventos(Order $order, ?Shipment $shipment, string $situacao): array
    {
        $eventos = [['situacao' => 'aguardando_pagamento', 'rotulo' => 'Pedido recebido', 'em' => self::iso($order->placed_at ?? $order->created_at)]];

        if ($order->paid_at || $order->payment_status === 'paid') {
            $eventos[] = ['situacao' => 'pago', 'rotulo' => self::SITUACOES['pago'], 'em' => self::iso($order->paid_at)];
        }
        if ($shipment?->label_generated_at) {
            $eventos[] = ['situacao' => 'em_separacao', 'rotulo' => self::SITUACOES['em_separacao'], 'em' => self::iso($shipment->label_generated_at)];
        }
        if ($shipment?->shipped_at) {
            $eventos[] = ['situacao' => 'a_caminho', 'rotulo' => 'Enviado', 'em' => self::iso($shipment->shipped_at)];
        }
        if ($shipment?->delivered_at) {
            $eventos[] = ['situacao' => 'entregue', 'rotulo' => self::SITUACOES['entregue'], 'em' => self::iso($shipment->delivered_at)];
        }
        if (in_array($situacao, ['cancelado', 'concluido_digital', 'problema'], true)) {
            $eventos[] = ['situacao' => $situacao, 'rotulo' => self::SITUACOES[$situacao], 'em' => null];
        }

        return $eventos;
    }

    private static function iso(?Carbon $date): ?string
    {
        return $date?->copy()->setTimezone('America/Sao_Paulo')->toIso8601String();
    }
}
