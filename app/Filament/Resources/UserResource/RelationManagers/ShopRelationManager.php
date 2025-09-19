<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\AssociateAction;
use Filament\Tables\Actions\DissociateAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ShopRelationManager extends RelationManager
{
    protected static string $relationship = 'store';
    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if ($ownerRecord->role !== 'delivery_boy') {
            return false;
        }

        return parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('city')
                    ->sortable()
                    ->label('City'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
            ])
            ->headerActions([
                AssociateAction::make()
                    ->modalHeading('Assign Store')
                    ->modalButton('Assign')
                    ->recordSelectOptionsQuery(fn($query) => $query->orderBy('name'))
                    ->visible(fn(): bool => ! $this->getOwnerRecord()->store()->exists()),
            ])
            ->actions([
                AssociateAction::make('change')
                    ->label('Change')
                    ->modalHeading('Change Store')
                    ->modalButton('Save')
                    ->visible(fn($record) => (bool) $record),
                DissociateAction::make()
                    ->label('Remove')
                    ->requiresConfirmation()
                    ->visible(fn($record) => (bool) $record),
            ])
            ->emptyStateHeading('No store assigned');
    }
}
