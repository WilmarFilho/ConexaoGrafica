<?php

namespace App\Filament\Resources\People\Pages;

use App\Filament\Resources\People\PersonResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePeople extends ManageRecords
{
    protected static string $resource = PersonResource::class;

    protected static ?string $title = 'Pessoas';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nova pessoa')->slideOver(),
        ];
    }
}
