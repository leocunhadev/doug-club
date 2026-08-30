<?php

namespace App\Filament\Resources\VaultDocuments\Pages;

use App\Filament\Resources\VaultDocuments\VaultDocumentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVaultDocument extends EditRecord
{
    protected static string $resource = VaultDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
