<?php

namespace App\Filament\Resources\Lessons\Schemas;

use App\Models\Lesson;
use App\Services\Vimeo\VimeoOembedClient;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
                Hidden::make('duration_locked')
                    ->dehydrated(false)
                    // default() only feeds the create-form pass in this Filament
                    // version, so an edit form's initial lock state has to be
                    // computed here instead, from the record being edited.
                    ->afterStateHydrated(function (Set $set, ?Lesson $record): void {
                        $set('duration_locked', $record?->video_provider === 'vimeo');
                    }),
                TextInput::make('duration_seconds')
                    ->label('Duration (mm:ss or h:mm:ss)')
                    ->placeholder('e.g. 5:30 or 1:15:30')
                    ->regex('/^(?:\d{1,3}:[0-5]\d|\d{1,2}:[0-5]\d:[0-5]\d)$/')
                    ->formatStateUsing(fn (?int $state): ?string => self::formatDuration($state))
                    ->dehydrateStateUsing(fn (?string $state): ?int => self::parseDuration($state))
                    ->disabled(fn (Get $get): bool => (bool) $get('duration_locked'))
                    // disabled() also turns off saving by default — force it back on.
                    ->dehydrated(),
                Select::make('video_provider')
                    ->options([
                        'vimeo' => 'Vimeo',
                        'youtube' => 'YouTube',
                    ])
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::syncVimeoDuration($get, $set)),
                TextInput::make('video_id')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::syncVimeoDuration($get, $set)),
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

    public static function syncVimeoDuration(Get $get, Set $set): void
    {
        if ($get('video_provider') !== 'vimeo') {
            $set('duration_locked', false);

            return;
        }

        $videoId = $get('video_id');

        if (blank($videoId)) {
            return;
        }

        $seconds = app(VimeoOembedClient::class)->getDurationInSeconds($videoId);

        if ($seconds === null) {
            $set('duration_locked', false);

            Notification::make()
                ->warning()
                ->title('Não foi possível obter a duração do Vimeo automaticamente')
                ->body('Preencha a duração manualmente.')
                ->send();

            return;
        }

        $set('duration_seconds', self::formatDuration($seconds));
        $set('duration_locked', true);
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
