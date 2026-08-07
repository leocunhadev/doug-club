# LMS Área de Membros — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the gap between `docs/lms-spec.md` and the codebase — remove the still-public registration route, drop the Panda Video provider, add the video-embed/presentation logic, and rebuild `dashboard.blade.php` into the real dark-themed UI described in the spec (hero player, course carousels, lesson cards, header/footer).

**Architecture:** Single Livewire component (`App\Livewire\Membros\Dashboard`) owns the whole `/membros` page — no nested Livewire components. Presentation logic (embed URL, formatted duration, thumbnail fallback) lives on `Lesson` as Eloquent accessors so the view stays declarative. The card and header are plain Blade components (`<x-lesson-card>`, `<x-membros.header>`) rendered inside the Dashboard component's own template, so `wire:click` works without component nesting.

**Tech Stack:** Laravel 13, Livewire 3, Blade components, Alpine.js (carousel arrows via `x-data`/`x-ref`), Tailwind 3 (existing `tailwind.config.js`, arbitrary-value classes for the `#0a0a0b`/`#12141a` tokens — no config changes needed), Pest-free PHPUnit (`php artisan test`).

## Global Constraints

- No self-registration: the `register` route and its view must not exist (spec §2, §4).
- `video_provider` supports only `vimeo` and `youtube` (spec §3) — no Panda Video anywhere in code, migrations, or seed data.
- One Livewire component for the whole dashboard — no `<livewire:membros.hero-player>` or `<livewire:membros.lesson-carousel>` (spec §5).
- Materials render inline under the hero player — no modal, no dedicated route (spec §4, §5).
- Design tokens from spec §7 (background `#0a0a0b`, cards `#12141a`/`border-slate-800/60`, orange→red gradient accent, `rounded-xl` cards) apply to every new UI element.
- Run `php artisan test` after every task; all tests must pass before moving on.

**Amendment (post-approval drift):** after this plan was approved, an out-of-band commit (`faf31bc`, closing GitHub issues #2-#8) landed on `main` and split the hero into a separate `HeroPlayer` Livewire component plus dedicated `LessonController` routes — the exact architecture the spec rejected in favor of a single `Dashboard` component. The human partner has confirmed the spec/plan govern: **Task 0** below consolidates that commit's code back into the single-component shape before Tasks 1-6 (written against the pre-`faf31bc` file shapes) proceed. Task 0 keeps `faf31bc`'s two `Actions` classes and its `seedSampleProgress()` seeder addition — both are compatible with the spec, just wired differently.

---

### Task 0: Consolidate back to a single Dashboard component

**Files:**
- Delete: `app/Livewire/Membros/HeroPlayer.php`
- Delete: `resources/views/livewire/membros/hero-player.blade.php`
- Delete: `app/Http/Controllers/Membros/LessonController.php`
- Delete: `resources/views/membros/materiais.blade.php`
- Modify: `app/Livewire/Membros/Dashboard.php`
- Modify: `resources/views/livewire/membros/dashboard.blade.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `App\Actions\DetermineFeaturedLesson::handle(int $userId): ?int` and `App\Actions\MarkLessonAsWatching::handle(int $userId, int $lessonId): void` (both already exist from `faf31bc`, unchanged).
- Produces: `Dashboard::$featuredLessonId`, `Dashboard::watchLesson(int $lessonId)`, `Dashboard::featuredLesson()` (Computed, eager-loads `['course', 'materials']`) — this is the exact shape Task 4 modifies, so Task 4's steps apply as written on top of this task's output. `/membros` is once again the only members route.

- [ ] **Step 1: Delete the split-out component, controller, and their views**

```bash
rm app/Livewire/Membros/HeroPlayer.php
rm resources/views/livewire/membros/hero-player.blade.php
rm app/Http/Controllers/Membros/LessonController.php
rm resources/views/membros/materiais.blade.php
```

- [ ] **Step 2: Restore `Dashboard` as the sole owner of featured-lesson state, wired to the existing Action classes**

Replace `app/Livewire/Membros/Dashboard.php` with:

```php
<?php

namespace App\Livewire\Membros;

use App\Actions\DetermineFeaturedLesson;
use App\Actions\MarkLessonAsWatching;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public ?int $featuredLessonId = null;

    public function mount(DetermineFeaturedLesson $determineFeaturedLesson): void
    {
        $this->featuredLessonId = $determineFeaturedLesson->handle(Auth::id());
    }

    public function watchLesson(int $lessonId, MarkLessonAsWatching $action): void
    {
        $action->handle(Auth::id(), $lessonId);

        $this->featuredLessonId = $lessonId;
    }

    #[Computed]
    public function featuredLesson(): ?Lesson
    {
        return Lesson::query()->with(['course', 'materials'])->find($this->featuredLessonId);
    }

    #[Computed]
    public function courses()
    {
        return Course::query()
            ->with('lessons')
            ->orderByDesc('position')
            ->get();
    }

    public function render()
    {
        return view('livewire.membros.dashboard', [
            'watchingLessonId' => LessonProgress::query()
                ->where('user_id', Auth::id())
                ->where('status', 'watching')
                ->latest('last_watched_at')
                ->value('lesson_id'),
        ]);
    }
}
```

- [ ] **Step 3: Inline the hero markup back into `dashboard.blade.php`**

This is a transitional placeholder — Task 6 rewrites this file completely into the real spec UI. It only needs to compile, render, and keep the existing test suite green. Replace `resources/views/livewire/membros/dashboard.blade.php` with:

```blade
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-12">

        <h1 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Sua central de conteúdos') }}
        </h1>

        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
            @if ($lesson = $this->featuredLesson)
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                    {{ $lesson->course->label }}: {{ $lesson->course->title }}
                </p>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    Aula {{ $lesson->number }} — {{ $lesson->title }}
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    {{ ucfirst($lesson->video_provider) }} · {{ $lesson->video_id }}
                </p>

                @if ($lesson->materials->isNotEmpty())
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($lesson->materials as $material)
                            <a href="{{ $material->file_url }}" target="_blank"
                               class="inline-flex items-center px-3 py-1.5 rounded-md text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600">
                                📎 {{ $material->title }}
                            </a>
                        @endforeach
                    </div>
                @endif
            @else
                <p class="text-gray-500 dark:text-gray-400">Nenhuma aula disponível ainda.</p>
            @endif
        </div>

        @foreach ($this->courses as $course)
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    {{ $course->label }}: {{ $course->title }}
                </h2>
                @if ($course->description)
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $course->description }}</p>
                @endif

                <div class="mt-4 flex gap-4 overflow-x-auto pb-2">
                    @foreach ($course->lessons as $courseLesson)
                        <button
                            wire:click="watchLesson({{ $courseLesson->id }})"
                            type="button"
                            class="relative shrink-0 w-56 text-left bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 hover:ring-2 hover:ring-orange-500"
                        >
                            @if ($watchingLessonId === $courseLesson->id)
                                <span class="absolute top-2 right-2 text-xs font-semibold px-2 py-0.5 rounded-full bg-orange-500 text-white">
                                    ASSISTINDO
                                </span>
                            @endif
                            <p class="text-xs uppercase tracking-wide text-gray-400">Aula</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $courseLesson->number }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                {{ $courseLesson->published_at->format('d/m/Y') }}
                            </p>
                            <p class="text-sm text-gray-700 dark:text-gray-200 mt-1 line-clamp-2">
                                {{ $courseLesson->title }}
                            </p>
                        </button>
                    @endforeach
                </div>
            </div>
        @endforeach

    </div>
