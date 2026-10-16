<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\SyncLog;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'Usuários';

    public function getSubheading(): ?string
    {
        $ultima = SyncLog::query()->where('action', 'access.review.confirmed')->latest('id')->first();

        return 'Quem entra no painel e recebe os avisos. Cada pessoa define a própria senha pelo link enviado por e-mail. '
            .($ultima ? 'Última revisão de acessos: '.$ultima->created_at->format('d/m/Y').'.' : 'Nenhuma revisão de acessos registrada ainda.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('revisao')
                ->label('Registrar revisão de acessos')
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color('gray')
                ->modalHeading('Registrar revisão trimestral de acessos')
                ->modalDescription('Confirme que conferiu cada usuário da lista: quem não precisa mais foi desativado e os perfis estão corretos.')
                ->schema([
                    Textarea::make('notas')->label('Observações (opcional)')->rows(3)->maxLength(1000),
                ])
                ->action(function (array $data) {
                    SyncLog::record(null, SyncLog::MANUAL, 'access.review.confirmed', null,
                        'Revisão de acessos confirmada'.(filled($data['notas'] ?? null) ? ': '.$data['notas'] : ''));
                    Notification::make()->title('Revisão registrada')->success()->send();
                }),
            UserResource::createAction(),
        ];
    }
}
