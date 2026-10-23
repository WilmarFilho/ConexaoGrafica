<?php

namespace App\Filament\Resources\VendorPayouts\RelationManagers;

use App\Filament\Resources\VendorCommissions\VendorCommissionResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

/** Comissões que compõem o repasse. */
class PayoutCommissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'commissions';

    protected static ?string $title = 'Comissões do repasse';

    protected static ?string $modelLabel = 'comissão';

    protected static ?string $pluralModelLabel = 'comissões';

    protected static bool $isLazy = false;

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return VendorCommissionResource::table($table, withVendor: false);
    }
}
