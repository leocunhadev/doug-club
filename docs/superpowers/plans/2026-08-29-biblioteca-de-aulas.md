# Biblioteca de aulas Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the real `/membros/aulas` page (lista-spec item #2), matching `prototype/aulas.blade.php`'s structure with real `Course`/`Lesson` data — category + tier filtering, a working hero player, and a filterable grid — then move the Início page's course-carousel content into it so Início matches `prototype/home.blade.php` exactly (hero player only, no carousels).

**Architecture:** Two new `Lesson` columns (`category`, `tier`) drive filtering. A new trait
(`TracksLessonProgress`) and Blade component (`<x-lesson-player>`) extract the "hero player + mark as
watched" concern that `Dashboard` already has, so the new `Aulas` component reuses it instead of
duplicating it — both components end up thin. `PersonaNavigation` only flips `membros.aulas` to
`available: true` once the page fully works, so the header nav and the Início "Atalhos" card (which
both already read `available` from there) unlock automatically with no separate change.

**Tech Stack:** Laravel 13, Livewire 3, Alpine.js, Tailwind CSS v3, PHPUnit (`php artisan test`), SQLite (`:memory:` in tests).

**Spec:** `docs/superpowers/specs/2026-08-29-biblioteca-de-aulas-design.md`

## Global Constraints

- `category` is `enum('Encontros','Convidados','Frameworks')`, default `'Encontros'`. `tier` (on
  `Lesson`, distinct from `User::tier`) is `enum('start','club')`, default `'start'`. Both not null.
- `Lesson::isAvailableFor(User $user): bool` is the one place that decides visibility
  (`tier === 'start' || $user->hasClubAccess()`) — filtering code calls this rule via
  `hasClubAccess()`, never compares `tier` strings inline.
- The Aulas grid's lesson count in the "N aulas na sua biblioteca" text filters by tier only, NOT by
  the active category filter — matches the prototype's `total` vs. `visibleAulas()` distinction.
- `<x-lesson-player>` shows real embedded video (iframe) immediately — it does NOT port the
  prototype's fake "click to reveal" player mechanic (`.player`/`.playbtn`/`.fake-video`), since the
  real app has always shown real video here.
- Category filter values (`Tudo`, `Encontros`, `Convidados`, `Frameworks`) are hardcoded in the view,
  not stored anywhere — `Tudo` is a UI-only "no filter" state, never a `category` column value.
- No fabricated content — this plan only reuses lesson/course data that already exists in the DB.

---

## Task 1: `category` + `tier` columns on `Lesson`

**Files:**
- Create: `database/migrations/2026_08_29_130000_add_category_and_tier_to_lessons_table.php`
- Modify: `app/Models/Lesson.php`
- Test: `tests/Unit/LessonPresentationTest.php`

**Interfaces:**
- Produces: `Lesson::isAvailableFor(User $user): bool`; `lessons.category`, `lessons.tier` columns,
  both in `Lesson::$fillable`. Task 4 (Aulas component) and Task 6 (Dashboard's `newestLesson()`)
  both filter list queries on `tier` directly (a `WHERE`, not a per-row method call). Task 3's
  `<x-lesson-player>` is the one place that calls `isAvailableFor()` itself — it's the defensive
  check for the single "featured" lesson (chosen by watch history, not by a tier-filtered query), so
  it's the one path where a stale `LessonProgress` row on a now-club-only lesson could otherwise leak
  the video to a `start`-tier viewer.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Unit/LessonPresentationTest.php` (this file already has a `makeLesson(array $overrides = [])`
helper — add these test methods using it):

```php
    public function test_is_available_for_start_tier_lesson_regardless_of_user_tier(): void
    {
        $lesson = $this->makeLesson(['tier' => 'start']);

        $this->assertTrue($lesson->isAvailableFor(User::factory()->create(['tier' => 'start'])));
        $this->assertTrue($lesson->isAvailableFor(User::factory()->create(['tier' => 'club'])));
    }

    public function test_is_available_for_club_tier_lesson_requires_club_access(): void
    {
        $lesson = $this->makeLesson(['tier' => 'club']);

        $this->assertFalse($lesson->isAvailableFor(User::factory()->create(['tier' => 'start'])));
        $this->assertTrue($lesson->isAvailableFor(User::factory()->create(['tier' => 'club'])));
        $this->assertTrue($lesson->isAvailableFor(User::factory()->create(['tier' => 'mentor'])));
    }

    public function test_lesson_defaults_to_encontros_category_and_start_tier(): void
    {
        $lesson = $this->makeLesson();
        $lesson->refresh();

        $this->assertSame('Encontros', $lesson->category);
        $this->assertSame('start', $lesson->tier);
    }
```

Add `use App\Models\User;` to this file's imports (it currently only imports `Course` and `Lesson`).

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Unit/LessonPresentationTest.php`
Expected: FAIL — `category`/`tier` columns don't exist yet, `isAvailableFor()` is undefined.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_08_29_130000_add_category_and_tier_to_lessons_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->enum('category', ['Encontros', 'Convidados', 'Frameworks'])->default('Encontros')->after('course_id');
            $table->enum('tier', ['start', 'club'])->default('start')->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn(['category', 'tier']);
        });
    }
};
```

- [ ] **Step 4: Update `app/Models/Lesson.php`**

Add `'category'` and `'tier'` to the `$fillable` array (after `'position'`):

```php
    protected $fillable = [
        'course_id',
        'number',
        'title',
        'duration_seconds',
        'video_provider',
        'video_id',
        'thumbnail_path',
        'published_at',
        'position',
        'category',
        'tier',
    ];
```

Add the import `use App\Models\User;` and this method (near `course()`/`materials()`):

```php
    public function isAvailableFor(User $user): bool
    {
        return $this->tier === 'start' || $user->hasClubAccess();
    }
```

- [ ] **Step 5: Run migration and tests**

Run: `php artisan migrate`
Run: `php artisan test tests/Unit/LessonPresentationTest.php`
Expected: PASS (all tests in the file, including the 3 new ones).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_29_130000_add_category_and_tier_to_lessons_table.php app/Models/Lesson.php tests/Unit/LessonPresentationTest.php
git commit -m "feat: add category and tier columns to Lesson"
```

---

## Task 2: Seed plausible category/tier values

**Files:**
- Modify: `database/seeders/LmsSeeder.php`

**Interfaces:**
- Consumes: `lessons.category`, `lessons.tier` (Task 1).
- No test — this is dev-only seed data (spec §1, §8: verify manually with `migrate:fresh --seed`).

- [ ] **Step 1: Add a `category` per course and mark each course's newest lesson as `club`**

In `database/seeders/LmsSeeder.php`, add a `'category'` key to each of the 5 course arrays in `$courses`
(the `Boas Vindas`/`Módulo 4`/`Curso 3`/`Curso 2`/`Curso 1` entries), then use it when creating lessons.
Replace:

