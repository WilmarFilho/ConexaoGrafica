<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'Usuários';

    public function getSubheading(): ?string
    {
        return 'Quem entra no painel e recebe os avisos. Cada pessoa define a própria senha pelo link enviado por e-mail.';
    }

    protected function getHeaderActions(): array
    {
        return [
            UserResource::createAction(),
        ];
    }
}
