<?php

namespace App\Filament\Resources\Vendors\RelationManagers;

use App\Filament\Resources\VendorPayouts\VendorPayoutResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

/** Repasses do vendedor; abrir leva à tela do repasse. */
class PayoutsRelationManager extends RelationManager
{
    protected static string $relationship = 'payouts';

    protected static ?string $title = 'Repasses';

    protected static ?string $modelLabel = 'repasse';

    protected static ?string $pluralModelLabel = 'repasses';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return VendorPayoutResource::table($table, withVendor: false);
    }
}
