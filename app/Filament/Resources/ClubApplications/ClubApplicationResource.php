<?php

namespace App\Filament\Resources\ClubApplications;

use App\Filament\Resources\ClubApplications\Pages\ListClubApplications;
use App\Filament\Resources\ClubApplications\Tables\ClubApplicationsTable;
use App\Models\ClubApplication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ClubApplicationResource extends Resource
{
    protected static ?string $model = ClubApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?string $recordTitleAttribute = 'id';

    public static function table(Table $table): Table
    {
        return ClubApplicationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClubApplications::route('/'),
        ];
    }
}
