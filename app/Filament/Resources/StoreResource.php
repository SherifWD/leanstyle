<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StoreResource\Pages;
use App\Models\Store;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StoreResource extends Resource
{
    protected static ?string $model = Store::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Owner relation
                Forms\Components\Select::make('owner_id')
                    ->label('Owner')
                    ->relationship('owner', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(255),

                Forms\Components\FileUpload::make('logo_path')
                    ->label('Logo')
                    ->image()
                    ->disk('local')
                    ->directory('store')
                    ->visibility('public')
                    ->imageEditor()
                    ->afterStateHydrated(function ($component, $state, $record) {
                        if ($record) {
                            $component->state($record->getRawOriginal('logo_path'));
                        }
                    }),

                Forms\Components\TextInput::make('brand_color')
                    ->maxLength(255)
                    ->default(null),

                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('address')
                    ->maxLength(255)
                    ->default(null),

                Forms\Components\TextInput::make('lat')
                    ->numeric()
                    ->default(null),

                Forms\Components\TextInput::make('lng')
                    ->numeric()
                    ->default(null),

                Forms\Components\Toggle::make('is_active')
                    ->required(),

                Forms\Components\Textarea::make('delivery_settings')
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('country')
                    ->maxLength(255)
                    ->default(null),

                Forms\Components\TextInput::make('city')
                    ->maxLength(255)
                    ->default(null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo_path')
                    ->label('Logo')
                    ->getStateUsing(fn($record) => $record->logo_path)
                    ->square(),
                Tables\Columns\TextColumn::make('owner.name')
                    ->label('Owner')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),


                Tables\Columns\TextColumn::make('brand_color')
                    ->searchable(),

                Tables\Columns\TextColumn::make('address')
                    ->searchable(),

                Tables\Columns\TextColumn::make('lat')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('lng')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('country')
                    ->searchable(),

                Tables\Columns\TextColumn::make('city')
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
            StoreResource\RelationManagers\BusinessHoursRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStores::route('/'),
            'create' => Pages\CreateStore::route('/create'),
            'edit' => Pages\EditStore::route('/{record}/edit'),
        ];
    }
}
