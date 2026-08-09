# Vimeo Watch Progress Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Resume the Vimeo hero player from where a member left off, and automatically mark a lesson `completed` at 90% watched — without polling the server while the video plays.

**Architecture:** The Vimeo Player SDK (`@vimeo/player`) wraps the existing hero `<iframe>` via a new Alpine component (`vimeoProgress`), reading local player events. It calls two new Livewire methods on `App\Livewire\Membros\Dashboard` (`updateProgress`, `markCompleted`) only on discrete events (`pause`, `ended`, crossing the completion threshold) — never on a timer. Both methods delegate to single-purpose Action classes under `App\Actions`, following the existing `MarkLessonAsWatching` pattern.

**Tech Stack:** Laravel 13, Livewire 3, Alpine.js (bundled by Livewire), `@vimeo/player` (npm), Vite, PHPUnit-style feature tests (`Tests\TestCase` + `Livewire::test()`).

## Global Constraints

- YouTube lessons are explicitly out of scope — the Alpine component must no-op unless `video_provider === 'vimeo'`.
- No periodic/polling saves. Progress is only persisted on `pause`, `ended`, and the one-time completion threshold crossing.
- Completion threshold is 90% of `player.getDuration()` (the live player value), not the `duration_seconds` DB column.
- On resume, never seek to a saved position that is within 5 seconds of the end.
- `updateProgress` must never revert `status` from `completed` back to `watching`.
- Both new Livewire methods resolve the acting user from `Auth::id()` server-side — the client never sends a user id.
- The project has no JS test runner; JS correctness is verified via `npm run build` succeeding and manual browser QA (see Task 5).
- Spec: `docs/superpowers/specs/2026-08-09-vimeo-watch-progress-design.md`. Issue: `docs/superpowers/issues/2026-08-09-vimeo-watch-progress-issue.md`.

---

### Task 1: `updateProgress` — persist watched position without downgrading `completed`

**Files:**
- Create: `app/Actions/UpdateLessonWatchedSeconds.php`
- Modify: `app/Livewire/Membros/Dashboard.php`
- Test: `tests/Feature/Livewire/Membros/DashboardTest.php`

**Interfaces:**
- Consumes: `App\Models\LessonProgress` (existing model, fields `user_id`, `lesson_id`, `status`, `watched_seconds`, `last_watched_at`).
- Produces: `UpdateLessonWatchedSeconds::handle(int $userId, int $lessonId, int $seconds): void`. `Dashboard::updateProgress(int $lessonId, int $seconds): void` — public Livewire method, callable from tests via `Livewire::test(Dashboard::class)->call('updateProgress', $lessonId, $seconds)` and from JS via `$wire.updateProgress(lessonId, seconds)` (wired in Task 3/4).

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Livewire/Membros/DashboardTest.php` (inside the `DashboardTest` class, after `test_watch_lesson_upserts_progress_and_updates_featured_lesson`):

```php
    public function test_update_progress_upserts_watched_seconds_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula 1',
            'video_provider' => 'vimeo', 'video_id' => '76979871', 'published_at' => '2026-01-01', 'position' => 1,
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->call('updateProgress', $lesson->id, 137);

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'watched_seconds' => 137,
            'status' => 'watching',
        ]);
    }

    public function test_update_progress_does_not_downgrade_completed_status(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula 1',
            'video_provider' => 'vimeo', 'video_id' => '76979871', 'published_at' => '2026-01-01', 'position' => 1,
        ]);

        LessonProgress::create([
            'user_id' => $user->id, 'lesson_id' => $lesson->id,
            'status' => 'completed', 'watched_seconds' => 590, 'last_watched_at' => now()->subMinute(),
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->call('updateProgress', $lesson->id, 600);

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'watched_seconds' => 600,
            'status' => 'completed',
        ]);
    }

    public function test_update_progress_is_scoped_to_the_authenticated_user(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $course = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula 1',
            'video_provider' => 'vimeo', 'video_id' => '76979871', 'published_at' => '2026-01-01', 'position' => 1,
        ]);

        LessonProgress::create([
            'user_id' => $owner->id, 'lesson_id' => $lesson->id,
            'status' => 'watching', 'watched_seconds' => 50, 'last_watched_at' => now(),
        ]);

        $this->actingAs($otherUser);

        Livewire::test(Dashboard::class)
            ->call('updateProgress', $lesson->id, 999);

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $owner->id, 'lesson_id' => $lesson->id, 'watched_seconds' => 50,
        ]);
        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $otherUser->id, 'lesson_id' => $lesson->id, 'watched_seconds' => 999,
        ]);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=test_update_progress`
Expected: FAIL — `Livewire\Exceptions\MethodNotFoundException` (or similar) because `updateProgress` doesn't exist on `Dashboard` yet.

- [ ] **Step 3: Create the action**

Create `app/Actions/UpdateLessonWatchedSeconds.php`:

```php
<?php

