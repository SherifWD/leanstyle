<?php

namespace App\Filament\Resources\DriverCashLedgerResource\Pages;

use App\Filament\Resources\DriverCashLedgerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDriverCashLedger extends EditRecord
{
    protected static string $resource = DriverCashLedgerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
