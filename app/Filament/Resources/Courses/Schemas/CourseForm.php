<?php

namespace App\Filament\Resources\Courses\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class CourseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')
                    ->required(),
                TextInput::make('title')
                    // The `title` column is NOT NULL at the DB level, but this field is
                    // intentionally optional (see the "Boas Vindas" seeded course, which
                    // has an empty title by design). Filament's TextInput coerces an
                    // empty string state to `null` when dehydrating
                    // (see `HasState::getStateToDehydrate()`), which would otherwise
                    // violate the NOT NULL constraint on save — so we cast it back to
                    // an empty string here instead.
                    ->dehydrateStateUsing(fn (?string $state): string => $state ?? ''),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('position')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
