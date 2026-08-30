<?php

namespace App\Filament\Resources\BridgeRequests\Tables;

use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BridgeRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('requester.name')
                    ->label('Quem pediu')
                    ->searchable(),
                TextColumn::make('target.name')
                    ->label('Com quem')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Quando')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                DeleteAction::make(),
            ]);
    }
}
