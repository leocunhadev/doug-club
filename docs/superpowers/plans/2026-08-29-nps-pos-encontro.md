# NPS Unificado (Modal Compartilhado) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the lesson NPS's inline banner with a single shared `<x-nps-modal>` component (closes GitHub issue #25), and add NPS pós-encontro on top of that same modal via a manual "Avaliar" button on past encontros (closes GitHub issue #19).

**Architecture:** A new standalone `EncontroFeedback` model + `SubmitEncontroNpsScore` action mirror the existing `LessonFeedback`/`SubmitLessonNpsScore` pair exactly — no shared/polymorphic table. The UI, in contrast, IS shared: one `<x-nps-modal>` Blade component, driven entirely by Alpine state populated from a `window` `CustomEvent` (`open-nps-modal`) carrying which Livewire method to call, which ID, and what subtitle to show. Because Alpine's `$wire` magic only resolves inside the DOM subtree a specific Livewire component actually renders, the modal is included once inside each of the three pages that need it (`dashboard.blade.php`, `aulas.blade.php`, `encontros.blade.php`) rather than once in the shared layout.

**Tech Stack:** Laravel 13, Livewire 3, Alpine.js, Tailwind CSS v3, PHPUnit (`php artisan test`), SQLite (`:memory:` in tests).

**Spec:** `docs/superpowers/specs/2026-08-29-nps-pos-encontro-design.md`

## Global Constraints

