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
                    ->required(),
                Textarea::make('description')
                    ->required(),
                TextInput::make('pdf_url')
                    ->label('External URL')
                    ->url()
                    ->requiredWithout('pdf_path'),
                FileUpload::make('pdf_path')
                    ->label('File (PDF)')
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
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
