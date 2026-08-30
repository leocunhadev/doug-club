<?php

namespace App\Filament\Resources\VaultDocuments\Pages;

use App\Filament\Resources\VaultDocuments\VaultDocumentResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateVaultDocument extends CreateRecord
{
    protected static string $resource = VaultDocumentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['mentor_id'] = User::query()->where('tier', 'mentor')->value('id');

        return $data;
    }
}
