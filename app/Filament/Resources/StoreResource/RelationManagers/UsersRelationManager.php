<?php

namespace App\Filament\Resources\StoreResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(190),
            Forms\Components\TextInput::make('email')->email()->maxLength(190)->nullable(),
            Forms\Components\TextInput::make('phone')->maxLength(30)->nullable(),
            Forms\Components\Select::make('role')->options([
                'shop_owner'   => 'Shop Owner',
                'delivery_boy' => 'Delivery Boy',
                'admin'        => 'Admin',
            ])->required(),
            Forms\Components\TextInput::make('password')
                ->password()
                ->revealable()
                ->dehydrateStateUsing(fn($state) => filled($state) ? $state : null)
                ->dehydrated(fn($state) => filled($state))
                ->required(fn(string $context) => $context === 'create'),
            Forms\Components\Toggle::make('is_blocked')->label('Blocked')->default(false),
            Forms\Components\Toggle::make('is_available')->label('Available')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('phone')->searchable(),
                Tables\Columns\BadgeColumn::make('role'),
                Tables\Columns\IconColumn::make('is_available')->boolean()->label('Available'),
                Tables\Columns\IconColumn::make('is_blocked')->boolean()->label('Blocked'),
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

