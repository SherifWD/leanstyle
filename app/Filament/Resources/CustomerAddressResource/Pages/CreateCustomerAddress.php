<?php

namespace App\Filament\Resources\CustomerAddressResource\Pages;

use App\Filament\Resources\CustomerAddressResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomerAddress extends CreateRecord
{
    protected static string $resource = CustomerAddressResource::class;
}