- No comment field on the modal — score only (0–10), matching the already-shipped lesson NPS decision.
- No mentor-alert logic based on score (that's issue #21, Painel do mentor — out of scope).
- `EncontroFeedback` is its own table/model, NOT a polymorphic generalization of `LessonFeedback` — the existing `lesson_feedback` table and its model/action are untouched by this plan except for how their UI is triggered.
- The "Avaliar" button only ever appears on **past** encontros, and disappears once the current user has already rated that specific encontro — never on future encontros, never a "you already rated this ✓" replacement label.
- `<x-nps-modal>` must be included inside each consuming page's own Livewire-rendered template — never in `resources/views/layouts/membros.blade.php` outside `{{ $slot }}`, or `$wire[action](...)` silently fails to resolve.

---

## Task 1: `EncontroFeedback` model + `SubmitEncontroNpsScore` action

**Files:**
- Create: `database/migrations/2026_08_29_180000_create_encontro_feedback_table.php`
- Create: `app/Models/EncontroFeedback.php`
- Create: `app/Actions/SubmitEncontroNpsScore.php`
- Test: `tests/Unit/SubmitEncontroNpsScoreTest.php`

**Interfaces:**
- Produces: `EncontroFeedback` model (`$fillable`: `user_id`, `encontro_id`, `score`; `user()`, `encontro()` relations) and `App\Actions\SubmitEncontroNpsScore::handle(int $userId, int $encontroId, int $score): void` (clamps to 0–10, `updateOrCreate` keyed on `user_id`+`encontro_id`). Task 3 calls this action by these exact names.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/SubmitEncontroNpsScoreTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Actions\SubmitEncontroNpsScore;
use App\Models\Encontro;
use App\Models\EncontroFeedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmitEncontroNpsScoreTest extends TestCase
{
    use RefreshDatabase;

    private function encontro(): Encontro
    {
        return Encontro::create([
            'tema' => 'O comercial é gente', 'quem' => 'Com Douglas',
            'scheduled_at' => now()->subDay(),
        ]);
    }

    public function test_creates_feedback_with_the_given_score(): void
    {
        $user = User::factory()->create();
        $encontro = $this->encontro();

        (new SubmitEncontroNpsScore)->handle($user->id, $encontro->id, 9);

        $this->assertDatabaseHas('encontro_feedback', [
            'user_id' => $user->id,
            'encontro_id' => $encontro->id,
            'score' => 9,
        ]);
    }

    public function test_resubmitting_updates_the_existing_score_instead_of_duplicating(): void
    {
        $user = User::factory()->create();
        $encontro = $this->encontro();

        (new SubmitEncontroNpsScore)->handle($user->id, $encontro->id, 5);
        (new SubmitEncontroNpsScore)->handle($user->id, $encontro->id, 8);

        $this->assertSame(1, EncontroFeedback::query()->where('user_id', $user->id)->where('encontro_id', $encontro->id)->count());
        $this->assertDatabaseHas('encontro_feedback', ['user_id' => $user->id, 'encontro_id' => $encontro->id, 'score' => 8]);
    }

    public function test_score_above_10_is_clamped_to_10(): void
    {
        $user = User::factory()->create();
        $encontro = $this->encontro();

        (new SubmitEncontroNpsScore)->handle($user->id, $encontro->id, 99);

        $this->assertDatabaseHas('encontro_feedback', ['user_id' => $user->id, 'encontro_id' => $encontro->id, 'score' => 10]);
    }

    public function test_score_below_0_is_clamped_to_0(): void
    {
        $user = User::factory()->create();
        $encontro = $this->encontro();

        (new SubmitEncontroNpsScore)->handle($user->id, $encontro->id, -3);

        $this->assertDatabaseHas('encontro_feedback', ['user_id' => $user->id, 'encontro_id' => $encontro->id, 'score' => 0]);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Unit/SubmitEncontroNpsScoreTest.php`
Expected: FAIL — `EncontroFeedback` class, `SubmitEncontroNpsScore` class, and `encontro_feedback` table don't exist yet.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_08_29_180000_create_encontro_feedback_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encontro_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('encontro_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->timestamps();

            $table->unique(['user_id', 'encontro_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encontro_feedback');
    }
};
```

- [ ] **Step 4: Create the model**

Create `app/Models/EncontroFeedback.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EncontroFeedback extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'encontro_id',
        'score',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function encontro(): BelongsTo
    {
        return $this->belongsTo(Encontro::class);
    }
}
```

- [ ] **Step 5: Create the action**

Create `app/Actions/SubmitEncontroNpsScore.php`:

```php
<?php

namespace App\Actions;

use App\Models\EncontroFeedback;

class SubmitEncontroNpsScore
{
    public function handle(int $userId, int $encontroId, int $score): void
    {
        EncontroFeedback::query()->updateOrCreate(
            ['user_id' => $userId, 'encontro_id' => $encontroId],
            ['score' => max(0, min(10, $score))],
        );
    }
}
```

- [ ] **Step 6: Run migration and tests**

Run: `php artisan migrate`
Run: `php artisan test tests/Unit/SubmitEncontroNpsScoreTest.php`
Expected: PASS (all 4 tests).

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_29_180000_create_encontro_feedback_table.php app/Models/EncontroFeedback.php app/Actions/SubmitEncontroNpsScore.php tests/Unit/SubmitEncontroNpsScoreTest.php
git commit -m "feat: add EncontroFeedback model and SubmitEncontroNpsScore action"
```

---

## Task 2: Shared `<x-nps-modal>` + retrofit NPS pós-aula (closes #25)

**Files:**
- Create: `resources/views/components/nps-modal.blade.php`
- Modify: `resources/js/vimeo-progress.js`
- Modify: `resources/views/components/lesson-player.blade.php`
- Modify: `resources/views/livewire/membros/dashboard.blade.php`
- Modify: `resources/views/livewire/membros/aulas.blade.php`
- Test: `tests/Feature/Livewire/Membros/DashboardTest.php` (one new test)

**Interfaces:**
- Consumes: existing `hasFeedback` prop on `<x-lesson-player>` (unchanged), existing `App\Livewire\Concerns\TracksLessonProgress::submitNpsScore(int $lessonId, int $score, ...)` (unchanged — still the method the modal ends up calling).
- Produces: `<x-nps-modal>` component, with no props, listening for a `window` `open-nps-modal` `CustomEvent` whose `detail` carries `{ action: string, subjectId: number, subtitle: string }`. Task 3 dispatches this same event shape for the encontro side.

- [ ] **Step 1: Confirm current state before editing**

Run: `php artisan test tests/Feature/Livewire/Membros/DashboardTest.php --filter=hasFeedback` (there is no literal test named this — instead run the full file):

Run: `php artisan test tests/Feature/Livewire/Membros/DashboardTest.php`
Expected: PASS (baseline, before this task's changes — confirms your starting point is clean).

- [ ] **Step 2: Write the one new failing test**

In `tests/Feature/Livewire/Membros/DashboardTest.php`, add this test right after
`test_hero_player_passes_has_feedback_true_when_the_user_already_rated_the_lesson` (same file
already imports `Course`, `Lesson`, `LessonFeedback`, `User`, `Livewire`, `Dashboard` — no new
imports needed):

```php
    public function test_the_shared_nps_modal_is_present_on_the_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSee('open-nps-modal', false);
    }
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `php artisan test tests/Feature/Livewire/Membros/DashboardTest.php --filter=test_the_shared_nps_modal_is_present_on_the_page`
Expected: FAIL — `<x-nps-modal>` doesn't exist yet, so `dashboard.blade.php` never includes it.

- [ ] **Step 4: Create `<x-nps-modal>`**

Create `resources/views/components/nps-modal.blade.php`:

```blade
<div
    x-data="{ open: false, action: null, subjectId: null, subtitle: '', score: null }"
    x-on:open-nps-modal.window="
        open = true;
        action = $event.detail.action;
        subjectId = $event.detail.subjectId;
        subtitle = $event.detail.subtitle;
        score = null;
    "
    x-show="open" x-cloak
    class="fixed inset-0 z-[150] bg-black/55 flex items-end sm:items-center justify-center p-[18px]"
>
    <div @click.outside="open = false" class="bg-card rounded-t-[22px] sm:rounded-[22px] p-[26px] max-w-[470px] w-full shadow-[0_24px_60px_rgba(0,0,0,.35)]">
        <h3 class="font-display text-lg">Como foi para você?</h3>
        <p class="mt-1 mb-4 text-sm text-stone" x-text="subtitle"></p>

        <div class="flex flex-wrap gap-1.5 mb-4">
            <template x-for="i in 11" :key="i">
                <button
                    type="button"
                    @click="score = i - 1"
                    :class="score === i - 1 ? 'bg-brand border-brand text-white' : 'bg-card border-sand text-ink'"
                    class="w-9 h-[38px] rounded-[10px] border font-bold text-sm"
                ><span x-text="i - 1"></span></button>
            </template>
        </div>

        <div class="flex items-center gap-2.5">
            <button type="button" @click="open = false" class="text-sm text-stone hover:text-ink">Agora não</button>
            <button
                type="button"
                @click="if (score !== null) { $wire[action](subjectId, score); open = false }"
                :disabled="score === null"
                class="ms-auto px-4 py-2 rounded-full bg-black text-white text-sm font-semibold disabled:opacity-40 disabled:cursor-not-allowed"
            >Enviar</button>
        </div>
    </div>
</div>
```

- [ ] **Step 5: Remove the inline NPS banner from `<x-lesson-player>`**

In `resources/views/components/lesson-player.blade.php`, the current file (read it first to confirm
this exact block is still there) has this block right after the closing `</div>` of the
`aspect-video` wrapper, before the materials `<div class="mt-4">`:

```blade
        <div x-show="showNps" x-cloak x-transition class="mt-3 rounded-xl border border-sand bg-paper p-4">
            <p class="text-sm font-medium text-ink">De 0 a 10, o quanto você recomendaria esta aula?</p>
            <div class="mt-2 flex flex-wrap items-center gap-1.5">
                @for ($i = 0; $i <= 10; $i++)
                    <button type="button" @click="submitNps({{ $i }})"
                            class="h-8 w-8 rounded-full border border-sand text-sm font-medium text-ink hover:border-black hover:bg-card">
                        {{ $i }}
                    </button>
                @endfor
                <button type="button" @click="showNps = false" class="ms-2 text-xs text-stone hover:text-ink">
                    Agora não
                </button>
            </div>
        </div>
```

Delete this entire block. Nothing else in `lesson-player.blade.php` changes — the `hasFeedback` prop
and its use in the `x-data="vimeoProgress({...})"` config stay exactly as they are.

- [ ] **Step 6: Retrofit `vimeo-progress.js` to dispatch the modal instead of the banner**

In `resources/js/vimeo-progress.js`, replace:

```js
        showNps: false,
```

(delete this line — the component no longer tracks its own modal-open state; that lives in
`<x-nps-modal>` now) and replace:

```js
        checkCompleted({ percent }) {
            if (this.completedSent || percent < COMPLETED_THRESHOLD) {
                return;
            }

            this.completedSent = true;
            this.$wire.markCompleted(lessonId);

            if (!hasFeedback) {
                this.showNps = true;
            }
        },
```

with:

```js
        checkCompleted({ percent }) {
            if (this.completedSent || percent < COMPLETED_THRESHOLD) {
                return;
            }

            this.completedSent = true;
            this.$wire.markCompleted(lessonId);

            if (!hasFeedback) {
                window.dispatchEvent(new CustomEvent('open-nps-modal', {
                    detail: {
                        action: 'submitNpsScore',
                        subjectId: lessonId,
                        subtitle: 'De 0 a 10, o quanto essa aula te ajudou a decidir melhor?',
                    },
                }));
            }
        },
```

And delete the `submitNps(score) { ... }` method entirely (the last method in the returned object,
right before the closing `};`) — the modal calls `$wire.submitNpsScore` itself now, this component
doesn't need to relay it.

The final `vimeo-progress.js` should have exactly these top-level properties/methods left:
`player`, `completedSent`, `init()`, `startPlayback()`, `resumeIfNeeded()`, `checkCompleted()`,
`saveProgress()`. No `showNps`, no `submitNps`.

- [ ] **Step 7: Include the modal in the two pages that need it**

In `resources/views/livewire/membros/dashboard.blade.php`, find the line:

```blade
            <x-lesson-player :lesson="$this->featuredLesson" :progress="$this->featuredProgress" :has-feedback="$this->featuredHasFeedback" />
```

and add right after it:

```blade
            <x-nps-modal />
```

In `resources/views/livewire/membros/aulas.blade.php`, find the line:

```blade
        <x-lesson-player :lesson="$this->featuredLesson" :progress="$this->featuredProgress" :has-feedback="$this->featuredHasFeedback" />
```

and add right after it:

```blade
        <x-nps-modal />
```

- [ ] **Step 8: Run the tests**

Run: `php artisan test tests/Feature/Livewire/Membros/DashboardTest.php`
Expected: PASS (including the new test).

Run: `npm run build`
Expected: builds without error (confirms the edited `vimeo-progress.js` has no syntax errors).

Run: `php artisan test`
Expected: PASS — full suite green. In particular, no existing test asserted the now-deleted banner's
markup ("De 0 a 10, o quanto você recomendaria esta aula?", `showNps`, or `submitNps(`) — confirm
this by searching: `grep -rn "recomendaria esta aula\|showNps\|submitNps(" tests/` should return
nothing. If it does return a match, that test needs updating to assert `hasFeedback` and
`open-nps-modal` presence instead, following the same shape as the new test in Step 2.

- [ ] **Step 9: Commit**

```bash
git add resources/views/components/nps-modal.blade.php resources/js/vimeo-progress.js resources/views/components/lesson-player.blade.php resources/views/livewire/membros/dashboard.blade.php resources/views/livewire/membros/aulas.blade.php tests/Feature/Livewire/Membros/DashboardTest.php
git commit -m "feat: replace lesson NPS banner with the shared nps-modal component"
```

---

## Task 3: NPS pós-encontro via "Avaliar" (closes #19)

**Files:**
- Modify: `app/Livewire/Membros/Encontros.php`
- Modify: `resources/views/components/encontro-card.blade.php`
- Modify: `resources/views/livewire/membros/encontros.blade.php`
- Test: `tests/Feature/Livewire/Membros/EncontrosTest.php`

**Interfaces:**
- Consumes: `App\Actions\SubmitEncontroNpsScore` (Task 1), `<x-nps-modal>` (Task 2 — same `open-nps-modal` event shape: `{ action, subjectId, subtitle }`).
- Produces: `Encontros::ratedEncontroIds(): array` (computed, list of `encontro_id` ints the current user has already scored) and `Encontros::submitEncontroNpsScore(int $encontroId, int $score): void` (Livewire action method) — the modal calls this by name via `action: 'submitEncontroNpsScore'`.

- [ ] **Step 1: Write the failing tests**

In `tests/Feature/Livewire/Membros/EncontrosTest.php`, add these imports if not already present
(the file already imports `Encontro`, `Course`, `Lesson`, `User`, `Livewire`, `Encontros`):

```php
use App\Models\EncontroFeedback;
```

Add these tests at the end of the class, before the closing `}`:

```php
    public function test_avaliar_button_appears_only_on_past_encontros(): void
    {
        $this->encontro(['tema' => 'Futuro', 'scheduled_at' => now()->addDay()]);
        $this->encontro(['tema' => 'Passado', 'scheduled_at' => now()->subDay()]);

        $this->actingAs(User::factory()->create(['tier' => 'club']));

        $html = Livewire::test(Encontros::class)->html();

        $this->assertSame(1, substr_count($html, 'Avaliar'));
    }

    public function test_avaliar_button_disappears_after_the_user_already_rated_that_encontro(): void
    {
        $ratedEncontro = $this->encontro(['tema' => 'Já avaliado', 'scheduled_at' => now()->subDay()]);
        $this->encontro(['tema' => 'Ainda não avaliado', 'scheduled_at' => now()->subDays(2)]);

        $user = User::factory()->create(['tier' => 'club']);
        EncontroFeedback::create(['user_id' => $user->id, 'encontro_id' => $ratedEncontro->id, 'score' => 8]);

        $this->actingAs($user);

        $html = Livewire::test(Encontros::class)->html();

        $this->assertSame(1, substr_count($html, 'Avaliar'));
    }

    public function test_submit_encontro_nps_score_persists_the_score(): void
    {
        $encontro = $this->encontro(['scheduled_at' => now()->subDay()]);

        $this->actingAs(User::factory()->create(['tier' => 'club']));

        Livewire::test(Encontros::class)
            ->call('submitEncontroNpsScore', $encontro->id, 9);

        $this->assertDatabaseHas('encontro_feedback', [
            'encontro_id' => $encontro->id,
            'score' => 9,
        ]);
    }

    public function test_submit_encontro_nps_score_is_a_no_op_for_a_nonexistent_encontro(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'club']));

        Livewire::test(Encontros::class)
            ->call('submitEncontroNpsScore', 999999, 9);

        $this->assertDatabaseMissing('encontro_feedback', ['encontro_id' => 999999]);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Livewire/Membros/EncontrosTest.php`
Expected: FAIL — `submitEncontroNpsScore` method doesn't exist, "Avaliar" doesn't render anywhere.

- [ ] **Step 3: Add the computed + action method to `Encontros`**

In `app/Livewire/Membros/Encontros.php`, add the imports:

```php
use App\Actions\SubmitEncontroNpsScore;
use App\Models\EncontroFeedback;
use Illuminate\Support\Facades\Auth;
```

Then add these two methods inside the class, after `encontros()`:

```php
    #[Computed]
    public function ratedEncontroIds(): array
    {
        return EncontroFeedback::query()
            ->where('user_id', Auth::id())
            ->pluck('encontro_id')
            ->all();
    }

    public function submitEncontroNpsScore(int $encontroId, int $score, SubmitEncontroNpsScore $action): void
    {
        if (! Encontro::query()->whereKey($encontroId)->exists()) {
            return;
        }

        $action->handle(Auth::id(), $encontroId, $score);
    }
```

The full class should now look like:

```php
<?php

namespace App\Livewire\Membros;

use App\Actions\SubmitEncontroNpsScore;
use App\Livewire\Concerns\ComputesUserInitials;
use App\Models\Encontro;
use App\Models\EncontroFeedback;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.membros')]
class Encontros extends Component
{
    use ComputesUserInitials;

    #[Computed]
    public function encontros()
    {
        $upcoming = Encontro::query()->with('lesson')
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at')
            ->get();

        $past = Encontro::query()->with('lesson')
            ->where('scheduled_at', '<', now())
            ->orderByDesc('scheduled_at')
            ->get();

        return $upcoming->concat($past);
    }

    #[Computed]
    public function ratedEncontroIds(): array
    {
        return EncontroFeedback::query()
            ->where('user_id', Auth::id())
            ->pluck('encontro_id')
            ->all();
    }

    public function submitEncontroNpsScore(int $encontroId, int $score, SubmitEncontroNpsScore $action): void
    {
        if (! Encontro::query()->whereKey($encontroId)->exists()) {
            return;
        }

        $action->handle(Auth::id(), $encontroId, $score);
    }

    public function render()
    {
        return view('livewire.membros.encontros');
    }
}
```

- [ ] **Step 4: Add the "Avaliar" button to `<x-encontro-card>`**

In `resources/views/components/encontro-card.blade.php`, change the `@props` line from:

```blade
@props(['encontro', 'isNext' => false])
```

to:

```blade
@props(['encontro', 'isNext' => false, 'ratedEncontroIds' => []])
```

Then, inside the `@if ($encontro->isPast())` branch, right after the `@else` / `@endif` for the
"Ver na biblioteca"/"Gravação em breve" pair (i.e., immediately before that branch's closing
`@endif` for `isPast()`), add the "Avaliar" button so it renders alongside whichever of those two
was shown. The full `isPast()` branch becomes:

```blade
            @if ($encontro->isPast())
                @if ($encontro->recording_lesson_id && $encontro->lesson)
                    <a href="{{ route('membros.aulas', ['lesson' => $encontro->recording_lesson_id]) }}" wire:navigate
                       class="inline-flex items-center px-[11px] py-[5px] rounded-full text-[11px] font-bold uppercase tracking-[.1em] bg-paper text-stone border border-sand hover:border-black hover:text-ink">
                        Ver na biblioteca
                    </a>
                @else
                    <span class="inline-flex items-center px-[11px] py-[5px] rounded-full text-[11px] font-bold uppercase tracking-[.1em] bg-paper text-stone border border-sand cursor-not-allowed">
                        Gravação em breve
                    </span>
                @endif

                @if (! in_array($encontro->id, $ratedEncontroIds))
                    <button
                        type="button"
                        @click="window.dispatchEvent(new CustomEvent('open-nps-modal', { detail: {
                            action: 'submitEncontroNpsScore',
                            subjectId: {{ $encontro->id }},
                            subtitle: 'De 0 a 10, o quanto esse encontro te ajudou a decidir melhor?',
                        } }))"
                        class="inline-flex items-center px-[11px] py-[5px] rounded-full text-[11px] font-bold uppercase tracking-[.1em] bg-paper text-stone border border-sand hover:border-black hover:text-ink"
                    >Avaliar</button>
                @endif
            @else
```

(Everything from `@else` onward — the future-encontro branch with "Próximo"/"Entrar"/"Link em
breve" — stays exactly as it already is; only the `isPast()` branch gains the new button.)

- [ ] **Step 5: Wire the prop through and include the modal in the view**

In `resources/views/livewire/membros/encontros.blade.php`, change:

```blade
                @foreach ($this->encontros as $encontro)
                    <x-encontro-card :encontro="$encontro" :is-next="$next !== null && $encontro->is($next)" />
                @endforeach
```

to:

```blade
                @foreach ($this->encontros as $encontro)
                    <x-encontro-card :encontro="$encontro" :is-next="$next !== null && $encontro->is($next)" :rated-encontro-ids="$this->ratedEncontroIds" />
                @endforeach
```

Then, right after the closing `</div>` of the `enc-timeline`/`@endif` block (i.e., right before
`<x-membros.footer />`), add:

```blade
        <x-nps-modal />
```

The full body of the page (inside the `max-w-7xl` container, after the header block) should read:

```blade
        @if ($this->encontros->isEmpty())
            <p class="text-stone">Nenhum encontro agendado ainda.</p>
        @else
            <div class="enc-timeline max-w-3xl">
                @foreach ($this->encontros as $encontro)
                    <x-encontro-card :encontro="$encontro" :is-next="$next !== null && $encontro->is($next)" :rated-encontro-ids="$this->ratedEncontroIds" />
                @endforeach
            </div>
        @endif

        <x-nps-modal />
```

- [ ] **Step 6: Run the tests**

Run: `php artisan test tests/Feature/Livewire/Membros/EncontrosTest.php`
Expected: PASS (all tests, including the 4 new ones).

Run: `npm run build`
Expected: builds without error.

Run: `php artisan test`
Expected: PASS — full suite green.

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/Membros/Encontros.php resources/views/components/encontro-card.blade.php resources/views/livewire/membros/encontros.blade.php tests/Feature/Livewire/Membros/EncontrosTest.php
git commit -m "feat: add NPS pós-encontro via the shared nps-modal"
```

---

## Manual verification (after Task 3)

1. Log in as a CLUB member with at least one past encontro that hasn't been rated yet. On
   `/membros/encontros`, confirm the "Avaliar" pill appears on that card (alongside "Ver na
   biblioteca" or "Gravação em breve"), clicking it opens the bottom-sheet modal with the correct
   subtitle ("De 0 a 10, o quanto esse encontro te ajudou a decidir melhor?"), picking a score and
   clicking "Enviar" closes the modal, and reloading the page shows the "Avaliar" pill is now gone
   for that specific card only (other unrated past encontros still show it).
2. On `/membros/aulas` or `/membros` (Início), watch a Vimeo lesson to ~90% — confirm the same modal
   opens automatically (no banner appears anymore), with the subtitle "De 0 a 10, o quanto essa aula
   te ajudou a decidir melhor?", and submitting or dismissing it behaves the same way.
3. Confirm "Agora não" on either trigger just closes the modal without submitting anything, and
   clicking outside the modal card also closes it.
