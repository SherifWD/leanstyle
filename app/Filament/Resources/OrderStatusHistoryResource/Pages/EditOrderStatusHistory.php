<?php

namespace App\Filament\Resources\OrderStatusHistoryResource\Pages;

use App\Filament\Resources\OrderStatusHistoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrderStatusHistory extends EditRecord
{
    protected static string $resource = OrderStatusHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