```php
        $courses = [
            [
                'label' => 'Boas Vindas',
                'title' => '',
                'description' => null,
                'position' => 50,
                'lessons' => [
```

with:

```php
        $courses = [
            [
                'label' => 'Boas Vindas',
                'title' => '',
                'description' => null,
                'position' => 50,
                'category' => 'Encontros',
                'lessons' => [
```

Apply the same one-line addition (`'category' => '...',` right after `'position' => N,`) to the other
4 course arrays, using: `Módulo 4` → `'Frameworks'`, `Curso 3` (Liderança e Recrutamento) →
`'Convidados'`, `Curso 2` (Influência) → `'Encontros'`, `Curso 1` (Vendas) → `'Convidados'`.

Then replace the lesson-creation loop:

```php
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
```

with:

```php
        foreach ($courses as $courseData) {
            $lessons = $courseData['lessons'];
            $category = $courseData['category'];
            unset($courseData['lessons'], $courseData['category']);

            $course = Course::create($courseData);

            foreach ($lessons as $index => $lessonData) {
                $video = $demoVideos[$videoIndex % count($demoVideos)];
                $videoIndex++;

                $course->lessons()->create($lessonData + [
                    'video_provider' => $video['provider'],
                    'video_id' => $video['id'],
                    'position' => count($lessons) - $index,
                    'category' => $category,
                    'tier' => ($index === 0 && count($lessons) > 1) ? 'club' : 'start',
                ]);
            }
        }
```

(`$index === 0` is the newest lesson in each course, since the array lists lessons newest-first;
`count($lessons) > 1` keeps the single-lesson "Boas Vindas" course fully `start` — a welcome video
should never be club-locked.)

- [ ] **Step 2: Verify manually**

Run: `php artisan migrate:fresh --seed`
Expected: completes with no error. Optionally spot-check: `php artisan tinker --execute="echo App\Models\Lesson::where('tier','club')->count();"` should print a number greater than 0.

- [ ] **Step 3: Commit**

```bash
git add database/seeders/LmsSeeder.php
git commit -m "feat: seed plausible category/tier values for demo lessons"
```

---

## Task 3: Extract `TracksLessonProgress` trait + `<x-lesson-player>` component

Mostly a refactor — `Dashboard`'s visible behavior must not change for any lesson a viewer is allowed
to see. The one real addition: `<x-lesson-player>` refuses to render a lesson the viewer isn't
allowed to see (`Lesson::isAvailableFor()`, from Task 1), falling back to the same "Nenhuma aula
disponível" state used when there's no featured lesson at all. This closes a gap that exists in the
current code: `DetermineFeaturedLesson` picks a lesson from watch history without checking tier, so a
user downgraded from `club` to `start` after watching a club-only lesson would otherwise still see it
in "Continuar assistindo." This is what Task 4's `Aulas` component reuses, gaining the same
protection for free.

**Files:**
- Create: `app/Livewire/Concerns/TracksLessonProgress.php`
- Create: `resources/views/components/lesson-player.blade.php`
- Modify: `app/Livewire/Membros/Dashboard.php`
- Modify: `resources/views/livewire/membros/dashboard.blade.php`
- Test: `tests/Feature/Livewire/Membros/DashboardTest.php`

**Interfaces:**
- Produces: trait `App\Livewire\Concerns\TracksLessonProgress` (provides `$featuredLessonId`,
  `mount()`, `watchLesson()`, `updateProgress()`, `markCompleted()`, `featuredLesson()` computed,
  `featuredProgress()` computed) and `<x-lesson-player :lesson="..." :progress="..." />`. Task 4's
  `Aulas` component uses both exactly as `Dashboard` will after this task.

- [ ] **Step 1: Create the trait**

Create `app/Livewire/Concerns/TracksLessonProgress.php` — this is `Dashboard.php`'s current
`$featuredLessonId` property plus its `mount()`, `watchLesson()`, `updateProgress()`,
`markCompleted()`, `featuredLesson()`, and `featuredProgress()`, moved verbatim:

```php
<?php

namespace App\Livewire\Concerns;

use App\Actions\DetermineFeaturedLesson;
use App\Actions\MarkLessonAsCompleted;
use App\Actions\MarkLessonAsWatching;
use App\Actions\UpdateLessonWatchedSeconds;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

trait TracksLessonProgress
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

    public function updateProgress(int $lessonId, int $seconds, UpdateLessonWatchedSeconds $action): void
    {
        $action->handle(Auth::id(), $lessonId, $seconds);
    }

    public function markCompleted(int $lessonId, MarkLessonAsCompleted $action): void
    {
        $action->handle(Auth::id(), $lessonId);
    }

    #[Computed]
    public function featuredLesson(): ?Lesson
    {
        return Lesson::query()->with(['course', 'materials'])->find($this->featuredLessonId);
    }

    #[Computed]
    public function featuredProgress(): ?LessonProgress
    {
        if ($this->featuredLessonId === null) {
            return null;
        }

        return LessonProgress::query()
            ->where('user_id', Auth::id())
            ->where('lesson_id', $this->featuredLessonId)
            ->first();
    }
}
```

- [ ] **Step 2: Write the failing test for the tier-gating check**

Add to `tests/Feature/Livewire/Membros/DashboardTest.php`:

```php
    public function test_hero_player_refuses_to_render_a_club_only_lesson_for_a_start_tier_viewer(): void
    {
        $user = User::factory()->create(['tier' => 'start']);
        $course = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);
        $clubLesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula só de club',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
            'category' => 'Encontros', 'tier' => 'club',
        ]);

        LessonProgress::create([
            'user_id' => $user->id, 'lesson_id' => $clubLesson->id,
            'status' => 'watching', 'last_watched_at' => now(),
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertDontSee('Aula só de club')
            ->assertSee('Nenhuma aula disponível ainda.');
    }
```

(This simulates a user who watched a lesson while on `club`, then got downgraded to `start` — the
`LessonProgress` row still points `DetermineFeaturedLesson` at that lesson.)

- [ ] **Step 3: Run the test to verify it fails**

Run: `php artisan test tests/Feature/Livewire/Membros/DashboardTest.php --filter=test_hero_player_refuses_to_render_a_club_only_lesson_for_a_start_tier_viewer`
Expected: FAIL — today's hero block has no tier check, so it renders the club lesson regardless of viewer tier.

- [ ] **Step 4: Create `<x-lesson-player>`**

Create `resources/views/components/lesson-player.blade.php` — this is `dashboard.blade.php`'s current
hero block (the `@if ($lesson = $this->featuredLesson) ... @else ... @endif`), turned into a
standalone component taking `$lesson`/`$progress` as props instead of reading them from `$this`, with
one added condition: the lesson only renders if `$lesson->isAvailableFor(auth()->user())`.