namespace App\Actions;

use App\Models\LessonProgress;

class UpdateLessonWatchedSeconds
{
    public function handle(int $userId, int $lessonId, int $seconds): void
    {
        $progress = LessonProgress::query()->firstOrNew([
            'user_id' => $userId,
            'lesson_id' => $lessonId,
        ]);

        $progress->watched_seconds = $seconds;
        $progress->last_watched_at = now();

        if ($progress->status !== 'completed') {
            $progress->status = 'watching';
        }

        $progress->save();
    }
}
```

- [ ] **Step 4: Wire the action into the Livewire component**

In `app/Livewire/Membros/Dashboard.php`, add the import alongside the existing `App\Actions` imports (line 5-6):

```php
use App\Actions\DetermineFeaturedLesson;
use App\Actions\MarkLessonAsWatching;
use App\Actions\UpdateLessonWatchedSeconds;
```

Then add a new method directly after `watchLesson()` (after line 34's closing `}`):

```php
    public function updateProgress(int $lessonId, int $seconds, UpdateLessonWatchedSeconds $action): void
    {
        $action->handle(Auth::id(), $lessonId, $seconds);
    }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=test_update_progress`
Expected: PASS (3 tests, 0 failures)

- [ ] **Step 6: Commit**

```bash
git add app/Actions/UpdateLessonWatchedSeconds.php app/Livewire/Membros/Dashboard.php tests/Feature/Livewire/Membros/DashboardTest.php
git commit -m "feat: persist Vimeo watch position via updateProgress"
```

---

### Task 2: `markCompleted` — mark a lesson as completed

**Files:**
- Create: `app/Actions/MarkLessonAsCompleted.php`
- Modify: `app/Livewire/Membros/Dashboard.php`
- Test: `tests/Feature/Livewire/Membros/DashboardTest.php`

**Interfaces:**
- Consumes: `App\Models\LessonProgress` (existing model).
- Produces: `MarkLessonAsCompleted::handle(int $userId, int $lessonId): void`. `Dashboard::markCompleted(int $lessonId): void` — public Livewire method, callable via `Livewire::test(Dashboard::class)->call('markCompleted', $lessonId)` and from JS via `$wire.markCompleted(lessonId)` (wired in Task 3/4).

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/Livewire/Membros/DashboardTest.php`, after the three tests added in Task 1:

```php
    public function test_mark_completed_sets_status_to_completed(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula 1',
            'video_provider' => 'vimeo', 'video_id' => '76979871', 'published_at' => '2026-01-01', 'position' => 1,
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->call('markCompleted', $lesson->id);

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'status' => 'completed',
        ]);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_mark_completed_sets_status_to_completed`
Expected: FAIL — `markCompleted` doesn't exist on `Dashboard` yet.

- [ ] **Step 3: Create the action**

Create `app/Actions/MarkLessonAsCompleted.php`:

```php
<?php

namespace App\Actions;

use App\Models\LessonProgress;

class MarkLessonAsCompleted
{
    public function handle(int $userId, int $lessonId): void
    {
        LessonProgress::query()->updateOrCreate(
            ['user_id' => $userId, 'lesson_id' => $lessonId],
            ['status' => 'completed', 'last_watched_at' => now()],
        );
    }
}
```

- [ ] **Step 4: Wire the action into the Livewire component**

In `app/Livewire/Membros/Dashboard.php`, add the import next to the one added in Task 1:

```php
use App\Actions\DetermineFeaturedLesson;
use App\Actions\MarkLessonAsCompleted;
use App\Actions\MarkLessonAsWatching;
use App\Actions\UpdateLessonWatchedSeconds;
```

Add a new method directly after `updateProgress()` (added in Task 1):

```php
    public function markCompleted(int $lessonId, MarkLessonAsCompleted $action): void
    {
        $action->handle(Auth::id(), $lessonId);
    }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=test_mark_completed_sets_status_to_completed`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Actions/MarkLessonAsCompleted.php app/Livewire/Membros/Dashboard.php tests/Feature/Livewire/Membros/DashboardTest.php
