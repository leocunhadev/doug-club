<?php

namespace App\Filament\Resources\Frameworks\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FrameworkForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Código')
                    ->required(),
                TextInput::make('title')
                    ->label('Título')
                    ->required(),
                Textarea::make('description')
                    ->label('Descrição')
                    ->required(),
                TextInput::make('pdf_url')
                    ->label('URL externa')
                    ->url()
                    ->requiredWithout('pdf_path'),
                FileUpload::make('pdf_path')
                    ->label('Arquivo (PDF)')
                    ->disk('public')
                    ->directory('framework-pdfs')
                    ->acceptedFileTypes(['application/pdf'])
                    ->requiredWithout('pdf_url'),
                Select::make('lesson_id')
                    ->label('Aula vinculada')
                    ->relationship('lesson', 'title')
                    ->searchable()
                    ->nullable(),
                TextInput::make('position')
                    ->label('Posição')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
