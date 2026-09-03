<?php

namespace App\Filament\Resources\Encontros\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EncontrosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tema')
                    ->label('Tema')
                    ->searchable(),
                TextColumn::make('quem')
                    ->label('Quem')
                    ->searchable(),
                TextColumn::make('scheduled_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('lesson.title')
                    ->label('Gravação')
                    ->placeholder('—'),
            ])
            ->defaultSort('scheduled_at', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
