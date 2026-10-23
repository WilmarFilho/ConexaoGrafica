<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\Product;
use App\Models\ProductChannelRef;
use App\Models\SyncLog;
use App\Models\Vendor;
use App\Vendors\Commissions;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Traz os vendedores do MVX (loja WooCommerce) para o hub, a partir de um JSON
 * exportado da loja:
 *
 *   [{"mvx_id": 36, "name": "...", "email": "...", "commission_pct": 65,
 *     "products": [{"woo_id": 4418, "commission_pct": null}]}]
 *
 * Casa cada produto pelo vínculo do canal WooCommerce. Reexecutar atualiza
 * (chave: mvx_id) sem duplicar. `--desde` vira o "comissões a partir de" de
 * todos: o que veio antes foi acertado pelo MVX.
 */
class VendorsImportMvxCommand extends Command
{
    protected $signature = 'hub:vendors-import-mvx {arquivo : JSON exportado do MVX} {--desde= : Data (AAAA-MM-DD) a partir da qual o hub calcula comissões} {--dry-run : Só mostra o que faria}';

    protected $description = 'Importa vendedores e livros do MVX (WooCommerce) para o multivendor do hub';

    public function handle(): int
    {
        $rows = json_decode((string) @file_get_contents($this->argument('arquivo')), true);
        if (! is_array($rows)) {
            $this->error('Arquivo inválido ou não encontrado.');

            return self::FAILURE;
        }

        $since = $this->option('desde') ? Carbon::parse($this->option('desde'))->toDateString() : null;
        $woo = Channel::bySlug(Channel::WOOCOMMERCE);
        $dry = (bool) $this->option('dry-run');

        foreach ($rows as $row) {
            $vendor = Vendor::query()->firstOrNew(['mvx_vendor_id' => (int) $row['mvx_id']]);
            $vendor->fill(array_filter([
                'name' => $vendor->name ?: ($row['name'] ?? null),
                'email' => $vendor->email ?: ($row['email'] ?? null),
                'commission_pct' => $vendor->exists ? null : ($row['commission_pct'] ?? Vendor::DEFAULT_COMMISSION_PCT),
                'commissions_start_at' => $since,
            ], fn ($v) => $v !== null));

            $this->line(($vendor->exists ? 'Atualiza ' : 'Cria ').$vendor->name.' (MVX '.$row['mvx_id'].')');
            if (! $dry) {
                $vendor->saveQuietly(); // as comissões são recalculadas uma vez, no fim
            }

            foreach ($row['products'] ?? [] as $p) {
                $productId = $woo ? ProductChannelRef::query()->where('channel_id', $woo->id)->where('external_id', (string) $p['woo_id'])->value('product_id') : null;
                $product = $productId ? Product::find($productId) : null;
                if (! $product) {
                    $this->warn("  produto Woo {$p['woo_id']} não está no catálogo do hub");

                    continue;
                }
                $this->line("  livro: {$product->name}".(isset($p['commission_pct']) && $p['commission_pct'] !== null ? " ({$p['commission_pct']}%)" : ''));
                if (! $dry) {
                    $product->forceFill([
                        'vendor_id' => $vendor->id,
                        'vendor_commission_pct' => $p['commission_pct'] ?? null,
                    ])->saveQuietly();
                }
            }

            if (! $dry) {
                Commissions::make()->syncVendor($vendor);
                SyncLog::record($woo, SyncLog::IN, 'vendor.imported', $vendor, 'Vendedor importado do MVX: '.$vendor->name,
                    ['mvx_id' => (int) $row['mvx_id'], 'products' => count($row['products'] ?? []), 'since' => $since]);
            }
        }

        return self::SUCCESS;
    }
}
