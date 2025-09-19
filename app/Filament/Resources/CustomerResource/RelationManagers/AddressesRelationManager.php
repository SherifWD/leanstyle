<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AddressesRelationManager extends RelationManager
{
    protected static string $relationship = 'addresses';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->maxLength(255)->nullable(),
            Forms\Components\TextInput::make('phone')->maxLength(50)->nullable(),
            Forms\Components\TextInput::make('label')->maxLength(120)->nullable(),
            Forms\Components\Textarea::make('address_line')->columnSpanFull()->rows(2),
            Forms\Components\TextInput::make('lat')->numeric()->nullable(),
            Forms\Components\TextInput::make('lng')->numeric()->nullable(),
            Forms\Components\Toggle::make('is_default')->label('Default')->default(false),
            Forms\Components\Toggle::make('is_verified')->label('Verified')->default(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label')->label('Label')->searchable(),
                Tables\Columns\TextColumn::make('name')->label('Name')->searchable(),
                Tables\Columns\TextColumn::make('phone')->label('Phone')->searchable(),
                Tables\Columns\TextColumn::make('address_line')->label('Address')->wrap(),
                Tables\Columns\TextColumn::make('lat')->numeric(),
                Tables\Columns\TextColumn::make('lng')->numeric(),
                Tables\Columns\IconColumn::make('is_default')->boolean()->label('Default'),
                Tables\Columns\IconColumn::make('is_verified')->boolean()->label('Verified'),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}

