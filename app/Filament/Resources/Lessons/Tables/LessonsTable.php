<?php

namespace App\Filament\Resources\Lessons\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LessonsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('course.label')
                    ->label('Curso')
                    ->searchable(),
                TextColumn::make('number')
                    ->label('Número')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Título')
                    ->searchable(),
                TextColumn::make('published_at')
                    ->label('Publicado em')
                    ->date()
                    ->sortable(),
                TextColumn::make('position')
                    ->label('Posição')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('position', 'desc')
            ->reorderable(
                'position',
                condition: fn ($livewire): bool => filled($livewire->tableFilters['course_id']['value'] ?? null),
                direction: 'desc',
            )
            ->filters([
                SelectFilter::make('course_id')
                    ->relationship('course', 'label'),
            ])
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
