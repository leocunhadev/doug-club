# Lesson Duration Auto-fill (Vimeo) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** When a lesson's provider is Vimeo, fetch its duration automatically from Vimeo's public oEmbed endpoint and lock the `duration_seconds` field against manual edits, falling back to manual entry if the lookup fails.

**Architecture:** A small standalone HTTP client (`App\Services\Vimeo\VimeoOembedClient`) wraps the Vimeo oEmbed call and never throws — it returns `?int` seconds or `null`. The Filament `LessonForm` gets a non-persisted `duration_locked` hidden field driven by `live()`/`afterStateUpdated()` hooks on `video_provider` and `video_id`, which call the client and toggle `duration_seconds`'s `disabled()` state accordingly.

**Tech Stack:** Laravel 13, Filament 4.12 (`filament/forms`, `filament/schemas`, `filament/notifications`), PHPUnit, Livewire testing (`Livewire::test`), `Illuminate\Support\Facades\Http` with `Http::fake()`.

## Global Constraints

- Only Vimeo gets auto-fetch; YouTube's `duration_seconds` stays fully manual (per spec §2).
- No API key / auth needed — use the public endpoint `https://vimeo.com/api/oembed.json?url=https://vimeo.com/{video_id}`, reading the `duration` key (seconds) from the JSON response.
- The client must never let an exception escape `getDurationInSeconds()` — any failure (bad status, missing key, network/timeout exception) returns `null`.
- Existing `formatDuration()` / `parseDuration()` helpers in `LessonForm` stay as the single source of truth for the `mm:ss` / `h:mm:ss` string format — reuse them, don't duplicate.
- `disabled()` in this Filament version also flips the field's `saved()`/dehydration hook to `false` (see `vendor/filament/schemas/src/Components/Concerns/CanBeDisabled.php:16-28` and `HasState::isDehydrated()`), so a disabled `duration_seconds` would silently stop being saved unless dehydration is forced back on. Every task touching that field must account for this.

---

### Task 1: `VimeoOembedClient`

**Files:**
- Create: `app/Services/Vimeo/VimeoOembedClient.php`
- Test: `tests/Unit/VimeoOembedClientTest.php`

