<?php

namespace App\Filament\Resources\DriverRemittanceResource\Pages;

use App\Filament\Resources\DriverRemittanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDriverRemittances extends ListRecords
{
    protected static string $resource = DriverRemittanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