```blade
@props(['lesson', 'progress'])

@if ($lesson && $lesson->isAvailableFor(auth()->user()))
    <div
        wire:key="hero-player-{{ $lesson->id }}"
        x-data="vimeoProgress({
            lessonId: {{ $lesson->id }},
            provider: '{{ $lesson->video_provider }}',
            initialSeconds: {{ $progress?->watched_seconds ?? 0 }},
        })"
        class="mt-6 rounded-2xl border border-sand bg-card p-3 sm:p-4"
    >
        <div class="relative aspect-video overflow-hidden rounded-xl">
            <iframe
                x-ref="iframe"
                src="{{ $lesson->embed_url }}"
                class="h-full w-full"
                allow="autoplay; fullscreen; picture-in-picture"
                allowfullscreen
            ></iframe>
            <x-brand-logo icon-only class="pointer-events-none absolute top-3 right-3 h-6 w-auto drop-shadow" />
        </div>
    </div>

    <div class="mt-4">
        @if ($lesson->materials->isNotEmpty())
            <div x-data="{ open: false }" class="relative inline-block">
                <button type="button" @click="open = !open" @click.outside="open = false"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-card border border-sand text-ink hover:bg-paper">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 fill-current">
                        <path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6Z"/>
                    </svg>
                    Materiais de aula
                </button>

                <div x-show="open" x-cloak x-transition
                     class="absolute left-0 z-10 mt-2 min-w-[14rem] rounded-lg border border-sand bg-card py-1 shadow-lg">
                    @foreach ($lesson->materials as $material)
                        @if ($material->hasUploadedFile())
                            <a href="{{ route('membros.materials.download', $material) }}"
                               class="block px-4 py-2 text-sm text-ink hover:bg-paper">
                                {{ $material->title }}
                            </a>
                        @else
                            <a href="{{ $material->file_url }}" target="_blank" rel="noopener"
                               class="block px-4 py-2 text-sm text-ink hover:bg-paper">
                                {{ $material->title }}
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        @else
            <span class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-card border border-sand text-stone cursor-not-allowed">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 fill-current">
                    <path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6Z"/>
                </svg>
                Materiais de aula
            </span>
        @endif
    </div>
@else
    <p class="mt-6 text-stone">Nenhuma aula disponível ainda.</p>
@endif
```

The Step 2 test still won't pass until `dashboard.blade.php` actually renders this component instead
of its old inline markup — that's Steps 6-7 below. Continue through them before checking GREEN.

- [ ] **Step 5: Update `Dashboard.php`**

Replace the full file with:

```php
<?php

namespace App\Livewire\Membros;

use App\Livewire\Concerns\ComputesUserInitials;
use App\Livewire\Concerns\TracksLessonProgress;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Support\PersonaNavigation;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.membros')]
class Dashboard extends Component
{
    use ComputesUserInitials, TracksLessonProgress;

    #[Computed]
    public function courses()
    {
        return Course::query()
            ->with('lessons')
            ->orderByDesc('position')
            ->get();
    }

    #[Computed]
    public function newestLesson(): ?Lesson
    {
        return Lesson::query()
            ->with('course')
            ->orderByDesc('published_at')
            ->orderByDesc('position')
            ->first();
    }

    /**
     * @return array<int, array{label: string, route: string, available: bool}>
     */
    #[Computed]
    public function quickLinks(): array
    {
        $availability = collect((new PersonaNavigation)->tabs(Auth::user()->tier))->keyBy('route');

        $thirdLink = Auth::user()->hasClubAccess()
            ? ['label' => 'Marcar minha sessão', 'route' => 'membros.agenda']
            : ['label' => 'Conhecer o CLUB', 'route' => 'membros.upgrade'];

        return collect([
            ['label' => 'Biblioteca de aulas', 'route' => 'membros.aulas'],
            ['label' => 'Frameworks DO', 'route' => 'membros.frameworks'],
            $thirdLink,
        ])->map(fn (array $link) => [
            ...$link,
            'available' => $availability->get($link['route'])['available'] ?? false,
        ])->all();
    }

    public function render()
    {
        return view('livewire.membros.dashboard', [
            'watchingLessonId' => LessonProgress::query()
                ->where('user_id', Auth::id())
                ->where('lesson_id', $this->featuredLessonId)
                ->where('status', 'watching')
                ->exists() ? $this->featuredLessonId : null,
        ]);
    }
}
```

(This step keeps `courses()`/`watchingLessonId` — Task 6 removes them once the carousel markup that
uses them is gone. Keeping them here means this task is a pure refactor with zero behavior change,
independently verifiable.)

- [ ] **Step 6: Update `dashboard.blade.php`'s hero block**

Replace (the whole `@if ($lesson = $this->featuredLesson) ... @else ... @endif` block, lines 31-92 of
the current file):

```blade
            @if ($lesson = $this->featuredLesson)
                <div
                    wire:key="hero-player-{{ $lesson->id }}"
                    x-data="vimeoProgress({
                        lessonId: {{ $lesson->id }},
                        provider: '{{ $lesson->video_provider }}',
                        initialSeconds: {{ $this->featuredProgress?->watched_seconds ?? 0 }},
                    })"
                    class="mt-6 rounded-2xl border border-sand bg-card p-3 sm:p-4"
                >
                    <div class="relative aspect-video overflow-hidden rounded-xl">
                        <iframe
                            x-ref="iframe"
                            src="{{ $lesson->embed_url }}"
                            class="h-full w-full"
                            allow="autoplay; fullscreen; picture-in-picture"
                            allowfullscreen
                        ></iframe>
                        <x-brand-logo icon-only class="pointer-events-none absolute top-3 right-3 h-6 w-auto drop-shadow" />
                    </div>
                </div>

                <div class="mt-4">
                    @if ($lesson->materials->isNotEmpty())
                        <div x-data="{ open: false }" class="relative inline-block">
                            <button type="button" @click="open = !open" @click.outside="open = false"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-card border border-sand text-ink hover:bg-paper">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 fill-current">
                                    <path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6Z"/>
                                </svg>
                                Materiais de aula
                            </button>

                            <div x-show="open" x-cloak x-transition
                                 class="absolute left-0 z-10 mt-2 min-w-[14rem] rounded-lg border border-sand bg-card py-1 shadow-lg">
                                @foreach ($lesson->materials as $material)
                                    @if ($material->hasUploadedFile())
                                        <a href="{{ route('membros.materials.download', $material) }}"
                                           class="block px-4 py-2 text-sm text-ink hover:bg-paper">
                                            {{ $material->title }}
                                        </a>
                                    @else
                                        <a href="{{ $material->file_url }}" target="_blank" rel="noopener"
                                           class="block px-4 py-2 text-sm text-ink hover:bg-paper">
                                            {{ $material->title }}
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @else
                        <span class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-card border border-sand text-stone cursor-not-allowed">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 fill-current">
                                <path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6Z"/>
                            </svg>
                            Materiais de aula
                        </span>
                    @endif
                </div>
            @else
                <p class="mt-6 text-stone">Nenhuma aula disponível ainda.</p>
            @endif
```

