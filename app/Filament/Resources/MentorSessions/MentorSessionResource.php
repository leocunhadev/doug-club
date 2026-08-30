<?php

namespace App\Filament\Resources\MentorSessions;

use App\Filament\Resources\MentorSessions\Pages\ListMentorSessions;
use App\Filament\Resources\MentorSessions\Tables\MentorSessionsTable;
use App\Models\MentorSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MentorSessionResource extends Resource
{
    protected static ?string $model = MentorSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $recordTitleAttribute = 'scheduled_at';

    public static function table(Table $table): Table
    {
        return MentorSessionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMentorSessions::route('/'),
        ];
    }
}
