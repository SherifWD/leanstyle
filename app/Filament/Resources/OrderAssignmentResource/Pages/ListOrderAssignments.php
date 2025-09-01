<?php

namespace App\Filament\Resources\OrderAssignmentResource\Pages;

use App\Filament\Resources\OrderAssignmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOrderAssignments extends ListRecords
{
    protected static string $resource = OrderAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