with:

```blade
            <x-lesson-player :lesson="$this->featuredLesson" :progress="$this->featuredProgress" />
```

Do not touch anything else in this file (the eyebrow/H1, the `.mt-8 grid ...` wrapper it sits inside,
the side-stack cards, or the course-carousel `@foreach` below it — those are untouched by this task).

- [ ] **Step 7: Run the gating test to confirm GREEN**

Run: `php artisan test tests/Feature/Livewire/Membros/DashboardTest.php --filter=test_hero_player_refuses_to_render_a_club_only_lesson_for_a_start_tier_viewer`
Expected: PASS — `dashboard.blade.php` now renders through `<x-lesson-player>`, which applies the
`isAvailableFor()` check from Step 4.

- [ ] **Step 8: Run the full suite**

Run: `php artisan test`
Expected: PASS — one more test than before this task (the new gating test from Step 2). Every
pre-existing test should be otherwise unaffected: `test_hero_player_wires_up_the_vimeo_progress_component_for_vimeo_lessons`,
`test_hero_player_passes_the_saved_watched_seconds_into_the_alpine_component`, and
`test_dashboard_renders_featured_lesson_embed_and_materials` all check markup that
`<x-lesson-player>` still renders identically for any lesson the viewer is allowed to see.

- [ ] **Step 9: Commit**

```bash
git add app/Livewire/Concerns/TracksLessonProgress.php resources/views/components/lesson-player.blade.php app/Livewire/Membros/Dashboard.php resources/views/livewire/membros/dashboard.blade.php tests/Feature/Livewire/Membros/DashboardTest.php
git commit -m "refactor: extract TracksLessonProgress trait and x-lesson-player component

Also closes a tier-gating gap: the hero player now refuses to render
a lesson the viewer's current tier can't access, even if their watch
history points at it (e.g. after a club->start downgrade)."
```

---

## Task 4: The `/membros/aulas` page

**Files:**
- Create: `app/Livewire/Membros/Aulas.php`
- Create: `resources/views/livewire/membros/aulas.blade.php`
- Create: `resources/views/components/aula-card.blade.php`
- Modify: `resources/css/app.css`
- Modify: `routes/web.php`
- Test: `tests/Feature/Livewire/Membros/AulasTest.php`

**Interfaces:**
- Consumes: `TracksLessonProgress` trait, `<x-lesson-player>` (Task 3); `Lesson::isAvailableFor()`
  concept expressed via `hasClubAccess()`-gated queries (Task 1).
- Produces: named route `membros.aulas` (`GET /membros/aulas`); `App\Livewire\Membros\Aulas` with
  public property `$category` and computed `lessons()`/`totalCount()`. Task 5 points
  `PersonaNavigation`'s `Aulas` tab at this exact route name.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Livewire/Membros/AulasTest.php`:

```php
<?php

namespace Tests\Feature\Livewire\Membros;

use App\Livewire\Membros\Aulas;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AulasTest extends TestCase
{
    use RefreshDatabase;

    private function course(): Course
    {
        return Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/membros/aulas')->assertRedirect('/login');
    }

    public function test_start_tier_only_sees_start_tier_lessons(): void
    {
        $course = $this->course();
        Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula start',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
            'category' => 'Encontros', 'tier' => 'start',
        ]);
        Lesson::create([
            'course_id' => $course->id, 'number' => 2, 'title' => 'Aula club',
            'video_provider' => 'youtube', 'video_id' => 'def', 'published_at' => '2026-01-02', 'position' => 2,
            'category' => 'Encontros', 'tier' => 'club',
        ]);

        $this->actingAs(User::factory()->create(['tier' => 'start']));

        Livewire::test(Aulas::class)
            ->assertSee('Aula start')
            ->assertDontSee('Aula club');
    }

    public function test_club_tier_sees_every_lesson(): void
    {
        $course = $this->course();
        Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula start',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
            'category' => 'Encontros', 'tier' => 'start',
        ]);
        Lesson::create([
            'course_id' => $course->id, 'number' => 2, 'title' => 'Aula club',
            'video_provider' => 'youtube', 'video_id' => 'def', 'published_at' => '2026-01-02', 'position' => 2,
            'category' => 'Encontros', 'tier' => 'club',
        ]);

        $this->actingAs(User::factory()->create(['tier' => 'club']));

        Livewire::test(Aulas::class)
            ->assertSee('Aula start')
            ->assertSee('Aula club');
    }

    public function test_exclusivo_club_suffix_shown_only_on_club_tier_cards(): void
    {
        $course = $this->course();
        Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula start',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
            'category' => 'Encontros', 'tier' => 'start',
        ]);
        Lesson::create([
            'course_id' => $course->id, 'number' => 2, 'title' => 'Aula club',
            'video_provider' => 'youtube', 'video_id' => 'def', 'published_at' => '2026-01-02', 'position' => 2,
            'category' => 'Encontros', 'tier' => 'club',
        ]);

        $this->actingAs(User::factory()->create(['tier' => 'club']));

        $html = Livewire::test(Aulas::class)->html();

        $this->assertSame(1, substr_count($html, 'Exclusivo CLUB'));
    }

    public function test_category_filter_narrows_the_grid(): void
    {
        $course = $this->course();
        Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula encontro',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
            'category' => 'Encontros', 'tier' => 'start',
        ]);
        Lesson::create([
            'course_id' => $course->id, 'number' => 2, 'title' => 'Aula framework',
            'video_provider' => 'youtube', 'video_id' => 'def', 'published_at' => '2026-01-02', 'position' => 2,
            'category' => 'Frameworks', 'tier' => 'start',
        ]);

        $this->actingAs(User::factory()->create(['tier' => 'club']));

        Livewire::test(Aulas::class)
            ->call('selectCategory', 'Frameworks')
            ->assertSee('Aula framework')
            ->assertDontSee('Aula encontro');
    }

    public function test_total_count_ignores_the_category_filter_but_respects_tier(): void
    {
        $course = $this->course();
        Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula encontro start',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
            'category' => 'Encontros', 'tier' => 'start',
        ]);
        Lesson::create([
            'course_id' => $course->id, 'number' => 2, 'title' => 'Aula framework club',
            'video_provider' => 'youtube', 'video_id' => 'def', 'published_at' => '2026-01-02', 'position' => 2,
            'category' => 'Frameworks', 'tier' => 'club',
        ]);

        $this->actingAs(User::factory()->create(['tier' => 'start']));

        Livewire::test(Aulas::class)
            ->call('selectCategory', 'Encontros')
            ->assertSet('category', 'Encontros')
            ->assertSee('1 aulas na sua biblioteca');
    }

    public function test_watching_a_lesson_from_the_grid_updates_the_hero_player(): void
    {
        $course = $this->course();
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula 1',
            'video_provider' => 'vimeo', 'video_id' => '76979871', 'published_at' => '2026-01-01', 'position' => 1,
            'category' => 'Encontros', 'tier' => 'start',
        ]);

        $this->actingAs(User::factory()->create());

        Livewire::test(Aulas::class)
            ->call('watchLesson', $lesson->id)
            ->assertSet('featuredLessonId', $lesson->id)
            ->assertSee("wire:key=\"hero-player-{$lesson->id}\"", false);
    }

    public function test_watching_badge_appears_on_exactly_the_featured_lesson_card(): void
    {
        $course = $this->course();
        $watchedLesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula 1',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 2,
            'category' => 'Encontros', 'tier' => 'start',
        ]);
        Lesson::create([
            'course_id' => $course->id, 'number' => 2, 'title' => 'Aula 2',
            'video_provider' => 'youtube', 'video_id' => 'def', 'published_at' => '2026-01-02', 'position' => 1,
            'category' => 'Encontros', 'tier' => 'start',
        ]);

        $user = User::factory()->create();
        LessonProgress::create([
            'user_id' => $user->id, 'lesson_id' => $watchedLesson->id,
            'status' => 'watching', 'last_watched_at' => now(),
        ]);

        $this->actingAs($user);

        $html = Livewire::test(Aulas::class)->html();

        $this->assertSame(1, substr_count($html, 'Assistindo'));

        preg_match_all(
            '/<button[^>]*wire:click="watchLesson\((\d+)\)"[^>]*>(.*?)<\/button>/s',
            $html,
            $cards,
            PREG_SET_ORDER,
        );

        $cardsWithBadge = array_values(array_filter($cards, fn (array $card) => str_contains($card[2], 'Assistindo')));

        $this->assertCount(1, $cardsWithBadge, 'Expected exactly one card to contain the "Assistindo" badge.');
        $this->assertSame((string) $watchedLesson->id, $cardsWithBadge[0][1]);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Livewire/Membros/AulasTest.php`
