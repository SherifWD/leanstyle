<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationGroup = 'System';
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    // protected static bool $shouldRegisterNavigation = false; // managed from Store (Users)

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                Forms\Components\Select::make('role')
                    ->label('Role')
                    ->options([
                        'admin'        => 'Admin',
                        'shop_owner'   => 'Shop Owner',
                        'delivery_boy' => 'Delivery Boy',
                    ])
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state !== 'delivery_boy') {
                            $set('store_id', null);
                            $set('is_available', false);
                        }
                    })
                    ->default('shop_owner'),

                Forms\Components\DateTimePicker::make('email_verified_at'),

                Forms\Components\TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->dehydrateStateUsing(fn($state) => filled($state) ? $state : null)
                    ->dehydrated(fn($state) => filled($state))
                    ->required(fn(string $context) => $context === 'create')
                    ->maxLength(255),

                Forms\Components\TextInput::make('phone')
                    ->tel()
                    ->maxLength(255)
                    ->default(null),

                Forms\Components\Toggle::make('is_blocked')
                    ->label('Blocked')
                    ->default(false),

                Forms\Components\DateTimePicker::make('blocked_at'),

                Forms\Components\Toggle::make('is_available')
                    ->label('Available for Delivery')
                    ->default(false)
                    ->visible(fn(callable $get) => $get('role') === 'delivery_boy'),

                // Store relation
                Forms\Components\Select::make('store_id')
                    ->label('Store')
                    ->relationship('store', 'name')
                    ->searchable()
                    ->preload()
                    ->default(null)
                    ->hidden(fn(callable $get) => $get('role') !== 'delivery_boy')
                    ->required(fn(callable $get) => $get('role') === 'delivery_boy'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable(),

                Tables\Columns\TextColumn::make('role'),

                Tables\Columns\TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_blocked')
                    ->boolean(),

                Tables\Columns\TextColumn::make('blocked_at')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_available')
                    ->boolean(),

                // Store relation in table
                Tables\Columns\TextColumn::make('store.name')
                    ->label('Store')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options([
                        'admin' => 'Admin',
                        'shop_owner' => 'Shop Owner',
                        'delivery_boy' => 'Delivery Boy',
                    ]),
                TernaryFilter::make('is_blocked')
                    ->label('Blocked'),
                TernaryFilter::make('is_available')
                    ->label('Available for Delivery'),
                SelectFilter::make('store_id')
                    ->label('Store')
                    ->relationship('store', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            UserResource\RelationManagers\OwnedStoresRelationManager::class,
            UserResource\RelationManagers\ShopRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
