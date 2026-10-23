<?php

namespace App\Filament\Resources\VendorPayouts\Pages;

use App\Filament\Resources\VendorPayouts\VendorPayoutResource;
use App\Filament\Resources\Vendors\PayoutActions;
use App\Models\VendorPayout;
use Filament\Resources\Pages\ViewRecord;

class ViewVendorPayout extends ViewRecord
{
    protected static string $resource = VendorPayoutResource::class;

    public function getTitle(): string
    {
        /** @var VendorPayout $payout */
        $payout = $this->getRecord();

        return 'Repasse #'.$payout->id.' · '.$payout->vendor?->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            PayoutActions::markPaid(),
            PayoutActions::cancel(),
        ];
    }
}