Expected: FAIL — route `membros.aulas` / class `App\Livewire\Membros\Aulas` don't exist.

- [ ] **Step 3: Create the Livewire component**

Create `app/Livewire/Membros/Aulas.php`:

```php
<?php

namespace App\Livewire\Membros;

use App\Livewire\Concerns\ComputesUserInitials;
use App\Livewire\Concerns\TracksLessonProgress;
use App\Models\Lesson;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.membros')]
class Aulas extends Component
{
    use ComputesUserInitials, TracksLessonProgress;

    public string $category = 'Tudo';

    public function selectCategory(string $category): void
    {
        $this->category = $category;
    }

    #[Computed]
    public function lessons()
    {
        return Lesson::query()->with('course')
            ->when(! Auth::user()->hasClubAccess(), fn ($q) => $q->where('tier', 'start'))
            ->when($this->category !== 'Tudo', fn ($q) => $q->where('category', $this->category))
            ->orderByDesc('published_at')
            ->orderByDesc('position')
            ->get();
    }

    #[Computed]
    public function totalCount(): int
    {
        return Lesson::query()
            ->when(! Auth::user()->hasClubAccess(), fn ($q) => $q->where('tier', 'start'))
            ->count();
    }

    public function render()
    {
        return view('livewire.membros.aulas');
    }
}
```

- [ ] **Step 4: Create `<x-aula-card>`**

Create `resources/views/components/aula-card.blade.php`:

```blade
@props(['lesson', 'watching' => false])

<button
    type="button"
    wire:click="watchLesson({{ $lesson->id }})"
    class="text-left overflow-hidden rounded-[18px] border border-sand bg-card shadow-[0_1px_2px_rgba(11,11,12,.05),0_10px_28px_rgba(11,11,12,.07)] transition hover:-translate-y-0.5"
>
    <div class="aula-card-thumb">
        @if ($watching)
            <span class="absolute top-2.5 left-2.5 text-xs font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full bg-brand text-white">
                Assistindo
            </span>
        @endif
        <span class="aula-card-number">{{ sprintf('%02d', $lesson->number) }}</span>
        <span class="aula-card-play"></span>
    </div>
    <div class="p-3.5">
        <b class="font-display text-sm block leading-tight">{{ $lesson->title }}</b>
        <small class="mt-1 block text-xs text-stone">
            {{ $lesson->course->label }}@if ($lesson->course->title): {{ $lesson->course->title }}@endif
            @if ($lesson->tier === 'club') · Exclusivo CLUB @endif
        </small>
    </div>
</button>
```

- [ ] **Step 5: Add the aula-card CSS**

Add to the end of `resources/css/app.css`:

```css
.aula-card-thumb {
    aspect-ratio: 16 / 9;
    background: theme('colors.black');
    position: relative;
    display: flex;
    align-items: flex-end;
    padding: 14px;
    border-radius: 18px 18px 0 0;
    overflow: hidden;
}
.aula-card-number {
    font-family: 'Syne', sans-serif;
    font-weight: 800;
    font-size: 44px;
    color: transparent;
    -webkit-text-stroke: 1.5px theme('colors.brand');
    position: absolute;
    top: 8px;
    right: 14px;
    opacity: .9;
}
.aula-card-play {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: theme('colors.brand');
    display: flex;
    align-items: center;
    justify-content: center;
}
.aula-card-play::after {
    content: "";
    border-style: solid;
    border-width: 6px 0 6px 10px;
    border-color: transparent transparent transparent #fff;
    margin-left: 2px;
}
```

- [ ] **Step 6: Create the view**

Create `resources/views/livewire/membros/aulas.blade.php`:

