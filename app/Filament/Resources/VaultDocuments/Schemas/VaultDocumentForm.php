<?php

namespace App\Filament\Resources\VaultDocuments\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class VaultDocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('member_id')
                    ->label('Membro')
                    ->relationship('member', 'name', fn ($query) => $query->where('tier', 'club'))
                    ->required()
                    ->searchable(),
                TextInput::make('title')
                    ->label('Título')
                    ->required(),
                Textarea::make('description')
                    ->label('Descrição'),
                TextInput::make('file_url')
                    ->label('External URL')
                    ->url()
                    ->requiredWithout('file_path'),
                FileUpload::make('file_path')
                    ->label('File')
                    ->disk('local')
                    ->directory('vault-documents')
                    ->acceptedFileTypes([
                        'application/pdf',
                        'video/mp4',
                        'video/quicktime',
                        'video/webm',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    ])
                    ->requiredWithout('file_url'),
            ]);
    }
}