</div>
```

- [ ] **Step 4: Remove the dedicated members routes**

Replace `routes/web.php` with:

```php
<?php

use App\Livewire\Membros\Dashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::get('membros', Dashboard::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
```

- [ ] **Step 5: Run the full test suite**

Run: `php artisan test`
Expected: PASS — no test in the suite references `HeroPlayer`, `LessonController`, or the removed routes, so this should be a clean regression check.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "Consolidate hero player back into the single Dashboard component"
```

---

### Task 1: Remove public self-registration

**Files:**
- Modify: `routes/auth.php:7-9`
- Delete: `resources/views/livewire/pages/auth/register.blade.php`
- Delete: `tests/Feature/Auth/RegistrationTest.php`

**Interfaces:**
- Consumes: nothing from other tasks.
- Produces: nothing later tasks depend on. `Route::has('register')` now returns `false`, which `resources/views/livewire/welcome/navigation.blade.php:17` already guards against — no change needed there.

- [ ] **Step 1: Remove the register route**

In `routes/auth.php`, delete the register block so the file starts:

```php
<?php

use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware('guest')->group(function () {
    Volt::route('login', 'pages.auth.login')
        ->name('login');

    Volt::route('forgot-password', 'pages.auth.forgot-password')
        ->name('password.request');

    Volt::route('reset-password/{token}', 'pages.auth.reset-password')
        ->name('password.reset');
});
```

(The `auth`-middleware group below is unchanged.)

- [ ] **Step 2: Delete the now-unreachable register view and its test**

```bash
rm resources/views/livewire/pages/auth/register.blade.php
rm tests/Feature/Auth/RegistrationTest.php
```

- [ ] **Step 3: Verify `/register` is gone and the rest of the auth suite still passes**

Run: `php artisan test tests/Feature/Auth`
Expected: PASS (RegistrationTest is gone; Authentication/EmailVerification/PasswordReset/PasswordUpdate/PasswordConfirmation tests still pass).

Then run: `php artisan test --filter=nothing_here 2>/dev/null; curl -s -o /dev/null -w "%{http_code}" http://localhost/register || true`
(This curl is optional/manual — the important check is the test suite above. If you have `php artisan serve` running, a `GET /register` should now 404.)

- [ ] **Step 4: Commit**

```bash
git add routes/auth.php
git rm resources/views/livewire/pages/auth/register.blade.php tests/Feature/Auth/RegistrationTest.php
git commit -m "Remove public self-registration route"
```

---

### Task 2: Drop Panda Video provider

**Files:**
- Modify: `database/migrations/2026_08_07_041529_create_lessons_table.php:15`
- Modify: `database/seeders/LmsSeeder.php`

**Interfaces:**
- Consumes: nothing from other tasks.
- Produces: `lessons.video_provider` enum is `['vimeo', 'youtube']`. Task 3's `embedUrl` accessor assumes only these two values exist.

This migration was added in the same feature branch and has never run against a deployed database (dev is SQLite, single local commit) — editing it in place is correct here; do **not** add a new migration for an unreleased column.

- [ ] **Step 1: Edit the enum in the existing migration**

In `database/migrations/2026_08_07_041529_create_lessons_table.php`, change:

```php
$table->enum('video_provider', ['vimeo', 'youtube', 'panda']);
```

to:

```php
$table->enum('video_provider', ['vimeo', 'youtube']);
```

- [ ] **Step 2: Replace Panda demo data in the seeder with real, embeddable IDs**

`database/seeders/LmsSeeder.php` currently has a `$providers = ['panda', 'vimeo', 'youtube']` cycle (added by the `faf31bc` commit — see the plan header amendment) feeding synthetic `demo-{course}-{number}` video IDs, followed by a `seedSampleProgress()` method. Keep `seedSampleProgress()` exactly as-is — only touch the provider/ID generation. Replace:

```php
        $providers = ['panda', 'vimeo', 'youtube'];
        $providerIndex = 0;

        foreach ($courses as $courseData) {
            $lessons = $courseData['lessons'];
            unset($courseData['lessons']);

            $course = Course::create($courseData);

            foreach ($lessons as $index => $lessonData) {
                $provider = $providers[$providerIndex % count($providers)];
                $providerIndex++;

                $course->lessons()->create($lessonData + [
                    'video_provider' => $provider,
                    'video_id' => 'demo-'.$course->id.'-'.$lessonData['number'],
                    'position' => count($lessons) - $index,
                ]);
            }
        }

        $this->seedSampleProgress();
```

with:

```php
        $demoVideos = [
            ['provider' => 'youtube', 'id' => 'aqz-KE-bpKQ'],
            ['provider' => 'vimeo', 'id' => '76979871'],
            ['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ'],
        ];
        $videoIndex = 0;

        foreach ($courses as $courseData) {
            $lessons = $courseData['lessons'];
            unset($courseData['lessons']);

            $course = Course::create($courseData);

            foreach ($lessons as $index => $lessonData) {
                $video = $demoVideos[$videoIndex % count($demoVideos)];
                $videoIndex++;

                $course->lessons()->create($lessonData + [
                    'video_provider' => $video['provider'],
                    'video_id' => $video['id'],
                    'position' => count($lessons) - $index,
                ]);
            }
        }

        $this->seedSampleProgress();
```

(The `$videoIndex` now increments across all courses rather than resetting per course, purely so the demo mix looks varied end-to-end — this has no functional effect on `seedSampleProgress()`, which looks up lessons by title/course label, not by provider.)

- [ ] **Step 3: Re-run migrations and seed, verify no Panda rows remain**

```bash
php artisan migrate:fresh --seed
```

Expected: no errors. Then:

```bash
php artisan tinker --execute="echo App\Models\Lesson::distinct()->pluck('video_provider')->implode(', ');"
```

Expected output: `youtube, vimeo` (only these two).

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_08_07_041529_create_lessons_table.php database/seeders/LmsSeeder.php
git commit -m "Drop Panda Video provider, use real demo video IDs in seeder"
```

---

### Task 3: Lesson presentation accessors

**Files:**
- Modify: `app/Models/Lesson.php`
- Create: `tests/Unit/LessonPresentationTest.php`

**Interfaces:**
- Consumes: `Lesson` model from Task 2 (enum now `vimeo`/`youtube`).
- Produces: `$lesson->embed_url` (string), `$lesson->duration_formatted` (string|null), `$lesson->thumbnail_url` (string|null) — used by Task 6's Blade views.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/LessonPresentationTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonPresentationTest extends TestCase
{
    use RefreshDatabase;

    private function makeLesson(array $overrides = []): Lesson
    {
        $course = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);

        return Lesson::create(array_merge([
            'course_id' => $course->id,
            'number' => 1,
            'title' => 'Aula 1',
            'video_provider' => 'youtube',
            'video_id' => 'abc123',
            'published_at' => '2026-01-01',
            'position' => 1,
        ], $overrides));
    }

    public function test_embed_url_for_vimeo(): void
    {
        $lesson = $this->makeLesson(['video_provider' => 'vimeo', 'video_id' => '76979871']);

        $this->assertSame('https://player.vimeo.com/video/76979871', $lesson->embed_url);
    }

    public function test_embed_url_for_youtube(): void
    {
        $lesson = $this->makeLesson(['video_provider' => 'youtube', 'video_id' => 'dQw4w9WgXcQ']);

        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $lesson->embed_url);
    }

    public function test_duration_formatted_under_an_hour(): void
    {
        $lesson = $this->makeLesson(['duration_seconds' => 2923]);

        $this->assertSame('48:43', $lesson->duration_formatted);
    }

    public function test_duration_formatted_over_an_hour(): void
    {
        $lesson = $this->makeLesson(['duration_seconds' => 4020]);

        $this->assertSame('1h 07min', $lesson->duration_formatted);
    }

    public function test_duration_formatted_is_null_when_duration_is_null(): void
    {
        $lesson = $this->makeLesson(['duration_seconds' => null]);

        $this->assertNull($lesson->duration_formatted);
    }

    public function test_thumbnail_url_falls_back_to_youtube_frame(): void
    {
        $lesson = $this->makeLesson(['video_provider' => 'youtube', 'video_id' => 'dQw4w9WgXcQ', 'thumbnail_path' => null]);

        $this->assertSame('https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg', $lesson->thumbnail_url);
    }

    public function test_thumbnail_url_falls_back_to_null_for_vimeo_without_thumbnail_path(): void
    {
        $lesson = $this->makeLesson(['video_provider' => 'vimeo', 'video_id' => '76979871', 'thumbnail_path' => null]);

        $this->assertNull($lesson->thumbnail_url);
    }

    public function test_thumbnail_url_prefers_explicit_thumbnail_path(): void
    {
        $lesson = $this->makeLesson(['thumbnail_path' => 'https://cdn.example.com/thumb.jpg']);

        $this->assertSame('https://cdn.example.com/thumb.jpg', $lesson->thumbnail_url);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Unit/LessonPresentationTest.php`
Expected: FAIL — `embed_url`/`duration_formatted`/`thumbnail_url` are undefined attributes (null/errors).

- [ ] **Step 3: Implement the accessors**

In `app/Models/Lesson.php`, add the `Attribute` import and three accessors:

```php
use Illuminate\Database\Eloquent\Casts\Attribute;
```

Add inside the `Lesson` class (after the `progress()` relation):

```php
protected function embedUrl(): Attribute
{
    return Attribute::get(fn () => match ($this->video_provider) {
        'vimeo' => "https://player.vimeo.com/video/{$this->video_id}",
        'youtube' => "https://www.youtube-nocookie.com/embed/{$this->video_id}",
    });
}

protected function durationFormatted(): Attribute
{
    return Attribute::get(function () {
        if ($this->duration_seconds === null) {
            return null;
        }

        $hours = intdiv($this->duration_seconds, 3600);
        $minutes = intdiv($this->duration_seconds % 3600, 60);
        $seconds = $this->duration_seconds % 60;

        return $hours > 0
            ? sprintf('%dh %02dmin', $hours, $minutes)
            : sprintf('%d:%02d', $minutes, $seconds);
    });
}

protected function thumbnailUrl(): Attribute
{
    return Attribute::get(fn () => $this->thumbnail_path ?? match ($this->video_provider) {
        'youtube' => "https://img.youtube.com/vi/{$this->video_id}/hqdefault.jpg",
        default => null,
    });
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Unit/LessonPresentationTest.php`
Expected: PASS (8 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Models/Lesson.php tests/Unit/LessonPresentationTest.php
git commit -m "Add embed URL, formatted duration, and thumbnail fallback to Lesson"
```

---

### Task 4: Dashboard test coverage, logout action, and layout switch

**Files:**
- Modify: `app/Livewire/Membros/Dashboard.php`
- Create: `tests/Feature/Livewire/Membros/DashboardTest.php`

**Interfaces:**
- Consumes: `App\Livewire\Actions\Logout` (existing, already used by `livewire/layout/navigation.blade.php`).
- Produces: `Dashboard::logout()` action and `Dashboard::userInitials` (`#[Computed]`, string) — both consumed by Task 6's header markup. `#[Layout('layouts.membros')]` — the layout Task 5 creates.

The `mount()`/`watchLesson()` logic already exists and works; this task adds regression tests that lock in current behavior before Task 6 rewrites the view on top of it, and adds the two pieces of logic the new UI needs (logout, initials).

- [ ] **Step 1: Write the regression + new-behavior tests**

Create `tests/Feature/Livewire/Membros/DashboardTest.php`:

```php
<?php

namespace Tests\Feature\Livewire\Membros;

use App\Livewire\Membros\Dashboard;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/membros')->assertRedirect('/login');
    }

    public function test_featured_lesson_defaults_to_first_lesson_of_highest_position_course(): void
    {
        $user = User::factory()->create();
        $olderCourse = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);
        $newerCourse = Course::create(['label' => 'Boas Vindas', 'title' => '', 'position' => 50]);

        Lesson::create([
            'course_id' => $olderCourse->id, 'number' => 1, 'title' => 'Aula antiga',
            'video_provider' => 'youtube', 'video_id' => 'abc123', 'published_at' => '2026-01-01', 'position' => 1,
        ]);
        $welcomeLesson = Lesson::create([
            'course_id' => $newerCourse->id, 'number' => 1, 'title' => 'Boas Vindas',
            'video_provider' => 'youtube', 'video_id' => 'def456', 'published_at' => '2026-01-01', 'position' => 1,
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSet('featuredLessonId', $welcomeLesson->id);
    }

    public function test_featured_lesson_uses_most_recently_watched_lesson(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);

        $olderLesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula 1',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
        ]);
        $recentLesson = Lesson::create([
            'course_id' => $course->id, 'number' => 2, 'title' => 'Aula 2',
            'video_provider' => 'youtube', 'video_id' => 'def', 'published_at' => '2026-01-02', 'position' => 2,
        ]);

        LessonProgress::create([
            'user_id' => $user->id, 'lesson_id' => $olderLesson->id,
            'status' => 'watching', 'last_watched_at' => now()->subDay(),
        ]);
        LessonProgress::create([
            'user_id' => $user->id, 'lesson_id' => $recentLesson->id,
            'status' => 'watching', 'last_watched_at' => now(),
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSet('featuredLessonId', $recentLesson->id);
    }

    public function test_watch_lesson_upserts_progress_and_updates_featured_lesson(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula 1',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->call('watchLesson', $lesson->id)
            ->assertSet('featuredLessonId', $lesson->id);

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'status' => 'watching',
        ]);
    }

    public function test_user_can_log_out_from_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->call('logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_user_initials_are_computed_from_name(): void
    {
        $user = User::factory()->create(['name' => 'Ana Souza']);
        $this->actingAs($user);

        Livewire::test(Dashboard::class)->assertSee('AS');
    }
}
```

- [ ] **Step 2: Run the tests to verify only the new ones fail**

Run: `php artisan test tests/Feature/Livewire/Membros/DashboardTest.php`
Expected: the first four tests PASS (existing `mount`/`watchLesson` logic already satisfies them); `test_user_can_log_out_from_dashboard` and `test_user_initials_are_computed_from_name` FAIL (`logout` method / `userInitials` don't exist yet, and current placeholder view doesn't print initials).

- [ ] **Step 3: Add the logout action and userInitials computed property**

In `app/Livewire/Membros/Dashboard.php`, add imports:

```php
use App\Livewire\Actions\Logout;
```

Change the layout attribute:

```php
#[Layout('layouts.membros')]
```

Add these two methods to the class (near `watchLesson`/the computed properties):

```php
public function logout(Logout $logout): void
{
    $logout();

    $this->redirect('/login', navigate: true);
}

#[Computed]
public function userInitials(): string
{
    $initials = collect(explode(' ', Auth::user()->name))
        ->filter()
        ->map(fn (string $part) => mb_substr($part, 0, 1))
        ->take(2)
        ->implode('');

    return mb_strtoupper($initials);
}
```

(`featuredLesson()` already eager-loads `['course', 'materials']` from Task 0 — no change needed there.)

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/Livewire/Membros/DashboardTest.php`
Expected: PASS (6 tests). Note `test_user_initials_are_computed_from_name` passes once `userInitials` exists and is rendered somewhere in the view — the current placeholder view doesn't render it yet, so if it still fails after Step 3, add a temporary `{{ $this->userInitials }}` anywhere in `dashboard.blade.php` to confirm the computed property itself works; Task 6 will place it properly in the header.

- [ ] **Step 5: Commit**

```bash
git add app/Livewire/Membros/Dashboard.php tests/Feature/Livewire/Membros/DashboardTest.php
git commit -m "Add Dashboard logout action, user initials, and regression tests"
```

---

### Task 5: Dark layout shell + lesson card component

**Files:**
- Create: `resources/views/layouts/membros.blade.php`
- Create: `resources/views/components/membros/header.blade.php`
- Create: `resources/views/components/lesson-card.blade.php`
- Modify: `config/services.php`
- Modify: `.env.example`

**Interfaces:**
- Consumes: `Lesson::embed_url`/`duration_formatted`/`thumbnail_url` (Task 3), `Dashboard::userInitials`/`logout` (Task 4).
- Produces: `<x-membros.header :initials="..." />`, `<x-lesson-card :lesson="..." :course="..." :watching="..." />`, `layouts.membros` — all consumed by Task 6.

- [ ] **Step 1: Create the dark layout shell**

Create `resources/views/layouts/membros.blade.php` (mirrors `layouts/guest.blade.php`'s structure, no Breeze nav component since header is now owned by the Dashboard component itself):

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-[#0a0a0b]">
        {{ $slot }}
    </body>
</html>
```

- [ ] **Step 2: Create the header component**

Create `resources/views/components/membros/header.blade.php`:

```blade
@props(['initials'])

<header class="border-b border-slate-800/60">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <a href="{{ route('dashboard') }}" wire:navigate>
            <x-application-logo class="h-8 w-auto fill-current text-orange-500" />
        </a>

        <x-dropdown align="right" width="48" contentClasses="py-1 bg-[#12141a] border border-slate-800/60">
            <x-slot name="trigger">
                <button type="button" class="h-9 w-9 rounded-full bg-gradient-to-br from-orange-500 to-red-600 text-sm font-semibold text-white flex items-center justify-center">
                    {{ $initials }}
                </button>
            </x-slot>

            <x-slot name="content">
                <button wire:click="logout" type="button" class="w-full text-start px-4 py-2 text-sm text-gray-300 hover:bg-slate-800/60">
                    Sair
                </button>
            </x-slot>
        </x-dropdown>
    </div>
</header>
```

- [ ] **Step 3: Create the lesson card component**

Create `resources/views/components/lesson-card.blade.php`:

```blade
@props(['lesson', 'course', 'watching' => false])

<button
    type="button"
    wire:click="watchLesson({{ $lesson->id }})"
    {{ $attributes->class(['group relative shrink-0 w-64 text-left rounded-xl overflow-hidden bg-[#12141a] border border-slate-800/60 transition hover:scale-[1.02] hover:brightness-110']) }}
>
    <div class="relative aspect-video bg-gradient-to-br from-orange-500 to-red-600">
        @if ($lesson->thumbnail_url)
            <img src="{{ $lesson->thumbnail_url }}" alt="" class="absolute inset-0 h-full w-full object-cover">
        @endif
        <div class="absolute inset-0 bg-gradient-to-tr from-black/80 via-black/20 to-orange-600/40"></div>

        <span class="absolute top-2 left-2 text-[10px] font-semibold uppercase tracking-widest text-white/90 bg-black/40 rounded px-2 py-0.5">
            Curso — {{ $course->title ?: $course->label }}
        </span>

        @if ($watching)
            <span class="absolute top-2 right-2 text-xs font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full bg-gradient-to-r from-orange-500 to-red-600 text-white">
                Assistindo
            </span>
        @endif

        <span class="absolute inset-x-3 bottom-3 text-3xl font-extrabold text-white drop-shadow">
            Aula {{ $lesson->number }}
        </span>

        @if ($lesson->duration_formatted)
            <span class="absolute bottom-2 right-2 text-xs font-medium text-white bg-black/60 rounded px-1.5 py-0.5">
                {{ $lesson->duration_formatted }}
            </span>
        @endif
    </div>

    <div class="p-3">
        <p class="text-xs text-gray-400">{{ $lesson->published_at->format('d/m/Y') }}</p>
        <p class="mt-1 text-sm font-medium text-white line-clamp-2">{{ $lesson->title }}</p>
    </div>
</button>
```

- [ ] **Step 4: Add the WhatsApp number config**

In `config/services.php`, add before the closing `];`:

```php
    'whatsapp' => [
        'number' => env('WHATSAPP_NUMBER'),
    ],
```

In `.env.example`, add at the end:

```
WHATSAPP_NUMBER=
```

Also add the same line to your local `.env` (not committed) with a real number once available; an empty value renders a `https://wa.me/` link that simply won't be functional until set.

- [ ] **Step 5: Verify the new Blade files have no syntax errors**

Run: `php artisan view:clear && php -l resources/views/layouts/membros.blade.php`
Expected: `No syntax errors detected` (Blade compiles lazily on first render, but `php -l` catches raw PHP typos; the real render check happens in Task 6's tests).

- [ ] **Step 6: Commit**

```bash
git add resources/views/layouts/membros.blade.php resources/views/components/membros/header.blade.php resources/views/components/lesson-card.blade.php config/services.php .env.example
git commit -m "Add dark membros layout, header, and lesson card components"
```

---

### Task 6: Rewrite the dashboard view

**Files:**
- Modify: `resources/views/livewire/membros/dashboard.blade.php`
- Modify: `tests/Feature/Livewire/Membros/DashboardTest.php`

**Interfaces:**
- Consumes: everything from Tasks 3-5 (`Lesson` accessors, `Dashboard::userInitials`/`logout`, `layouts.membros`, `<x-membros.header>`, `<x-lesson-card>`).
- Produces: the final `/membros` page. Nothing later depends on this.

- [ ] **Step 1: Add markup assertions to the Dashboard test (written first, will fail against the current placeholder view)**

Append to `tests/Feature/Livewire/Membros/DashboardTest.php` (inside the class):

```php
    public function test_dashboard_renders_featured_lesson_embed_and_materials(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['label' => 'Módulo 4', 'title' => 'Modelos de Negócio', 'position' => 40]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 5, 'title' => 'Aula 05',
            'video_provider' => 'youtube', 'video_id' => 'dQw4w9WgXcQ', 'published_at' => '2026-07-17', 'position' => 1,
        ]);
        $lesson->materials()->create(['title' => 'Slides', 'file_url' => 'https://example.com/slides.pdf']);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSee('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertSee('Slides')
            ->assertSee('Aula 05')
            ->assertSee('Módulo 4');
    }

    public function test_watching_badge_appears_on_exactly_the_featured_lesson_card(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);
        $watchedLesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula 1',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 2,
        ]);
        Lesson::create([
            'course_id' => $course->id, 'number' => 2, 'title' => 'Aula 2',
            'video_provider' => 'youtube', 'video_id' => 'def', 'published_at' => '2026-01-02', 'position' => 1,
        ]);

        LessonProgress::create([
            'user_id' => $user->id, 'lesson_id' => $watchedLesson->id,
            'status' => 'watching', 'last_watched_at' => now(),
        ]);

        $this->actingAs($user);

        $html = Livewire::test(Dashboard::class)->html();

        $this->assertSame(1, substr_count($html, 'Assistindo'));
    }
```

- [ ] **Step 2: Run the tests to verify the two new ones fail**

Run: `php artisan test tests/Feature/Livewire/Membros/DashboardTest.php`
Expected: FAIL — current placeholder view has no iframe, no `Assistindo` badge text (it says "ASSISTINDO" via a different markup path today, but there's no `<x-lesson-card>` yet so the count/format won't match).

- [ ] **Step 3: Rewrite the view**

Replace the entire contents of `resources/views/livewire/membros/dashboard.blade.php` with:

```blade
<div class="min-h-screen text-white">
    <x-membros.header :initials="$this->userInitials" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-16 sm:space-y-20">
        <section>
            <h1 class="text-2xl font-bold">Sua central de conteúdos</h1>
            <p class="mt-1 text-gray-400">Continue de onde parou ou explore os módulos abaixo.</p>

            @if ($lesson = $this->featuredLesson)
                <div class="mt-6 rounded-xl overflow-hidden border border-slate-800/60 bg-[#12141a]">
                    <div class="aspect-video">
                        <iframe
                            src="{{ $lesson->embed_url }}"
                            class="h-full w-full"
                            allow="autoplay; fullscreen; picture-in-picture"
                            allowfullscreen
                        ></iframe>
                    </div>

                    <div class="p-4 sm:p-6">
                        <p class="text-xs uppercase tracking-widest text-gray-400">
                            {{ $lesson->course->label }}@if($lesson->course->title): {{ $lesson->course->title }}@endif
                        </p>
                        <h2 class="mt-1 text-lg font-semibold">Aula {{ $lesson->number }} — {{ $lesson->title }}</h2>

                        @if ($lesson->materials->isNotEmpty())
                            <div class="mt-4 flex flex-wrap items-center gap-2">
                                <span class="text-xs uppercase tracking-widest text-gray-400">Materiais de aula:</span>
                                @foreach ($lesson->materials as $material)
                                    <a href="{{ $material->file_url }}" target="_blank" rel="noopener"
                                       class="inline-flex items-center px-3 py-1.5 rounded-md text-sm bg-slate-800/60 text-gray-200 hover:bg-slate-700">
                                        {{ $material->title }}
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <p class="mt-6 text-gray-400">Nenhuma aula disponível ainda.</p>
            @endif
        </section>

        @foreach ($this->courses as $course)
            @if ($course->lessons->isNotEmpty())
                <section
                    x-data="{
                        canScrollLeft: false,
                        canScrollRight: false,
                        update() {
                            this.canScrollLeft = this.$refs.track.scrollLeft > 0;
                            this.canScrollRight = this.$refs.track.scrollLeft + this.$refs.track.clientWidth < this.$refs.track.scrollWidth - 1;
                        },
                    }"
                    x-init="update()"
                >
                    <div class="flex items-end justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">
                                {{ $course->label }}@if($course->title): {{ $course->title }}@endif
                            </h2>
                            @if ($course->description)
                                <p class="mt-2 text-sm text-gray-400">{{ $course->description }}</p>
                            @endif
                        </div>

                        <div class="hidden md:flex gap-2">
                            <button type="button" x-show="canScrollLeft" @click="$refs.track.scrollBy({ left: -300, behavior: 'smooth' })"
                                    class="h-8 w-8 rounded-full border border-slate-700 text-gray-300 hover:bg-slate-800/60">‹</button>
                            <button type="button" x-show="canScrollRight" @click="$refs.track.scrollBy({ left: 300, behavior: 'smooth' })"
                                    class="h-8 w-8 rounded-full border border-slate-700 text-gray-300 hover:bg-slate-800/60">›</button>
                        </div>
                    </div>

                    <div x-ref="track" @scroll.debounce.100ms="update()" class="mt-4 flex gap-4 overflow-x-auto pb-2 scroll-smooth snap-x">
                        @foreach ($course->lessons as $courseLesson)
                            <div class="snap-start">
                                <x-lesson-card :lesson="$courseLesson" :course="$course" :watching="$watchingLessonId === $courseLesson->id" />
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach
    </div>

    <footer class="border-t border-slate-800/60 mt-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-gray-400">
            <div class="flex gap-4">
                <a href="#" class="hover:text-white">Política de Privacidade</a>
                <a href="#" class="hover:text-white">Sobre</a>
            </div>
            <p>&copy; {{ now()->year }} {{ config('app.name') }}. Todos os direitos reservados.</p>
        </div>
    </footer>

    <a href="https://wa.me/{{ config('services.whatsapp.number') }}" target="_blank" rel="noopener"
       class="fixed bottom-4 right-4 h-14 w-14 rounded-full bg-gradient-to-br from-orange-500 to-red-600 flex items-center justify-center shadow-lg hover:brightness-110">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-7 w-7 fill-white">
            <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.79.47 3.47 1.29 4.93L2 22l5.28-1.38a9.9 9.9 0 0 0 4.76 1.21h.01c5.46 0 9.9-4.45 9.9-9.91C21.96 6.45 17.5 2 12.04 2Zm5.8 14.08c-.24.68-1.4 1.3-1.93 1.38-.5.08-1.11.11-1.79-.11-.41-.13-.94-.3-1.62-.6-2.85-1.23-4.7-4.1-4.84-4.29-.14-.19-1.16-1.54-1.16-2.94s.73-2.09 1-2.38c.24-.26.53-.32.71-.32h.5c.16 0 .38-.03.58.44.24.57.81 1.98.88 2.12.07.15.11.31.02.5-.09.19-.14.31-.28.47-.14.16-.29.36-.42.48-.14.13-.28.28-.12.55.16.27.71 1.17 1.53 1.9 1.05.93 1.94 1.22 2.21 1.36.27.13.43.11.59-.07.16-.19.68-.79.86-1.06.18-.27.36-.22.6-.13.24.09 1.55.73 1.82.86.27.14.44.2.51.32.07.13.07.72-.17 1.4Z"/>
        </svg>
    </a>
</div>
```

- [ ] **Step 4: Run the full test suite**

Run: `php artisan test`
Expected: PASS — every test, including the two new markup assertions from Step 1.

- [ ] **Step 5: Manual visual check**

```bash
npm run build
php artisan serve
```

Log in as `test@example.com` (seeded by `DatabaseSeeder`, password `password` unless changed) at `/login`, open `/membros`, and confirm: dark background, hero iframe plays, materials chips show, course carousels scroll with `‹›` toggling correctly at the edges, exactly one card shows "Assistindo", WhatsApp button is fixed bottom-right, header dropdown logs out.

- [ ] **Step 6: Commit**

```bash
git add resources/views/livewire/membros/dashboard.blade.php tests/Feature/Livewire/Membros/DashboardTest.php
git commit -m "Rewrite membros dashboard with hero embed, carousels, and design tokens"
```

---

## Self-Review Notes

- **Spec coverage:** §5 single-component architecture (undoing the `faf31bc` split) → Task 0. §2/§4 self-registration → Task 1. §3 enum + embed → Tasks 2-3. §4 routes/actions → Tasks 0, 1, 4. §5 UI composition (header, hero, materials inline, carousels, footer, card) → Tasks 5-6. §6 business rules (featured lesson, badge exclusivity, upsert) → already implemented, locked in by Task 4's regression tests and Task 6's badge-count test. §7 design tokens → Tasks 5-6 (arbitrary-value Tailwind classes matching the exact hex/gradient values). §8 status table itself needs no code. §9 out-of-scope items are intentionally untouched.
- **Placeholder scan:** no TBD/TODO; the one open value (WhatsApp number) is handled as a real `env()`-backed config, not a code placeholder.
- **Type consistency:** `Lesson::embedUrl/durationFormatted/thumbnailUrl` accessors are referenced consistently as `embed_url`/`duration_formatted`/`thumbnail_url` in every task from Task 3 onward. `Dashboard::userInitials` (Computed) is referenced as `$this->userInitials` consistently in Tasks 4 and 6.
