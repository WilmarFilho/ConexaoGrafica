<?php

namespace App\Filament\Pages;

use App\Filament\Resources\VendorPayouts\VendorPayoutResource;
use App\Models\Vendor;
use App\Models\VendorPayout;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/** Portal do vendedor: os repasses que a editora fez (ou vai fazer) a ele. */
class VendorPayoutsPortal extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Meus repasses';

    protected static ?string $title = 'Meus repasses';

    protected static ?string $slug = 'meus-repasses';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.vendedor';

    public static function canAccess(): bool
    {
        return VendorPortal::canAccess();
    }

    public function getSubheading(): ?string
    {
        return 'Cada repasse junta as comissões disponíveis até uma data. "A pagar" significa que a transferência ainda vai ser feita.';
    }

    public function getStats(): array
    {
        $vendorId = auth()->user()->vendor_id;
        $paid = VendorPayout::query()->where('vendor_id', $vendorId)->where('status', VendorPayout::PAID);
        $last = (clone $paid)->latest('paid_at')->first();

        return [
            ['label' => 'A pagar', 'value' => Vendor::money((int) VendorPayout::query()->where('vendor_id', $vendorId)->where('status', VendorPayout::DRAFT)->sum('amount_cents')), 'tone' => 'warning'],
            ['label' => 'Recebido no ano', 'value' => Vendor::money((int) (clone $paid)->whereYear('paid_at', now()->year)->sum('amount_cents')), 'tone' => 'success'],
            ['label' => 'Último repasse', 'value' => $last ? $last->paid_at->format('d/m/Y') : '—', 'tone' => 'info'],
        ];
    }

    public function table(Table $table): Table
    {
        return VendorPayoutResource::table(
            $table->query(VendorPayout::query()->where('vendor_id', auth()->user()->vendor_id)->where('status', '!=', VendorPayout::CANCELLED)),
            withVendor: false,
        );
    }
}
