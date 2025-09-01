<?php

namespace App\Filament\Resources\DriverAvailabilityResource\Pages;

use App\Filament\Resources\DriverAvailabilityResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDriverAvailability extends EditRecord
{
    protected static string $resource = DriverAvailabilityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
