<?php

namespace App\Vendors;

use App\Models\SyncLog;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorCommission;
use App\Models\VendorPayout;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Repasses: junta as comissões a repassar de um vendedor até uma data, depois
 * registra o pagamento (ou cancela e as comissões voltam a ficar livres).
 * Tudo auditado.
 */
class Payouts
{
    public static function make(): self
    {
        return new self;
    }

    public function create(Vendor $vendor, CarbonInterface $until, ?User $user = null): VendorPayout
    {
        return DB::transaction(function () use ($vendor, $until, $user) {
            $commissions = VendorCommission::query()
                ->where('vendor_id', $vendor->id)
                ->open()
                ->where('earned_at', '<=', $until->copy()->endOfDay())
                ->lockForUpdate()
                ->get();

            $amount = (int) $commissions->sum('amount_cents');
            if ($commissions->isEmpty() || $amount <= 0) {
                throw new RuntimeException('Nada a repassar para '.$vendor->name.' até '.$until->format('d/m/Y').'.');
            }

            $payout = VendorPayout::create([
                'vendor_id' => $vendor->id,
                'status' => VendorPayout::DRAFT,
                'period_until' => $until->toDateString(),
                'amount_cents' => $amount,
                'commissions_count' => $commissions->count(),
                'method' => $vendor->payout_method,
                'created_by' => $user?->id,
            ]);

            VendorCommission::query()->whereIn('id', $commissions->pluck('id'))->update(['payout_id' => $payout->id]);

            SyncLog::record(null, SyncLog::MANUAL, 'vendor.payout_created', $payout,
                sprintf('Repasse de %s para %s (%d comissões até %s)', $payout->amount_formatted, $vendor->name, $payout->commissions_count, $until->format('d/m/Y')),
                ['vendor_id' => $vendor->id, 'user_id' => $user?->id]);

            return $payout;
        });
    }

    public function markPaid(VendorPayout $payout, CarbonInterface $paidAt, string $method, ?string $reference, ?User $user = null, ?string $notes = null): VendorPayout
    {
        if ($payout->status !== VendorPayout::DRAFT) {
            throw new RuntimeException('Só um repasse a pagar pode ser marcado como pago.');
        }

        DB::transaction(function () use ($payout, $paidAt, $method, $reference, $user, $notes) {
            $payout->update([
                'status' => VendorPayout::PAID,
                'paid_at' => $paidAt,
                'method' => $method,
                'reference' => $reference,
                'notes' => $notes ?? $payout->notes,
                'paid_by' => $user?->id,
            ]);
            $payout->commissions()->update(['status' => VendorCommission::PAID]);
        });

        SyncLog::record(null, SyncLog::MANUAL, 'vendor.payout_paid', $payout,
            sprintf('Repasse #%d de %s para %s registrado como pago em %s', $payout->id, $payout->amount_formatted, $payout->vendor?->name, $paidAt->format('d/m/Y')),
            ['vendor_id' => $payout->vendor_id, 'method' => $method, 'reference' => $reference, 'user_id' => $user?->id]);

        return $payout->refresh();
    }

    public function cancel(VendorPayout $payout, ?User $user = null): VendorPayout
    {
        if ($payout->status !== VendorPayout::DRAFT) {
            throw new RuntimeException('Repasse pago não pode ser cancelado.');
        }

        DB::transaction(function () use ($payout) {
            $payout->commissions()->update(['payout_id' => null]);
            $payout->update(['status' => VendorPayout::CANCELLED]);
        });

        SyncLog::record(null, SyncLog::MANUAL, 'vendor.payout_cancelled', $payout,
            sprintf('Repasse #%d de %s para %s cancelado; as comissões voltaram a ficar disponíveis', $payout->id, $payout->amount_formatted, $payout->vendor?->name),
            ['vendor_id' => $payout->vendor_id, 'user_id' => $user?->id]);

        return $payout->refresh();
    }
}