git commit -m "feat: mark lesson as completed via markCompleted"
```

---

### Task 3: Vimeo Player SDK integration (`vimeoProgress` Alpine component)

**Files:**
- Modify: `package.json`, `package-lock.json` (via `npm install`)
- Create: `resources/js/vimeo-progress.js`
- Modify: `resources/js/app.js`

**Interfaces:**
- Consumes: `Dashboard::updateProgress(int $lessonId, int $seconds)` and `Dashboard::markCompleted(int $lessonId)` from Tasks 1-2, called as `this.$wire.updateProgress(lessonId, seconds)` / `this.$wire.markCompleted(lessonId)`.
- Produces: default export `vimeoProgress({ lessonId, provider, initialSeconds })` from `resources/js/vimeo-progress.js`, registered as `Alpine.data('vimeoProgress', ...)`. Consumed by the blade markup added in Task 4 as `x-data="vimeoProgress({ lessonId: ..., provider: ..., initialSeconds: ... })"`, with the component reading its iframe via `this.$refs.iframe` (the blade must place `x-ref="iframe"` on the `<iframe>`).

There is no JS test runner in this project, so this task's "test" is a successful `npm run build` plus the manual QA in Task 5.

- [ ] **Step 1: Install the Vimeo Player SDK**

Run: `npm install @vimeo/player`
Expected: `package.json` gains a new `"dependencies"` entry `"@vimeo/player": "^2.30.4"` (or newer patch), `package-lock.json` updates, exit code 0.

- [ ] **Step 2: Create the Alpine component module**

Create `resources/js/vimeo-progress.js`:

```js
import Player from '@vimeo/player';

const COMPLETED_THRESHOLD = 0.9;
const RESUME_SAFETY_MARGIN_SECONDS = 5;

export default function vimeoProgress({ lessonId, provider, initialSeconds }) {
    return {
        player: null,
        completedSent: false,

        init() {
            if (provider !== 'vimeo') {
                return;
            }

            this.player = new Player(this.$refs.iframe);

            this.player.on('loaded', () => this.resumeIfNeeded());
            this.player.on('timeupdate', (data) => this.checkCompleted(data));
            this.player.on('pause', () => this.saveProgress());
            this.player.on('ended', () => this.saveProgress());
        },

        async resumeIfNeeded() {
            if (initialSeconds <= 0) {
                return;
            }

            const duration = await this.player.getDuration();

            if (initialSeconds < duration - RESUME_SAFETY_MARGIN_SECONDS) {
                this.player.setCurrentTime(initialSeconds);
            }
        },

        checkCompleted({ percent }) {
            if (this.completedSent || percent < COMPLETED_THRESHOLD) {
                return;
            }

            this.completedSent = true;
            this.$wire.markCompleted(lessonId);
        },

        async saveProgress() {
            const seconds = await this.player.getCurrentTime();
            this.$wire.updateProgress(lessonId, Math.floor(seconds));
        },
    };
}
```

- [ ] **Step 3: Register the component with Alpine**

Replace the contents of `resources/js/app.js` (currently just `//`) with:

```js
import vimeoProgress from './vimeo-progress';

document.addEventListener('alpine:init', () => {
    Alpine.data('vimeoProgress', vimeoProgress);
});
```

- [ ] **Step 4: Verify the build succeeds**

Run: `npm run build`
Expected: exit code 0, output includes a compiled chunk referencing `app.js` with no errors about `@vimeo/player` or `vimeo-progress.js`.

- [ ] **Step 5: Commit**

```bash
git add package.json package-lock.json resources/js/vimeo-progress.js resources/js/app.js
git commit -m "feat: add Vimeo Player SDK integration for watch progress"
```

---

### Task 4: Wire the hero player to `vimeoProgress` and pass the saved position

**Files:**
- Modify: `app/Livewire/Membros/Dashboard.php`
- Modify: `resources/views/livewire/membros/dashboard.blade.php`
- Test: `tests/Feature/Livewire/Membros/DashboardTest.php`

