<?php

namespace App\Filament\Resources\Encontros;

use App\Filament\Resources\Encontros\Pages\CreateEncontro;
use App\Filament\Resources\Encontros\Pages\EditEncontro;
use App\Filament\Resources\Encontros\Pages\ListEncontros;
use App\Filament\Resources\Encontros\Schemas\EncontroForm;
use App\Filament\Resources\Encontros\Tables\EncontrosTable;
use App\Models\Encontro;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EncontroResource extends Resource
{
    protected static ?string $model = Encontro::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $recordTitleAttribute = 'tema';

    public static function getModelLabel(): string
    {
        return 'Encontro';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Encontros';
    }

    public static function form(Schema $schema): Schema
    {
        return EncontroForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EncontrosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEncontros::route('/'),
            'create' => CreateEncontro::route('/create'),
            'edit' => EditEncontro::route('/{record}/edit'),
        ];
    }
}
