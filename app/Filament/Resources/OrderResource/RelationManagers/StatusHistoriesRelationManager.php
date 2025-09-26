<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StatusHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'statusHistories';

    public function form(Form $form): Form
    {
        $statuses = [
            'pending' => 'pending',
            'preparing' => 'preparing',
            'ready' => 'ready',
            'assigned' => 'assigned',
            'picked' => 'picked',
            'out_for_delivery' => 'out_for_delivery',
            'delivered' => 'delivered',
            'rejected' => 'rejected',
            'cancelled' => 'cancelled',
        ];

        return $form->schema([
            Forms\Components\Select::make('from_status')->options($statuses)->searchable(),
            Forms\Components\Select::make('to_status')->options($statuses)->required()->searchable(),
            Forms\Components\Select::make('changed_by')
                ->label('Changed By')
                ->relationship('changer','name')
                ->searchable()->preload()->required(),
            Forms\Components\Textarea::make('reason')->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->latest('created_at')->limit(6))
            ->columns([
                Tables\Columns\TextColumn::make('to_status')
                    ->label('Status')
                    ->badge(),
                Tables\Columns\TextColumn::make('changer.name')
                    ->label('Changed By')
                    ->placeholder('System'),
                Tables\Columns\TextColumn::make('reason')
                    ->label('Note')
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->tooltip(fn ($record) => $record->created_at?->toDayDateTimeString()),
            ])
            ->paginated(false)
            ->emptyStateHeading('No status activity yet')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Log Status Change'),
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
