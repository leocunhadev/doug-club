<?php

namespace App\Filament\Resources\MentorSessions\Tables;

use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MentorSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('member.name')
                    ->label('Membro')
                    ->searchable(),
                TextColumn::make('scheduled_at')
                    ->label('Data/hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('cancelled_at')
                    ->label('Status')
                    ->formatStateUsing(fn (?string $state) => $state ? 'Cancelada' : 'Confirmada')
                    ->badge()
                    ->color(fn (?string $state) => $state ? 'gray' : 'success'),
            ])
            ->defaultSort('scheduled_at', 'desc')
            ->recordActions([
                DeleteAction::make(),
            ]);
    }
}
