<?php

namespace App\Filament\Resources\Vendors\RelationManagers;

use App\Filament\Resources\VendorCommissions\VendorCommissionResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

/** Comissões do vendedor (somente leitura; vêm dos pedidos). */
class CommissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'commissions';

    protected static ?string $title = 'Comissões';

    protected static ?string $modelLabel = 'comissão';

    protected static ?string $pluralModelLabel = 'comissões';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return VendorCommissionResource::table($table, withVendor: false);
    }
}
