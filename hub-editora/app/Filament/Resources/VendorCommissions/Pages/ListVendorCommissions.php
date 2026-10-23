<?php

namespace App\Filament\Resources\VendorCommissions\Pages;

use App\Filament\Resources\VendorCommissions\VendorCommissionResource;
use Filament\Resources\Pages\ListRecords;

class ListVendorCommissions extends ListRecords
{
    protected static string $resource = VendorCommissionResource::class;

    public function getSubheading(): ?string
    {
        return 'Uma linha por pedido e livro de vendedor. Nascem com o pedido, ficam "a repassar" quando o cliente paga e somem ou viram estorno se o pedido cair.';
    }
}
