<?php

namespace App\Filament\Resources\OrderAssignmentResource\Pages;

use App\Filament\Resources\OrderAssignmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrderAssignment extends EditRecord
{
    protected static string $resource = OrderAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