**Interfaces:**
- Produces: `App\Services\Vimeo\VimeoOembedClient::getDurationInSeconds(string $videoId): ?int` — used by Task 2.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/VimeoOembedClientTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Services\Vimeo\VimeoOembedClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VimeoOembedClientTest extends TestCase
{
    public function test_it_returns_the_duration_in_seconds_on_success(): void
    {
        Http::fake([
            'vimeo.com/api/oembed.json*' => Http::response(['duration' => 635], 200),
        ]);

        $client = new VimeoOembedClient();

        $this->assertSame(635, $client->getDurationInSeconds('123456789'));
    }

    public function test_it_returns_null_when_the_response_has_no_duration(): void
    {
        Http::fake([
            'vimeo.com/api/oembed.json*' => Http::response(['title' => 'Some video'], 200),
        ]);

        $client = new VimeoOembedClient();

        $this->assertNull($client->getDurationInSeconds('123456789'));
    }

    public function test_it_returns_null_on_a_non_successful_response(): void
    {
        Http::fake([
            'vimeo.com/api/oembed.json*' => Http::response(['error' => 'not found'], 404),
        ]);

        $client = new VimeoOembedClient();

        $this->assertNull($client->getDurationInSeconds('does-not-exist'));
    }

    public function test_it_returns_null_when_the_request_throws(): void
    {
        Http::fake([
            'vimeo.com/api/oembed.json*' => function (): never {
                throw new ConnectionException('Connection timed out');
            },
        ]);

        $client = new VimeoOembedClient();

        $this->assertNull($client->getDurationInSeconds('123456789'));
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Unit/VimeoOembedClientTest.php`
Expected: FAIL — `Class "App\Services\Vimeo\VimeoOembedClient" not found`.

- [ ] **Step 3: Implement the client**

Create `app/Services/Vimeo/VimeoOembedClient.php`:

```php
<?php

namespace App\Services\Vimeo;

use Illuminate\Support\Facades\Http;
use Throwable;

class VimeoOembedClient
{
    public function getDurationInSeconds(string $videoId): ?int
    {
        try {
            $response = Http::timeout(5)->get('https://vimeo.com/api/oembed.json', [
                'url' => "https://vimeo.com/{$videoId}",
            ]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $duration = $response->json('duration');

        return is_int($duration) ? $duration : null;
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Unit/VimeoOembedClientTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Vimeo/VimeoOembedClient.php tests/Unit/VimeoOembedClientTest.php
git commit -m "feat: add VimeoOembedClient for fetching video duration"
```

---

### Task 2: Wire auto-fill and lock into `LessonForm`

**Files:**
- Modify: `app/Filament/Resources/Lessons/Schemas/LessonForm.php`
- Modify: `tests/Feature/Admin/LessonResourceTest.php`

**Interfaces:**
- Consumes: `App\Services\Vimeo\VimeoOembedClient::getDurationInSeconds(string $videoId): ?int` (Task 1), `LessonForm::formatDuration(?int $seconds): ?string` (existing, unchanged).
- Produces: `LessonForm::syncVimeoDuration(Get $get, Set $set): void` — the shared handler wired to both `video_provider` and `video_id`. Not consumed outside this file, but keep the name exact since both fields' `afterStateUpdated()` closures call it.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Admin/LessonResourceTest.php`. First add `use Illuminate\Support\Facades\Http;` to the imports at the top of the file (alongside the existing `use` statements), then add these test methods inside the class:

```php
    public function test_creating_a_vimeo_lesson_autofills_and_locks_duration_on_a_valid_video_id(): void
    {
        Http::fake([
            'vimeo.com/api/oembed.json*' => Http::response(['duration' => 125], 200),
        ]);

        $course = $this->course();

        $this->actingAs($this->admin());

        Livewire::test(CreateLesson::class)
            ->fillForm([
                'course_id' => $course->id,
                'number' => 1,
                'title' => 'Aula vimeo',
                'video_provider' => 'vimeo',
                'published_at' => '2026-01-01',
                'position' => 10,
            ])
            ->set('data.video_id', 'xyz')
            ->assertFormSet(['duration_seconds' => '2:05'])
            ->assertFormFieldDisabled('duration_seconds')
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('lessons', [
            'title' => 'Aula vimeo',
            'duration_seconds' => 125,
        ]);
    }

    public function test_creating_a_vimeo_lesson_leaves_duration_editable_when_the_vimeo_lookup_fails(): void
    {
        Http::fake([
            'vimeo.com/api/oembed.json*' => Http::response(['error' => 'not found'], 404),
        ]);

        $course = $this->course();

        $this->actingAs($this->admin());

        Livewire::test(CreateLesson::class)
            ->fillForm([
                'course_id' => $course->id,
                'number' => 1,
                'title' => 'Aula vimeo invalida',
                'video_provider' => 'vimeo',
                'published_at' => '2026-01-01',
                'position' => 10,
            ])
            ->set('data.video_id', 'does-not-exist')
            ->assertFormFieldEnabled('duration_seconds')
            ->assertNotified('Não foi possível obter a duração do Vimeo automaticamente')
            ->fillForm(['duration_seconds' => '3:00'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('lessons', [
            'title' => 'Aula vimeo invalida',
            'duration_seconds' => 180,
        ]);
    }

    public function test_creating_a_youtube_lesson_never_calls_vimeo_and_keeps_duration_editable(): void
    {
        Http::fake();

        $course = $this->course();

        $this->actingAs($this->admin());

        Livewire::test(CreateLesson::class)
            ->fillForm([
                'course_id' => $course->id,
                'number' => 1,
                'title' => 'Aula youtube',
                'video_provider' => 'youtube',
                'published_at' => '2026-01-01',
                'position' => 10,
            ])
            ->set('data.video_id', 'abc123')
            ->assertFormFieldEnabled('duration_seconds')
            ->fillForm(['duration_seconds' => '4:15'])
            ->call('create')
            ->assertHasNoFormErrors();

        Http::assertNothingSent();

        $this->assertDatabaseHas('lessons', [
            'title' => 'Aula youtube',
            'duration_seconds' => 255,
        ]);
    }

    public function test_editing_an_existing_vimeo_lesson_opens_with_duration_already_locked(): void
    {
        Http::fake();

        $lesson = Lesson::create([
            'course_id' => $this->course()->id,
            'number' => 1,
            'title' => 'Aula vimeo existente',
            'duration_seconds' => 125,
            'video_provider' => 'vimeo',
            'video_id' => 'xyz',
            'published_at' => '2026-01-01',
            'position' => 10,
        ]);

        $this->actingAs($this->admin());

        Livewire::test(EditLesson::class, ['record' => $lesson->getKey()])
            ->assertFormSet(['duration_seconds' => '2:05'])
            ->assertFormFieldDisabled('duration_seconds');

        Http::assertNothingSent();
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/Admin/LessonResourceTest.php --filter=vimeo_lesson`
Expected: FAIL — `duration_seconds` stays unset/editable because there's no reactive wiring yet (the two new-behavior tests for Vimeo fail; the YouTube and edit-existing tests may already pass since they don't depend on new behavior — that's fine, they lock in the "must not regress" side of this change).

- [ ] **Step 3: Implement the form changes**

Replace the full contents of `app/Filament/Resources/Lessons/Schemas/LessonForm.php`:

```php
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
                    // disabled() also turns off saving by default (see plan's Global Constraints) — force it back on.
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
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/LessonResourceTest.php`
Expected: PASS (all tests in the file, including the pre-existing ones — `test_admin_can_create_a_lesson` and `test_admin_can_edit_a_lesson_and_duration_round_trips_through_the_form` must still pass unchanged).

Note from actual execution: the first pass with `Hidden::make('duration_locked')->default(...)` left `test_editing_an_existing_vimeo_lesson_opens_with_duration_already_locked` failing — `default()` in this Filament version only feeds the create-form default-state hydration pass (see `hydrateDefaultState()` in `vendor/filament/schemas/src/Components/Concerns/HasState.php:558-575`, short-circuited whenever the record already has state, i.e. on edit). Swapping to `->afterStateHydrated()` (shown above) fixed it, since that hook fires for both create and edit.

- [ ] **Step 5: Run the full test suite**

Run: `php artisan test`
Expected: PASS, no regressions elsewhere.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/Lessons/Schemas/LessonForm.php tests/Feature/Admin/LessonResourceTest.php
git commit -m "feat: auto-fill and lock lesson duration for Vimeo videos"
```

---

## Self-Review Notes

- **Spec coverage:** client + null-on-any-failure (Task 1, spec §3 "Fonte dos dados"/client contract) ✔; hidden lock flag defaulting from the record (Task 2, spec §3 default closure) ✔; live triggers on both `video_provider` and `video_id` (Task 2, spec §3) ✔; success/failure/blank branches of `syncVimeoDuration` (Task 2, spec §3 numbered list) ✔; disabled binding on `duration_seconds` (Task 2) ✔; YouTube untouched, no HTTP call (Task 2 test 3) ✔; edit-existing-lesson opens locked without refetch (Task 2 test 4) ✔.
- **Placeholder scan:** none found — every step has literal, complete code.
- **Type consistency:** `VimeoOembedClient::getDurationInSeconds(string $videoId): ?int` matches its Task 1 test usage and its Task 2 call site (`app(VimeoOembedClient::class)->getDurationInSeconds($videoId)`); `LessonForm::syncVimeoDuration(Get $get, Set $set): void` matches both `afterStateUpdated()` call sites; `formatDuration`/`parseDuration` signatures are untouched from the existing file.
