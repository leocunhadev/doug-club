<?php

namespace App\Filament\Resources\Encontros\Pages;

use App\Filament\Resources\Encontros\EncontroResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEncontros extends ListRecords
{
    protected static string $resource = EncontroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
