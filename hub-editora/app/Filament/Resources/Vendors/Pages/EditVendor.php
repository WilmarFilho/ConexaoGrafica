<?php

namespace App\Filament\Resources\Vendors\Pages;

use App\Filament\Resources\Vendors\PayoutActions;
use App\Filament\Resources\Vendors\VendorResource;
use App\Models\SyncLog;
use App\Models\Vendor;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVendor extends EditRecord
{
    protected static string $resource = VendorResource::class;

    protected function getHeaderActions(): array
    {
        /** @var Vendor $vendor */
        $vendor = $this->getRecord();

        return [
            PayoutActions::create($vendor),
            DeleteAction::make()
                ->label('Remover')
                // com comissão ou repasse o histórico precisa ficar: desative em vez de remover
                ->visible(fn (Vendor $record) => ! $record->commissions()->exists() && ! $record->payouts()->exists()),
        ];
    }

    protected function afterSave(): void
    {
        /** @var Vendor $vendor */
        $vendor = $this->getRecord();
        if ($vendor->wasChanged(['commission_pct', 'commissions_start_at', 'payout_details', 'payout_method', 'document'])) {
            SyncLog::record(null, SyncLog::MANUAL, 'vendor.updated', $vendor,
                'Dados de comissão/repasse de '.$vendor->name.' alterados',
                ['changed' => array_keys(array_intersect_key($vendor->getChanges(), array_flip(['commission_pct', 'commissions_start_at', 'payout_details', 'payout_method', 'document'])))]);
        }
    }
}
