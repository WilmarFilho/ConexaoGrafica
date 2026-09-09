<?php

namespace App\Filament\Resources\SyncLogs\Pages;

use App\Filament\Resources\SyncLogs\SyncLogResource;
use Filament\Resources\Pages\ListRecords;

class ListSyncLogs extends ListRecords
{
    protected static string $resource = SyncLogResource::class;

    protected static ?string $title = 'Auditoria';

    public function getSubheading(): ?string
    {
        return 'Cada entrada de canal, retorno ao canal e mudança manual, com quem fez e quando.';
    }
}
