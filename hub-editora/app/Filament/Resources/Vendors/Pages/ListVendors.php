<?php

namespace App\Filament\Resources\Vendors\Pages;

use App\Filament\Resources\Vendors\PayoutActions;
use App\Filament\Resources\Vendors\VendorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVendors extends ListRecords
{
    protected static string $resource = VendorResource::class;

    public function getSubheading(): ?string
    {
        return 'Autores e parceiros que vendem pela editora. Cada venda dos livros deles gera comissão; as comissões pagas pelo cliente entram no repasse.';
    }

    protected function getHeaderActions(): array
    {
        return [
            PayoutActions::create()->color('gray'),
            CreateAction::make()->label('Novo vendedor'),
        ];
    }
}