```blade
<div class="min-h-screen text-ink">
    <x-membros.header :initials="$this->userInitials" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="mb-8">
            <h1 class="text-[clamp(26px,4vw,38px)] leading-[1.05] font-display font-extrabold tracking-[-0.015em] text-black">
                Biblioteca de aulas
            </h1>
            <p class="mt-2 max-w-xl text-stone">
                Todos os encontros gravados, aulas de convidados e frameworks em vídeo. Aperte o play e continue de onde parou.
            </p>
        </div>

        <x-lesson-player :lesson="$this->featuredLesson" :progress="$this->featuredProgress" />

        <p class="mt-4 text-sm text-stone">
            Assistindo agora: <b class="font-semibold text-ink">{{ $this->featuredLesson?->title ?? '—' }}</b>
            · {{ $this->totalCount }} aulas na sua biblioteca
        </p>

        <div class="mt-6 flex flex-wrap gap-2">
            @foreach (['Tudo', 'Encontros', 'Convidados', 'Frameworks'] as $cat)
                <button type="button" wire:click="selectCategory('{{ $cat }}')"
                        class="px-3.5 py-1.5 rounded-full text-sm font-medium border {{ $category === $cat ? 'bg-black text-white border-black' : 'bg-card text-stone border-sand hover:text-ink' }}">
                    {{ $cat }}
                </button>
            @endforeach
        </div>

        <div class="mt-6 grid grid-cols-[repeat(auto-fill,minmax(240px,1fr))] gap-4">
            @foreach ($this->lessons as $lesson)
                <x-aula-card :lesson="$lesson" :watching="$this->featuredLessonId === $lesson->id" />
            @endforeach
        </div>
    </div>

    <x-membros.footer />
</div>
```

- [ ] **Step 7: Register the route**

In `routes/web.php`, add the import:

```php
use App\Livewire\Membros\Aulas;
```

Then, after the `membros.sobre` route, add:

```php
Route::get('membros/aulas', Aulas::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('membros.aulas');
```

