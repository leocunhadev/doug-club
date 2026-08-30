<?php

namespace App\Filament\Resources\ClubApplications\Tables;

use App\Models\ClubApplication;
use App\Notifications\ClubApplicationApproved;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClubApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Quem aplicou')
                    ->searchable(),
                TextColumn::make('user.email')
                    ->label('E-mail')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Quando')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('approve')
                    ->label('Aprovar')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (ClubApplication $record) {
                        $record->user->update(['tier' => 'club']);
                        $record->user->notify(new ClubApplicationApproved);
                        $record->delete();
                    }),
                DeleteAction::make()
                    ->label('Recusar'),
            ]);
    }
}
