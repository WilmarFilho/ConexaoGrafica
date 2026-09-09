<?php

namespace App\Filament\Actions;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\SyncLog;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Mudança manual de etapa, com motivo obrigatório e trilha de auditoria.
 * Por padrão trava a etapa: o sync do canal deixa de sobrescrevê-la até
 * alguém liberar de novo por aqui.
 */
class ChangeOrderStatusAction
{
    public static function make(string $name = 'alterar_etapa'): Action
    {
        return Action::make($name)
            ->label('Alterar etapa')
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('gray')
            ->modalHeading(fn (Order $record) => 'Alterar etapa do pedido #'.($record->external_number ?: $record->external_id))
            ->modalSubmitActionLabel('Aplicar')
            ->modalWidth('md')
            ->fillForm(fn (Order $record) => [
                'status' => $record->status->value,
                'lock' => true,
            ])
            ->schema([
                Select::make('status')
                    ->label('Nova etapa')
                    ->options(OrderStatus::class)
                    ->required()
                    ->native(false),
                Textarea::make('reason')
                    ->label('Motivo')
                    ->required()
                    ->minLength(5)
                    ->maxLength(500)
                    ->rows(3)
                    ->placeholder('Ex.: cliente retirou na editora; entregue em mãos.'),
                Toggle::make('lock')
                    ->label('Manter esta etapa mesmo que o canal atualize')
                    ->helperText('Desligue para o pedido voltar a seguir o status da loja/landing.'),
            ])
            ->action(function (Order $record, array $data) {
                $from = $record->status;
                $to = OrderStatus::from($data['status']);
                $lock = (bool) ($data['lock'] ?? true);

                $record->update(['status' => $to, 'status_manual' => $lock]);

                SyncLog::record(
                    $record->channel,
                    SyncLog::MANUAL,
                    $from === $to && ! $lock ? 'status.unlocked' : 'status.changed',
                    $record,
                    $from === $to
                        ? ($lock ? "Etapa mantida em \"{$to->label()}\": {$data['reason']}" : "Etapa liberada para o canal: {$data['reason']}")
                        : "{$from->label()} → {$to->label()}: {$data['reason']}",
                    ['from' => $from->value, 'to' => $to->value, 'locked' => $lock, 'reason' => $data['reason']],
                );

                Notification::make()
                    ->title('Etapa atualizada')
                    ->body($to->label().($lock ? ' · travada contra o canal' : ' · segue o canal'))
                    ->success()
                    ->send();
            });
    }
}
