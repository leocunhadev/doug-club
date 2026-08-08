<?php

namespace App\Filament\Resources\Lessons\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LessonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('course_id')
                    ->relationship('course', 'label')
                    ->required(),
                TextInput::make('number')
                    ->required()
                    ->numeric(),
                TextInput::make('title')
                    ->required(),
                TextInput::make('duration_seconds')
                    ->label('Duration (mm:ss or h:mm:ss)')
                    ->placeholder('e.g. 5:30 or 1:15:30')
                    ->regex('/^(?:\d{1,3}:[0-5]\d|\d{1,2}:[0-5]\d:[0-5]\d)$/')
                    ->formatStateUsing(fn (?int $state): ?string => self::formatDuration($state))
                    ->dehydrateStateUsing(fn (?string $state): ?int => self::parseDuration($state)),
                Select::make('video_provider')
                    ->options([
                        'vimeo' => 'Vimeo',
                        'youtube' => 'YouTube',
                    ])
                    ->required(),
                TextInput::make('video_id')
                    ->required(),
                FileUpload::make('thumbnail_path')
                    ->disk('public')
                    ->directory('lesson-thumbnails')
                    ->image(),
                DatePicker::make('published_at')
                    ->required(),
                TextInput::make('position')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function parseDuration(?string $value): ?int
    {
        if (blank($value)) {
            return null;
        }

        $parts = array_map('intval', explode(':', $value));

        if (count($parts) < 2 || count($parts) > 3) {
            return null;
        }

        if (count($parts) === 2) {
            [$minutes, $seconds] = $parts;

            return ($minutes * 60) + $seconds;
        }

        [$hours, $minutes, $seconds] = $parts;

        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }

    public static function formatDuration(?int $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $remainingSeconds)
            : sprintf('%d:%02d', $minutes, $remainingSeconds);
    }
}
