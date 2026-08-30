<?php

namespace App\Filament\Resources\VaultDocuments\Pages;

use App\Filament\Resources\VaultDocuments\VaultDocumentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVaultDocuments extends ListRecords
{
    protected static string $resource = VaultDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
