<?php

namespace App\Filament\Resources\StoreResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_code')->label('Code')->searchable(),
                Tables\Columns\TextColumn::make('customer.name')->label('Customer')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('grand_total')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\ViewAction::make()->url(fn($r)=>route('filament.admin.resources.orders.edit',['record'=>$r]))->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([]),
            ]);
    }
}

