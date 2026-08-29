<?php

namespace App\Filament\Resources\Encontros\Pages;

use App\Filament\Resources\Encontros\EncontroResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEncontro extends EditRecord
{
    protected static string $resource = EncontroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
