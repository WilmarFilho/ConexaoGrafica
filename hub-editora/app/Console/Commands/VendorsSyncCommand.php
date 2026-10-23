<?php

namespace App\Console\Commands;

use App\Models\Vendor;
use App\Vendors\Commissions;
use Illuminate\Console\Command;

/** Recalcula as comissões (idempotente): depois de ligar livros, mudar % ou data de início. */
class VendorsSyncCommand extends Command
{
    protected $signature = 'hub:vendors-sync {--vendor= : Id do vendedor (padrão: todos)}';

    protected $description = 'Recalcula as comissões dos vendedores a partir dos pedidos';

    public function handle(): int
    {
        $vendors = Vendor::query()->when($this->option('vendor'), fn ($q, $id) => $q->whereKey($id))->get();

        foreach ($vendors as $vendor) {
            $orders = Commissions::make()->syncVendor($vendor);
            $this->line(sprintf('%s: %d pedido(s); a repassar %s, aguardando pagamento %s',
                $vendor->name, $orders, Vendor::money($vendor->availableCents()), Vendor::money($vendor->pendingCents())));
        }

        return self::SUCCESS;
    }
}
