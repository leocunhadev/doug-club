<?php

namespace App\Filament\Resources\BridgeRequests;

use App\Filament\Resources\BridgeRequests\Pages\ListBridgeRequests;
use App\Filament\Resources\BridgeRequests\Tables\BridgeRequestsTable;
use App\Models\BridgeRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BridgeRequestResource extends Resource
{
    protected static ?string $model = BridgeRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $recordTitleAttribute = 'id';

    public static function getModelLabel(): string
    {
        return 'Pedido de Ponte';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Pedidos de Ponte';
    }

    public static function table(Table $table): Table
    {
        return BridgeRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBridgeRequests::route('/'),
        ];
    }
}
