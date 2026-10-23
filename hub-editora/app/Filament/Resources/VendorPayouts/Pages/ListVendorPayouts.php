<?php

namespace App\Filament\Resources\VendorPayouts\Pages;

use App\Filament\Resources\VendorPayouts\VendorPayoutResource;
use App\Filament\Resources\Vendors\PayoutActions;
use Filament\Resources\Pages\ListRecords;

class ListVendorPayouts extends ListRecords
{
    protected static string $resource = VendorPayoutResource::class;

    public function getSubheading(): ?string
    {
        return 'Gere o repasse, faça a transferência ao vendedor e registre o pagamento aqui.';
    }

    protected function getHeaderActions(): array
    {
        return [
            PayoutActions::create(),
        ];
    }
}
