<?php

namespace App\Filament\Pages;

use App\Filament\Resources\VendorCommissions\VendorCommissionResource;
use App\Models\Vendor;
use App\Models\VendorCommission;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Portal do vendedor: as vendas dos livros dele e a comissão de cada uma.
 * Só para contas com perfil Vendedor; tudo filtrado pelo vendedor da conta.
 * Sem dados de cliente (nome, endereço, contato): só número do pedido e canal.
 */
class VendorPortal extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Minhas vendas';

    protected static ?string $title = 'Minhas vendas';

    protected static ?string $slug = 'minhas-vendas';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.vendedor';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) $user?->isVendor() && $user->vendor_id !== null;
    }

    public function getSubheading(): ?string
    {
        $vendor = $this->vendor();
        $pct = number_format((float) $vendor->commission_pct, 2, ',', '.');

        return "{$vendor->name}: sua comissão é de {$pct}% do valor dos livros vendidos (sem frete). A comissão fica disponível quando o cliente paga e entra no repasse seguinte.";
    }

    public function vendor(): Vendor
    {
        return Vendor::findOrFail(auth()->user()->vendor_id);
    }

    /** Números do topo. */
    public function getStats(): array
    {
        $vendor = $this->vendor();
        $month = VendorCommission::query()->where('vendor_id', $vendor->id)
            ->whereIn('status', [VendorCommission::AVAILABLE, VendorCommission::PAID])
            ->where('kind', VendorCommission::SALE)
            ->where('earned_at', '>=', now()->startOfMonth());

        return [
            ['label' => 'Livros vendidos no mês', 'value' => (string) (int) (clone $month)->sum('quantity'), 'tone' => 'info'],
            ['label' => 'Comissão do mês', 'value' => Vendor::money((int) (clone $month)->sum('amount_cents')), 'tone' => 'info'],
            ['label' => 'A receber', 'value' => Vendor::money($vendor->availableCents() + (int) $vendor->commissions()->where('status', VendorCommission::AVAILABLE)->whereNotNull('payout_id')->sum('amount_cents')), 'tone' => 'warning'],
            ['label' => 'Aguardando pagamento do cliente', 'value' => Vendor::money($vendor->pendingCents()), 'tone' => 'warning'],
            ['label' => 'Já recebido', 'value' => Vendor::money($vendor->paidCents()), 'tone' => 'success'],
        ];
    }

    public function table(Table $table): Table
    {
        return VendorCommissionResource::table(
            $table->query(VendorCommission::query()->where('vendor_id', auth()->user()->vendor_id)),
            withVendor: false,
            linkOrders: false,
        );
    }
}
