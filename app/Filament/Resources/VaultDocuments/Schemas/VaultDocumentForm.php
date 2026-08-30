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
                    ->disk('public')
                    ->directory('vault-documents')
                    ->requiredWithout('file_url'),
            ]);
    }
}
