<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class AssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignment';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('driver_id')->relationship('driver', 'name')->searchable()->preload()->required(),
            Forms\Components\Select::make('assigned_by')->relationship('assignedBy', 'name')->searchable()->preload()->required(),
            Forms\Components\DateTimePicker::make('assigned_at'),
            Forms\Components\DateTimePicker::make('accepted_at'),
            Forms\Components\DateTimePicker::make('started_at'),
            Forms\Components\DateTimePicker::make('picked_at'),
            Forms\Components\DateTimePicker::make('out_for_delivery_at'),
            Forms\Components\DateTimePicker::make('completed_at'),
            Forms\Components\DateTimePicker::make('rejected_at'),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('driver.name')->label('Driver'),
                Tables\Columns\TextColumn::make('assignedBy.name')->label('Assigned By'),
                Tables\Columns\TextColumn::make('assigned_at')->dateTime(),
                Tables\Columns\TextColumn::make('accepted_at')->dateTime(),
                Tables\Columns\TextColumn::make('completed_at')->dateTime(),
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

