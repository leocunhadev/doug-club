<?php

namespace App\Filament\Resources\Encontros\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class EncontroForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('tema')
                    ->required(),
                TextInput::make('quem')
                    ->label('Quem')
                    ->placeholder('Com Douglas / Convidada: Marina Prado')
                    ->required(),
                DateTimePicker::make('scheduled_at')
                    ->label('Data e hora')
                    ->seconds(false)
                    ->required(),
                TextInput::make('access_url')
                    ->label('Link de acesso (Zoom/Meet)')
                    ->url()
                    ->nullable(),
                Select::make('recording_lesson_id')
                    ->label('Gravação na biblioteca')
                    ->relationship('lesson', 'title')
                    ->searchable()
                    ->nullable(),
            ]);
    }
}
