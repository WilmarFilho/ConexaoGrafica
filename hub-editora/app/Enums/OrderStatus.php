<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Status interno do pedido. Todo canal é traduzido para estes valores na
 * entrada; a volta (hub → canal) usa o mapa de cada integração.
 */
enum OrderStatus: string implements HasColor, HasLabel
{
    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return $this->color();
    }

    case New = 'new';                       // recebido, ainda sem pagamento confirmado
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';                     // pago, aguardando separação
    case Picking = 'picking';               // em separação
    case Checked = 'checked';               // conferido (código de barras)
    case LabelGenerated = 'label_generated';
    case ReadyToShip = 'ready_to_ship';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Problem = 'problem';               // precisa de intervenção humana
    case Fulfilled = 'fulfilled';           // só e-books: nada a expedir

    public function label(): string
    {
        return match ($this) {
            self::New => 'Novo',
            self::AwaitingPayment => 'Aguardando pagamento',
            self::Paid => 'Pago · aguardando envio',
            self::Picking => 'Em separação',
            self::Checked => 'Conferido',
            self::LabelGenerated => 'Etiqueta gerada',
            self::ReadyToShip => 'Pronto para despacho',
            self::Shipped => 'Enviado',
            self::Delivered => 'Entregue',
            self::Cancelled => 'Cancelado',
            self::Problem => 'Problema',
            self::Fulfilled => 'Concluído (digital)',
        };
    }

    /** Cor semântica usada pelo painel (Filament). */
    public function color(): string
    {
        return match ($this) {
            self::Paid, self::Picking, self::Checked => 'warning',
            self::LabelGenerated, self::ReadyToShip => 'info',
            self::Shipped, self::Delivered, self::Fulfilled => 'success',
            self::Cancelled, self::Problem => 'danger',
            default => 'gray',
        };
    }

    /** Pedidos que aparecem na fila "A separar" da Central de Expedição. */
    public static function awaitingShipment(): array
    {
        return [self::Paid, self::Picking, self::Checked];
    }
}