(`PersonaNavigation` still marks this tab `available: false` until Task 5 — visiting the URL directly
already works after this task, it just isn't linked from the nav yet.)

- [ ] **Step 8: Run the tests**

Run: `php artisan test tests/Feature/Livewire/Membros/AulasTest.php`
Expected: PASS (all 8 tests).

Run: `npm run build`
Expected: builds without error.

- [ ] **Step 9: Commit**

```bash
git add app/Livewire/Membros/Aulas.php resources/views/livewire/membros/aulas.blade.php resources/views/components/aula-card.blade.php resources/css/app.css routes/web.php tests/Feature/Livewire/Membros/AulasTest.php
git commit -m "feat: add the real Biblioteca de aulas page"
```

---

## Task 5: Unlock the Aulas nav tab

**Files:**
- Modify: `app/Support/PersonaNavigation.php`
- Modify: `tests/Unit/Support/PersonaNavigationTest.php`
- Modify: `tests/Feature/Membros/PersonaNavigationTest.php`
- Modify: `tests/Feature/Livewire/Membros/DashboardTest.php`

**Interfaces:**
- Consumes: named route `membros.aulas` (Task 4) — must exist before this task runs, since
  `x-membros.header` calls `route($tab['route'])` for every tab marked `available: true`.
  `Dashboard::quickLinks()` (Task 3) reads this same `available` flag, so flipping it here also
  changes what `DashboardTest.php` sees — Step 4 below fixes the one test that asserted the old,
  locked state.

- [ ] **Step 1: Write the failing tests**

In `tests/Unit/Support/PersonaNavigationTest.php`, replace:

```php
    public function test_start_tier_has_one_available_tab_and_three_locked_tabs(): void
    {
        $tabs = (new PersonaNavigation)->tabs('start');

        $this->assertCount(4, $tabs);
        $this->assertSame(['Início', 'Aulas', 'Frameworks', 'Sessão 1:1'], array_column($tabs, 'label'));
        $this->assertSame([true, false, false, false], array_column($tabs, 'available'));
    }

    public function test_club_tier_has_one_available_tab_and_six_locked_tabs(): void
    {
        $tabs = (new PersonaNavigation)->tabs('club');

        $this->assertCount(7, $tabs);
        $this->assertSame(
            ['Início', 'Aulas', 'Meu cofre', 'Minha sessão', 'Pessoas', 'Encontros', 'Frameworks'],
            array_column($tabs, 'label'),
        );
        $this->assertSame([true, false, false, false, false, false, false], array_column($tabs, 'available'));
    }
```

with:

```php
    public function test_start_tier_has_two_available_tabs_and_two_locked_tabs(): void
    {
        $tabs = (new PersonaNavigation)->tabs('start');

        $this->assertCount(4, $tabs);
        $this->assertSame(['Início', 'Aulas', 'Frameworks', 'Sessão 1:1'], array_column($tabs, 'label'));
        $this->assertSame([true, true, false, false], array_column($tabs, 'available'));
    }

    public function test_club_tier_has_two_available_tabs_and_five_locked_tabs(): void
    {
        $tabs = (new PersonaNavigation)->tabs('club');

        $this->assertCount(7, $tabs);
        $this->assertSame(
            ['Início', 'Aulas', 'Meu cofre', 'Minha sessão', 'Pessoas', 'Encontros', 'Frameworks'],
            array_column($tabs, 'label'),
        );
        $this->assertSame([true, true, false, false, false, false, false], array_column($tabs, 'available'));
    }
```

In `tests/Feature/Membros/PersonaNavigationTest.php`, replace:

```php
    public function test_start_tier_shows_inicio_as_a_link_and_the_rest_locked(): void
    {
        $user = User::factory()->create(['tier' => 'start']);
        $this->actingAs($user);

        $html = $this->get('/membros')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<a[^>]*href="http://localhost/membros"[^>]*>\s*Início\s*</a>#s',
            $html,
        );

        foreach (['Aulas', 'Frameworks', 'Sessão 1:1'] as $label) {
            $this->assertMatchesRegularExpression(
                '#<span[^>]*title="Em breve"[^>]*>\s*'.preg_quote($label, '#').'#s',
                $html,
            );
        }
    }

    public function test_club_tier_shows_inicio_as_a_link_and_six_tabs_locked(): void
    {
        $user = User::factory()->create(['tier' => 'club']);
        $this->actingAs($user);

        $html = $this->get('/membros')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<a[^>]*href="http://localhost/membros"[^>]*>\s*Início\s*</a>#s',
            $html,
        );

        foreach (['Aulas', 'Meu cofre', 'Minha sessão', 'Pessoas', 'Encontros', 'Frameworks'] as $label) {
            $this->assertMatchesRegularExpression(
                '#<span[^>]*title="Em breve"[^>]*>\s*'.preg_quote($label, '#').'#s',
                $html,
            );
        }
    }
```

with:

```php
    public function test_start_tier_shows_inicio_and_aulas_as_links_and_the_rest_locked(): void
    {
        $user = User::factory()->create(['tier' => 'start']);
        $this->actingAs($user);

        $html = $this->get('/membros')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<a[^>]*href="http://localhost/membros"[^>]*>\s*Início\s*</a>#s',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '#<a[^>]*href="http://localhost/membros/aulas"[^>]*>\s*Aulas\s*</a>#s',
            $html,
        );

        foreach (['Frameworks', 'Sessão 1:1'] as $label) {
            $this->assertMatchesRegularExpression(
                '#<span[^>]*title="Em breve"[^>]*>\s*'.preg_quote($label, '#').'#s',
                $html,
            );
        }
    }

    public function test_club_tier_shows_inicio_and_aulas_as_links_and_five_tabs_locked(): void
    {
        $user = User::factory()->create(['tier' => 'club']);
        $this->actingAs($user);

        $html = $this->get('/membros')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<a[^>]*href="http://localhost/membros"[^>]*>\s*Início\s*</a>#s',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '#<a[^>]*href="http://localhost/membros/aulas"[^>]*>\s*Aulas\s*</a>#s',
            $html,
        );

        foreach (['Meu cofre', 'Minha sessão', 'Pessoas', 'Encontros', 'Frameworks'] as $label) {
            $this->assertMatchesRegularExpression(
                '#<span[^>]*title="Em breve"[^>]*>\s*'.preg_quote($label, '#').'#s',
                $html,
            );
        }
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Unit/Support/PersonaNavigationTest.php tests/Feature/Membros/PersonaNavigationTest.php`
Expected: FAIL — `Aulas` is still `available: false`, and `route('membros.aulas')` is never called by
the header yet so no `<a href=".../membros/aulas">` exists.

- [ ] **Step 3: Update `PersonaNavigation.php`**

In `app/Support/PersonaNavigation.php`, change the `Aulas` entry's `available` from `false` to `true`
in both the `'start'` and `'club'` arrays (2 one-word edits):

```php
            'start' => [
                ['label' => 'Início', 'route' => 'dashboard', 'available' => true],
                ['label' => 'Aulas', 'route' => 'membros.aulas', 'available' => true],
                ['label' => 'Frameworks', 'route' => 'membros.frameworks', 'available' => false],
                ['label' => 'Sessão 1:1', 'route' => 'membros.upgrade', 'available' => false],
            ],
            'club' => [
                ['label' => 'Início', 'route' => 'dashboard', 'available' => true],
                ['label' => 'Aulas', 'route' => 'membros.aulas', 'available' => true],
                ['label' => 'Meu cofre', 'route' => 'membros.cofre', 'available' => false],
                ['label' => 'Minha sessão', 'route' => 'membros.agenda', 'available' => false],
                ['label' => 'Pessoas', 'route' => 'membros.pessoas', 'available' => false],
                ['label' => 'Encontros', 'route' => 'membros.encontros', 'available' => false],
                ['label' => 'Frameworks', 'route' => 'membros.frameworks', 'available' => false],
            ],
```

- [ ] **Step 4: Run the tests**

Run: `php artisan test tests/Unit/Support/PersonaNavigationTest.php tests/Feature/Membros/PersonaNavigationTest.php`
Expected: PASS.

Run: `php artisan test tests/Feature/Livewire/Membros/DashboardTest.php`
Expected: FAIL — `test_quick_links_render_locked_with_no_href_when_route_does_not_exist_yet` asserts
that "Biblioteca de aulas" (the Início "Atalhos" card's link, which reads its `available` flag from
this exact `PersonaNavigation` entry) renders as a locked `<span>` with no `href` to
`/membros/aulas`. That's no longer true — it now renders as a real link. Fix the test: in
`tests/Feature/Livewire/Membros/DashboardTest.php`, replace:

```php
    public function test_quick_links_render_locked_with_no_href_when_route_does_not_exist_yet(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'club']));

        $html = Livewire::test(Dashboard::class)->html();

        foreach (['Biblioteca de aulas', 'Frameworks DO', 'Marcar minha sessão'] as $label) {
            $this->assertMatchesRegularExpression(
                '#<span[^>]*>\s*'.preg_quote($label, '#').'.*?🔒#s',
                $html,
            );
        }

        $this->assertStringNotContainsString('href="http://localhost/membros/aulas"', $html);
    }
```

with:

```php
    public function test_quick_links_render_locked_with_no_href_when_route_does_not_exist_yet(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'club']));

        $html = Livewire::test(Dashboard::class)->html();

        foreach (['Frameworks DO', 'Marcar minha sessão'] as $label) {
            $this->assertMatchesRegularExpression(
                '#<span[^>]*>\s*'.preg_quote($label, '#').'.*?🔒#s',
                $html,
            );
        }

        $this->assertMatchesRegularExpression(
            '#<a[^>]*href="http://localhost/membros/aulas"[^>]*>\s*Biblioteca de aulas#s',
            $html,
        );
    }
```

Run: `php artisan test tests/Feature/Livewire/Membros/DashboardTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Support/PersonaNavigation.php tests/Unit/Support/PersonaNavigationTest.php tests/Feature/Membros/PersonaNavigationTest.php tests/Feature/Livewire/Membros/DashboardTest.php
git commit -m "feat: unlock the Aulas nav tab now that the page exists"
```

---

## Task 6: Remove the course carousels from Início

**Files:**
- Modify: `app/Livewire/Membros/Dashboard.php`
- Modify: `resources/views/livewire/membros/dashboard.blade.php`
- Modify: `tests/Feature/Livewire/Membros/DashboardTest.php`

**Interfaces:**
- Consumes: nothing new — this only removes code. `newestLesson()`'s signature is unchanged, only
  its query gains a `where('tier', 'start')` clause.

- [ ] **Step 1: Update the tests first**

In `tests/Feature/Livewire/Membros/DashboardTest.php`:

Remove `test_watching_badge_appears_on_exactly_the_featured_lesson_card` entirely (lines 284-323 of
the current file) — its equivalent now lives in `AulasTest::test_watching_badge_appears_on_exactly_the_featured_lesson_card`
(Task 4), and its target markup (course carousel cards) no longer exists on this page.

In `test_dashboard_renders_featured_lesson_embed_and_materials`, remove the two assertions that only
ever passed because of the carousel rendering the same lesson a second time — change:

```php
        Livewire::test(Dashboard::class)
            ->assertSee('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertSee('Slides')
            ->assertSee('Apostila')
            ->assertSee(route('membros.materials.download', $uploaded), false)
            ->assertSee('Aula 05')
            ->assertSee('Módulo 4');
```

to:

```php
        Livewire::test(Dashboard::class)
            ->assertSee('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertSee('Slides')
            ->assertSee('Apostila')
            ->assertSee(route('membros.materials.download', $uploaded), false);
```

Add a test confirming `newestLesson()`'s new tier filter:

```php
    public function test_newest_lesson_card_never_recommends_a_club_only_lesson_to_a_start_tier_user(): void
    {
        $user = User::factory()->create(['tier' => 'start']);
        $course = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);
        Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula start mais antiga',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
            'category' => 'Encontros', 'tier' => 'start',
        ]);
        Lesson::create([
            'course_id' => $course->id, 'number' => 2, 'title' => 'Aula club mais nova',
            'video_provider' => 'youtube', 'video_id' => 'def', 'published_at' => '2026-06-01', 'position' => 2,
            'category' => 'Encontros', 'tier' => 'club',
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSee('Aula start mais antiga')
            ->assertDontSee('Aula club mais nova');
    }
```

- [ ] **Step 2: Run tests to verify the new/changed ones fail appropriately**

Run: `php artisan test tests/Feature/Livewire/Membros/DashboardTest.php`
Expected: `test_newest_lesson_card_never_recommends_a_club_only_lesson_to_a_start_tier_user` FAILS
(newestLesson() has no tier filter yet); the two edited tests should already pass as written (they're
narrowed, not new behavior) — if `test_watching_badge_...` is still present it will error since it
was deleted from the file, which is expected mid-step.

- [ ] **Step 3: Update `Dashboard.php`**

Remove the `courses()` computed method and the `Course` import; add a `where('tier', 'start')` to
`newestLesson()`; remove the `watchingLessonId` line from `render()` (nothing reads it anymore once
the carousel is gone). Replace:

```php
use App\Livewire\Concerns\ComputesUserInitials;
use App\Livewire\Concerns\TracksLessonProgress;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Support\PersonaNavigation;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.membros')]
class Dashboard extends Component
{
    use ComputesUserInitials, TracksLessonProgress;

    #[Computed]
    public function courses()
    {
        return Course::query()
            ->with('lessons')
            ->orderByDesc('position')
            ->get();
    }

    #[Computed]
    public function newestLesson(): ?Lesson
    {
        return Lesson::query()
            ->with('course')
            ->orderByDesc('published_at')
            ->orderByDesc('position')
            ->first();
    }
```

with:

```php
use App\Livewire\Concerns\ComputesUserInitials;
use App\Livewire\Concerns\TracksLessonProgress;
use App\Models\Lesson;
use App\Support\PersonaNavigation;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.membros')]
class Dashboard extends Component
{
    use ComputesUserInitials, TracksLessonProgress;

    #[Computed]
    public function newestLesson(): ?Lesson
    {
        return Lesson::query()
            ->with('course')
            ->where('tier', 'start')
            ->orderByDesc('published_at')
            ->orderByDesc('position')
            ->first();
    }
```

And replace the `render()` method:

```php
    public function render()
    {
        return view('livewire.membros.dashboard', [
            'watchingLessonId' => LessonProgress::query()
                ->where('user_id', Auth::id())
                ->where('lesson_id', $this->featuredLessonId)
                ->where('status', 'watching')
                ->exists() ? $this->featuredLessonId : null,
        ]);
    }
```

with:

```php
    public function render()
    {
        return view('livewire.membros.dashboard');
    }
```

Remove the now-unused `use App\Models\LessonProgress;` import (it was only for the `watchingLessonId`
query — `TracksLessonProgress` has its own `LessonProgress` import for `featuredProgress()`, this
file no longer needs its own).

- [ ] **Step 4: Remove the carousel markup**

In `resources/views/livewire/membros/dashboard.blade.php`, delete the entire block from the closing
`</section>` of the hero onward, i.e. everything between the hero `</section>` and the final
`</div><x-membros.footer />`:

```blade
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
                    @resize.window.debounce.100ms="update()"
                >
                    <div>
                        <h2 class="text-lg font-semibold font-display text-ink">
                            {{ $course->label }}@if($course->title): {{ $course->title }}@endif
                        </h2>
                        @if ($course->description)
                            <p class="mt-2 text-sm text-stone">{{ $course->description }}</p>
                        @endif
                    </div>

                    <div class="relative">
                        <div x-ref="track" @scroll.debounce.100ms="update()" class="mt-4 flex gap-4 overflow-x-auto scrollbar-none pb-2 scroll-smooth snap-x">
                            @foreach ($course->lessons as $courseLesson)
                                <div class="snap-start">
                                    @if ($course->lessons->count() === 1)
                                        <x-lesson-card-simple :lesson="$courseLesson" :course="$course" :watching="$watchingLessonId === $courseLesson->id" />
                                    @else
                                        <x-lesson-card :lesson="$courseLesson" :course="$course" :watching="$watchingLessonId === $courseLesson->id" />
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <button type="button" x-show="canScrollLeft" x-cloak
                                @click="$refs.track.scrollBy({ left: -300, behavior: 'smooth' })"
                                class="hidden md:flex absolute left-2 top-1/2 -translate-y-1/2 h-10 w-10 items-center justify-center rounded-full bg-brand text-white shadow-lg hover:brightness-110">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5 fill-current">
                                <path d="M14.71 6.71a1 1 0 0 1 0 1.42L10.41 12l4.3 4.29a1 1 0 0 1-1.42 1.42l-5-5a1 1 0 0 1 0-1.42l5-5a1 1 0 0 1 1.42 0Z"/>
                            </svg>
                        </button>

                        <button type="button" x-show="canScrollRight" x-cloak
                                @click="$refs.track.scrollBy({ left: 300, behavior: 'smooth' })"
                                class="hidden md:flex absolute right-2 top-1/2 -translate-y-1/2 h-10 w-10 items-center justify-center rounded-full bg-brand text-white shadow-lg hover:brightness-110">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5 fill-current">
                                <path d="M9.29 6.71a1 1 0 0 1 1.42 0l5 5a1 1 0 0 1 0 1.42l-5 5a1 1 0 0 1-1.42-1.42L13.59 12 9.29 7.71a1 1 0 0 1 0-1.42Z"/>
                            </svg>
                        </button>
                    </div>
                </section>
            @endif
        @endforeach
```

Delete it entirely — nothing replaces it (the outer `<div class="... space-y-16 sm:space-y-20">` now
wraps only the hero `<section>`, which is fine; `space-y-*` with one child is a no-op).

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`
Expected: PASS, all tests green (including the new tier-filter test from Step 1).

Run: `npm run build`
Expected: builds without error.

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/Membros/Dashboard.php resources/views/livewire/membros/dashboard.blade.php tests/Feature/Livewire/Membros/DashboardTest.php
git commit -m "refactor: remove course carousels from Início now that Aulas exists"
```

---

## Manual verification (after Task 6)

1. `php artisan migrate:fresh --seed`, then log in as a `tier=start` user. Confirm: Início shows only
   "Continuar assistindo" + the two side-stack cards (no carousels); the header nav shows "Aulas" as a
   real link; clicking it opens `/membros/aulas` showing only `tier=start` lessons; the category
   filters narrow the grid; clicking a card updates the hero player without a page reload.
2. Switch the same user's `tier` to `club` (`php artisan tinker`) — confirm the Aulas grid now also
   shows the `tier=club` lessons seeded in Task 2, each with "· Exclusivo CLUB" in its card.
3. Confirm the "N aulas na sua biblioteca" count does NOT change when switching category filters, only
   when switching tier.
