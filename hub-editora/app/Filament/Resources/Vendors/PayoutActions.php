<?php

namespace App\Filament\Resources\Vendors;

use App\Filament\Resources\VendorPayouts\VendorPayoutResource;
use App\Models\Vendor;
use App\Models\VendorPayout;
use App\Vendors\Payouts;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use RuntimeException;

/** Ações de repasse usadas na tela do vendedor e na lista/tela de repasses. */
class PayoutActions
{
    /** Gera o repasse de um vendedor (fixo, ou escolhido no formulário quando $vendor é null). */
    public static function create(?Vendor $vendor = null): Action
    {
        return Action::make('gerar_repasse')
            ->label('Gerar repasse')
            ->icon(Heroicon::OutlinedBanknotes)
            ->modalHeading('Gerar repasse')
            ->modalDescription('Junta as comissões a repassar até a data escolhida. O repasse fica "a pagar" até você registrar a transferência.')
            ->schema(array_values(array_filter([
                $vendor ? null : Select::make('vendor_id')->label('Vendedor')
                    ->options(fn () => Vendor::query()->orderBy('name')->get()
                        ->mapWithKeys(fn (Vendor $v) => [$v->id => $v->name.' · '.Vendor::money($v->availableCents())]))
                    ->searchable()->required(),
                DatePicker::make('until')->label('Comissões até')->default(now()->subDay()->toDateString())->maxDate(now())->required(),
            ])))
            ->action(function (array $data, Action $action) use ($vendor) {
                $target = $vendor ?? Vendor::findOrFail($data['vendor_id']);
                try {
                    $payout = Payouts::make()->create($target, Carbon::parse($data['until']), auth()->user());
                } catch (RuntimeException $e) {
                    Notification::make()->title('Nada a repassar')->body($e->getMessage())->warning()->send();
                    $action->halt();

                    return;
                }
                Notification::make()->title('Repasse gerado: '.$payout->amount_formatted)
                    ->body($payout->commissions_count.' comissão(ões). Registre o pagamento depois da transferência.')->success()->send();
                $action->redirect(VendorPayoutResource::getUrl('view', ['record' => $payout]));
            });
    }

    public static function markPaid(): Action
    {
        return Action::make('marcar_pago')
            ->label('Registrar pagamento')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (VendorPayout $record) => $record->status === VendorPayout::DRAFT)
            ->modalHeading(fn (VendorPayout $record) => 'Registrar pagamento de '.$record->amount_formatted.' para '.$record->vendor?->name)
            ->fillForm(fn (VendorPayout $record) => ['paid_at' => now(), 'method' => $record->method ?? $record->vendor?->payout_method])
            ->schema([
                DateTimePicker::make('paid_at')->label('Pago em')->seconds(false)->maxDate(now()->endOfDay())->required(),
                Select::make('method')->label('Forma')->options(VendorPayout::METHODS)->required()->native(false),
                TextInput::make('reference')->label('Identificador da transferência')->maxLength(190)
                    ->helperText('ID do PIX, número do comprovante.'),
                Textarea::make('notes')->label('Observações')->rows(2)->maxLength(1000),
            ])
            ->action(function (VendorPayout $record, array $data) {
                Payouts::make()->markPaid($record, Carbon::parse($data['paid_at']), $data['method'], $data['reference'] ?? null, auth()->user(), $data['notes'] ?? null);
                Notification::make()->title('Pagamento registrado')->success()->send();
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancelar_repasse')
            ->label('Cancelar repasse')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (VendorPayout $record) => $record->status === VendorPayout::DRAFT)
            ->requiresConfirmation()
            ->modalDescription('As comissões voltam a ficar "a repassar" e podem entrar num novo repasse.')
            ->action(function (VendorPayout $record) {
                Payouts::make()->cancel($record, auth()->user());
                Notification::make()->title('Repasse cancelado')->success()->send();
            });
    }
}