**Interfaces:**
- Consumes: `vimeoProgress` Alpine component (Task 3), `updateProgress`/`markCompleted` Livewire methods (Tasks 1-2), `App\Models\LessonProgress`.
- Produces: `Dashboard::featuredProgress(): ?LessonProgress` computed property (Livewire `#[Computed]`), used by the blade to read the saved `watched_seconds` for the currently featured lesson and the authenticated user.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Livewire/Membros/DashboardTest.php`, after `test_mark_completed_sets_status_to_completed`:

```php
    public function test_hero_player_wires_up_the_vimeo_progress_component_for_vimeo_lessons(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula 1',
            'video_provider' => 'vimeo', 'video_id' => '76979871', 'published_at' => '2026-01-01', 'position' => 1,
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSee("wire:key=\"hero-player-{$lesson->id}\"", false)
            ->assertSee('x-data="vimeoProgress(', false)
            ->assertSee("provider: 'vimeo'", false)
            ->assertSee('initialSeconds: 0', false);
    }

    public function test_hero_player_passes_the_saved_watched_seconds_into_the_alpine_component(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula 1',
            'video_provider' => 'vimeo', 'video_id' => '76979871', 'published_at' => '2026-01-01', 'position' => 1,
        ]);

        LessonProgress::create([
            'user_id' => $user->id, 'lesson_id' => $lesson->id,
            'status' => 'watching', 'watched_seconds' => 245, 'last_watched_at' => now(),
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSee('initialSeconds: 245', false);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=test_hero_player`
Expected: FAIL — the blade doesn't render `wire:key`/`x-data`/`initialSeconds` yet.

- [ ] **Step 3: Add the `featuredProgress` computed property**

In `app/Livewire/Membros/Dashboard.php`, add this method directly after `featuredLesson()`:

```php
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
```

- [ ] **Step 4: Wire the hero player markup**

In `resources/views/livewire/membros/dashboard.blade.php`, replace lines 13-23:

```blade
                <div class="mt-6 rounded-2xl border border-slate-800/60 bg-surface p-3 sm:p-4">
                    <div class="relative aspect-video overflow-hidden rounded-xl">
                        <iframe
                            src="{{ $lesson->embed_url }}"
                            class="h-full w-full"
                            allow="autoplay; fullscreen; picture-in-picture"
                            allowfullscreen
                        ></iframe>
                        <x-brand-logo icon-only class="pointer-events-none absolute top-3 right-3 h-6 w-auto drop-shadow" />
                    </div>
                </div>
```

with:

```blade
                <div
                    wire:key="hero-player-{{ $lesson->id }}"
                    x-data="vimeoProgress({
                        lessonId: {{ $lesson->id }},
                        provider: '{{ $lesson->video_provider }}',
                        initialSeconds: {{ $this->featuredProgress?->watched_seconds ?? 0 }},
                    })"
                    class="mt-6 rounded-2xl border border-slate-800/60 bg-surface p-3 sm:p-4"
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
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=Dashboard`
Expected: PASS — all `DashboardTest` tests (existing + new), 0 failures.

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/Membros/Dashboard.php resources/views/livewire/membros/dashboard.blade.php tests/Feature/Livewire/Membros/DashboardTest.php
git commit -m "feat: resume Vimeo hero player from saved watch position"
```

---

### Task 5: Manual QA and full regression run

**Files:** none (verification only).

**Interfaces:** Consumes the complete feature from Tasks 1-4.

- [ ] **Step 1: Run the full backend test suite**

Run: `php artisan test`
Expected: PASS, 0 failures.

- [ ] **Step 2: Build frontend assets**

Run: `npm run build`
Expected: exit code 0.

- [ ] **Step 3: Point a seeded lesson at a real public Vimeo video for manual testing**

In `database/seeders/LmsSeeder.php` (or via `php artisan tinker`), temporarily set one lesson's `video_provider` to `vimeo` and `video_id` to `76979871` (Vimeo's public "Vimeo Staff Picks" demo video, safe to embed for local testing). Do not commit this change if it touches the seeder — revert it after manual QA, since fixing the seeder's `video_provider = 'panda'` values is tracked separately (see `docs/lms-spec.md`, section 8).

- [ ] **Step 4: Manual browser walkthrough**

Run: `npm run dev` and `php artisan serve` (or your usual local URL), then in a browser:
1. Log in and open `/membros`.
2. Open the hero player for the Vimeo test lesson, play a few seconds, then pause.
3. Confirm in `lesson_progress` (via `php artisan tinker` or DB client) that `watched_seconds` and `last_watched_at` updated for your user.
4. Reload `/membros`. Confirm the player resumes at approximately the paused position (not from 0).
5. Seek near the end of the video (past 90%) and let it play. Confirm `lesson_progress.status` becomes `completed`.
6. Open the browser network tab while the video plays uninterrupted for at least 30 seconds without pausing. Confirm no Livewire request fires during that window (no polling).
7. Switch to a different lesson in the carousel while the first is still playing. Confirm the player swaps cleanly (no JS console errors) and the new lesson's hero renders correctly.

- [ ] **Step 5: Revert the temporary seeder/tinker change from Step 3**

If `LmsSeeder.php` was edited for testing, revert it: `git checkout -- database/seeders/LmsSeeder.php` (only if no other pending changes exist in that file — check `git status` first).
