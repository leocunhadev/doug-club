# Encontros ao vivo Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the real `/membros/encontros` page (closes GitHub issue #18): a CLUB-exclusive timeline of live events, each with a real join link and an optional link back to its recording in the Biblioteca de aulas.

**Architecture:** `Encontro` is a small standalone model with a custom-named FK (`recording_lesson_id` → `lessons`) and no stored status — past/future is always computed from `scheduled_at` vs `now()`. The page is a single Livewire component whose one computed property concatenates two simple queries (upcoming ascending, then past descending). The whole route sits behind the existing `tier:club` middleware, which is why the card needs NO per-lesson `isAvailableFor()` gate (every viewer already has `hasClubAccess()`, making that check a tautology — document this, don't "fix" it). Admin CRUD mirrors `FrameworkResource` file-for-file.

**Tech Stack:** Laravel 13, Livewire 3, Filament 4, Tailwind CSS v3, PHPUnit (`php artisan test`), SQLite (`:memory:` in tests).

**Spec:** `docs/superpowers/specs/2026-08-29-encontros-ao-vivo-design.md`

## Global Constraints

- The route `membros/encontros` carries `['auth', 'verified', 'active', 'tier:club']` — the WHOLE page is CLUB-exclusive (club + mentor via `hasClubAccess()`); a `start` member is redirected to the dashboard by the existing `EnsureTier` middleware. No per-content tier checks anywhere else in this feature.
- No stored status column — past/future is always computed from `scheduled_at` compared to `now()`.
- The FK is `recording_lesson_id` (NOT `lesson_id`): the migration must use `constrained('lessons')` explicitly, and the model relation must use `belongsTo(Lesson::class, 'recording_lesson_id')` explicitly — the Eloquent defaults would infer wrong names for both.
- `access_url` is a plain external link (`target="_blank" rel="noopener"`) — no download controller, no route through the server.
- Button/badge copy, exact: "Entrar", "Link em breve", "Ver na biblioteca", "Gravação em breve", "Próximo". Empty state: "Nenhum encontro agendado ainda."
- The app locale is `en` — never use `translatedFormat()` for the pt-BR month abbreviation; use the model's deterministic accessor (Task 1).
- NPS pós-encontro (issue #19), live "ao vivo agora" detection, recurring events, RSVP, and per-user timezones are all OUT of scope.

---

## Task 1: `Encontro` model + migration

**Files:**
- Create: `database/migrations/2026_08_29_170000_create_encontros_table.php`
- Create: `app/Models/Encontro.php`
- Test: `tests/Unit/EncontroTest.php`

**Interfaces:**
- Produces: `Encontro` model with `$fillable` (`tema`, `quem`, `scheduled_at`, `access_url`, `recording_lesson_id`), `scheduled_at` cast to `datetime`, `isPast(): bool`, `lesson(): BelongsTo` (custom FK `recording_lesson_id`), and a `scheduled_month_label` accessor returning the pt-BR month abbreviation (`'jan'`…`'dez'`). Task 2 (page/card), and Task 3 (Filament resource) depend on these exact names.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/EncontroTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Encontro;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EncontroTest extends TestCase
{
    use RefreshDatabase;

    private function lesson(): Lesson
    {
        $course = Course::create(['label' => 'Curso', 'title' => 'Teste', 'position' => 10]);

        return Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Gravação do encontro',
            'video_provider' => 'vimeo', 'video_id' => '123', 'published_at' => '2026-01-01', 'position' => 1,
        ]);
    }

    public function test_is_past_is_true_for_a_past_encontro(): void
    {
        $encontro = Encontro::create([
            'tema' => 'O comercial é gente', 'quem' => 'Com Douglas',
            'scheduled_at' => now()->subDay(),
        ]);

        $this->assertTrue($encontro->isPast());
    }

    public function test_is_past_is_false_for_a_future_encontro(): void
    {
        $encontro = Encontro::create([
            'tema' => 'Precificação sem medo', 'quem' => 'Convidada: Marina Prado',
            'scheduled_at' => now()->addDay(),
        ]);

        $this->assertFalse($encontro->isPast());
    }

    public function test_lesson_relationship_resolves_through_the_custom_fk(): void
    {
        $lesson = $this->lesson();
        $encontro = Encontro::create([
            'tema' => 'O comercial é gente', 'quem' => 'Com Douglas',
            'scheduled_at' => now()->subDay(), 'recording_lesson_id' => $lesson->id,
        ]);

        $this->assertTrue($encontro->lesson->is($lesson));
    }

    public function test_recording_lesson_id_is_nulled_when_the_linked_lesson_is_deleted(): void
    {
        $lesson = $this->lesson();
        $encontro = Encontro::create([
            'tema' => 'O comercial é gente', 'quem' => 'Com Douglas',
            'scheduled_at' => now()->subDay(), 'recording_lesson_id' => $lesson->id,
        ]);

        $lesson->delete();

        $this->assertNull($encontro->fresh()->recording_lesson_id);
    }

    public function test_scheduled_month_label_returns_the_pt_br_abbreviation(): void
    {
        $encontro = Encontro::create([
            'tema' => 'Decisão orientada por dados', 'quem' => 'Com Douglas',
            'scheduled_at' => '2026-07-29 19:00:00',
        ]);

        $this->assertSame('jul', $encontro->scheduled_month_label);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Unit/EncontroTest.php`
Expected: FAIL — `Encontro` class and `encontros` table don't exist yet.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_08_29_170000_create_encontros_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encontros', function (Blueprint $table) {
            $table->id();
            $table->string('tema');
            $table->string('quem');
            $table->dateTime('scheduled_at');
            $table->string('access_url')->nullable();
            $table->foreignId('recording_lesson_id')->nullable()->constrained('lessons')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encontros');
    }
};
```

Note the explicit `constrained('lessons')` — without the argument, Laravel would infer a
(nonexistent) `recording_lessons` table from the column name.

- [ ] **Step 4: Create the model**

Create `app/Models/Encontro.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Encontro extends Model
{
    use HasFactory;

    protected $fillable = [
        'tema',
        'quem',
        'scheduled_at',
        'access_url',
        'recording_lesson_id',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'recording_lesson_id');
    }

    public function isPast(): bool
    {
        return $this->scheduled_at->isPast();
    }

    /**
     * pt-BR month abbreviation, independent of the app locale (which is 'en').
     */
    protected function scheduledMonthLabel(): Attribute
    {
        return Attribute::get(fn () => [
            'jan', 'fev', 'mar', 'abr', 'mai', 'jun',
            'jul', 'ago', 'set', 'out', 'nov', 'dez',
        ][$this->scheduled_at->month - 1]);
    }
}
```

The explicit `'recording_lesson_id'` in `belongsTo` is required — the method name `lesson` would
otherwise make Eloquent look for a `lesson_id` column that doesn't exist on this table.

- [ ] **Step 5: Run migration and tests**

Run: `php artisan migrate`
Run: `php artisan test tests/Unit/EncontroTest.php`
Expected: PASS (all 5 tests).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_29_170000_create_encontros_table.php app/Models/Encontro.php tests/Unit/EncontroTest.php
git commit -m "feat: add Encontro model and migration"
```

---

## Task 2: The `/membros/encontros` page

**Files:**
- Create: `app/Livewire/Membros/Encontros.php`
- Create: `resources/views/livewire/membros/encontros.blade.php`
- Create: `resources/views/components/encontro-card.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Livewire/Membros/EncontrosTest.php`

**Interfaces:**
- Consumes: `Encontro` model (Task 1: `isPast()`, `lesson()`, `scheduled_month_label`); existing
  `tier:club` middleware alias (`App\Http\Middleware\EnsureTier`, redirects non-club to the
  dashboard); existing route `membros.aulas` with its `?lesson=` deep-link support.
- Produces: named route `membros.encontros` — Task 4's nav flip depends on it existing.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Livewire/Membros/EncontrosTest.php`:

```php
<?php

namespace Tests\Feature\Livewire\Membros;

use App\Livewire\Membros\Encontros;
use App\Models\Course;
use App\Models\Encontro;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EncontrosTest extends TestCase
{
    use RefreshDatabase;

    private function encontro(array $overrides = []): Encontro
    {
        return Encontro::create(array_merge([
            'tema' => 'O comercial é gente', 'quem' => 'Com Douglas',
            'scheduled_at' => now()->addDay(),
        ], $overrides));
    }

    private function lesson(): Lesson
    {
        $course = Course::create(['label' => 'Curso', 'title' => 'Teste', 'position' => 10]);

        return Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Gravação',
            'video_provider' => 'vimeo', 'video_id' => '123', 'published_at' => '2026-01-01', 'position' => 1,
        ]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/membros/encontros')->assertRedirect('/login');
    }

    public function test_start_tier_is_redirected_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'start']));

        $this->get('/membros/encontros')->assertRedirect('/membros');
    }

    public function test_club_tier_can_access_the_page(): void
    {
        $this->encontro();

        $this->actingAs(User::factory()->create(['tier' => 'club']));

        $this->get('/membros/encontros')->assertOk()->assertSee('Encontros do grupo');
    }

    public function test_mentor_tier_can_access_the_page(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));

        $this->get('/membros/encontros')->assertOk();
    }

    public function test_upcoming_come_first_ascending_then_past_descending(): void
    {
        $this->encontro(['tema' => 'Daqui a dois dias', 'scheduled_at' => now()->addDays(2)]);
        $this->encontro(['tema' => 'Amanhã', 'scheduled_at' => now()->addDay()]);
        $this->encontro(['tema' => 'Ontem', 'scheduled_at' => now()->subDay()]);
        $this->encontro(['tema' => 'Semana passada', 'scheduled_at' => now()->subWeek()]);

        $this->actingAs(User::factory()->create(['tier' => 'club']));

        Livewire::test(Encontros::class)
            ->assertSeeInOrder(['Amanhã', 'Daqui a dois dias', 'Ontem', 'Semana passada']);
    }

    public function test_proximo_badge_appears_only_on_the_nearest_upcoming_encontro(): void
    {
        $this->encontro(['tema' => 'Mais distante', 'scheduled_at' => now()->addDays(5)]);
        $this->encontro(['tema' => 'Mais próximo', 'scheduled_at' => now()->addDay()]);

        $this->actingAs(User::factory()->create(['tier' => 'club']));

        $html = Livewire::test(Encontros::class)->html();

        $this->assertSame(1, substr_count($html, 'Próximo'));
    }

    public function test_future_encontro_with_access_url_shows_the_entrar_link(): void
    {
        $this->encontro(['access_url' => 'https://zoom.us/j/123']);

        $this->actingAs(User::factory()->create(['tier' => 'club']));

        $html = Livewire::test(Encontros::class)->html();

        $this->assertStringContainsString('https://zoom.us/j/123', $html);
        $this->assertStringContainsString('Entrar', $html);
    }

    public function test_future_encontro_without_access_url_shows_link_em_breve(): void
    {
        $this->encontro(['access_url' => null]);

        $this->actingAs(User::factory()->create(['tier' => 'club']));

        Livewire::test(Encontros::class)->assertSee('Link em breve');
    }

    public function test_past_encontro_with_recording_links_to_the_library(): void
    {
        $lesson = $this->lesson();
        $this->encontro([
            'scheduled_at' => now()->subDay(), 'recording_lesson_id' => $lesson->id,
        ]);

        $this->actingAs(User::factory()->create(['tier' => 'club']));

        $html = Livewire::test(Encontros::class)->html();

        $this->assertStringContainsString('Ver na biblioteca', $html);
        $this->assertStringContainsString(route('membros.aulas', ['lesson' => $lesson->id]), $html);
    }

    public function test_past_encontro_without_recording_shows_gravacao_em_breve(): void
    {
        $this->encontro(['scheduled_at' => now()->subDay(), 'recording_lesson_id' => null]);

        $this->actingAs(User::factory()->create(['tier' => 'club']));

        Livewire::test(Encontros::class)->assertSee('Gravação em breve');
    }

    public function test_empty_state_shown_with_no_encontros(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'club']));

        Livewire::test(Encontros::class)->assertSee('Nenhum encontro agendado ainda.');
    }
}
```

(Gate tests go through HTTP `get()` because `Livewire::test()` bypasses route middleware; content
tests use `Livewire::test()` with a club user.)

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Livewire/Membros/EncontrosTest.php`
Expected: FAIL — route `membros.encontros` / class `App\Livewire\Membros\Encontros` don't exist.

- [ ] **Step 3: Create the Livewire component**

Create `app/Livewire/Membros/Encontros.php`:

```php
<?php

namespace App\Livewire\Membros;

use App\Livewire\Concerns\ComputesUserInitials;
use App\Models\Encontro;
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

    public function render()
    {
        return view('livewire.membros.encontros');
    }
}
```

- [ ] **Step 4: Create `<x-encontro-card>`**

Create `resources/views/components/encontro-card.blade.php`:

```blade
@props(['encontro', 'isNext' => false])

<div class="flex items-center gap-4 p-5 rounded-[18px] border border-sand bg-card shadow-[0_1px_2px_rgba(11,11,12,.05),0_10px_28px_rgba(11,11,12,.07)]">
    <div class="shrink-0 w-14 text-center rounded-xl border border-sand bg-paper py-2">
        <b class="block font-display text-lg leading-none">{{ $encontro->scheduled_at->format('d') }}</b>
        <small class="text-xs text-stone">{{ $encontro->scheduled_month_label }}</small>
    </div>

    <div class="flex-1 min-w-0">
        <b class="font-display text-sm block leading-tight">{{ $encontro->tema }}</b>
        <small class="mt-0.5 block text-xs text-stone">
            {{ $encontro->quem }} · {{ $encontro->scheduled_at->format('H\hi') }}
        </small>
    </div>

    <div class="flex items-center gap-2">
        @if ($encontro->isPast())
            @if ($encontro->recording_lesson_id && $encontro->lesson)
                <a href="{{ route('membros.aulas', ['lesson' => $encontro->recording_lesson_id]) }}" wire:navigate
                   class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-semibold border border-sand text-ink hover:border-black">
                    Ver na biblioteca
                </a>
            @else
                <span class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-semibold bg-card border border-sand text-stone cursor-not-allowed">
                    Gravação em breve
                </span>
            @endif
        @else
            @if ($isNext)
                <span class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-semibold uppercase tracking-wide bg-brand text-white">
                    Próximo
                </span>
            @endif

            @if ($encontro->access_url)
                <a href="{{ $encontro->access_url }}" target="_blank" rel="noopener"
                   class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-semibold bg-black text-white hover:brightness-110">
                    Entrar
                </a>
            @else
                <span class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-semibold bg-card border border-sand text-stone cursor-not-allowed">
                    Link em breve
                </span>
            @endif
        @endif
    </div>
</div>
```

Deliberate design note (do NOT "fix" this): unlike `<x-framework-card>`, the "Ver na biblioteca"
link has no `isAvailableFor()` check — this page is only reachable by club/mentor users
(`tier:club` on the whole route), for whom `Lesson::isAvailableFor()` is always true
(`hasClubAccess()` short-circuits it). Adding the check would be dead code.

- [ ] **Step 5: Create the view**

Create `resources/views/livewire/membros/encontros.blade.php`:

```blade
<div class="min-h-screen text-ink">
    <x-membros.header :initials="$this->userInitials" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="mb-8">
            <h1 class="text-[clamp(26px,4vw,38px)] leading-[1.05] font-display font-extrabold tracking-[-0.015em] text-black">
                Encontros do grupo
            </h1>
            <p class="mt-2 max-w-xl text-stone">
                Aulas ao vivo com o Douglas e convidados. As gravações vão direto para a biblioteca.
            </p>
        </div>

        @php
            $next = $this->encontros->first(fn ($encontro) => ! $encontro->isPast());
        @endphp

        <div class="flex flex-col gap-3 max-w-3xl">
            @forelse ($this->encontros as $encontro)
                <x-encontro-card :encontro="$encontro" :is-next="$next !== null && $encontro->is($next)" />
            @empty
                <p class="text-stone">Nenhum encontro agendado ainda.</p>
            @endforelse
        </div>
    </div>

    <x-membros.footer />
</div>
```

- [ ] **Step 6: Register the route**

In `routes/web.php`, add the import (alphabetical among the `App\Livewire\Membros` imports):

```php
use App\Livewire\Membros\Encontros;
```

Then, after the `membros.aulas` route block, add:

```php
Route::get('membros/encontros', Encontros::class)
    ->middleware(['auth', 'verified', 'active', 'tier:club'])
    ->name('membros.encontros');
```

**Known unrelated concurrent work:** `routes/web.php` has an uncommitted trailing
`require __DIR__.'/prototype.php';` line from separate work-in-progress — not yours to touch or
remove. Use a targeted edit (never a full-file rewrite), then run `git diff routes/web.php` and
confirm the trailing line is still present in the working tree. When staging, use
`git add -p routes/web.php` and stage ONLY the hunks containing your import + route; verify with
`git diff --staged` before committing.

- [ ] **Step 7: Run the tests**

Run: `php artisan test tests/Feature/Livewire/Membros/EncontrosTest.php`
Expected: PASS (all 11 tests).

- [ ] **Step 8: Commit**

```bash
git add app/Livewire/Membros/Encontros.php resources/views/livewire/membros/encontros.blade.php resources/views/components/encontro-card.blade.php tests/Feature/Livewire/Membros/EncontrosTest.php
git add -p routes/web.php
git commit -m "feat: add the real Encontros page, CLUB-exclusive"
```

---

## Task 3: Filament admin for Encontro

**Files:**
- Create: `app/Filament/Resources/Encontros/EncontroResource.php`
- Create: `app/Filament/Resources/Encontros/Schemas/EncontroForm.php`
- Create: `app/Filament/Resources/Encontros/Tables/EncontrosTable.php`
- Create: `app/Filament/Resources/Encontros/Pages/ListEncontros.php`
- Create: `app/Filament/Resources/Encontros/Pages/CreateEncontro.php`
- Create: `app/Filament/Resources/Encontros/Pages/EditEncontro.php`
- Test: `tests/Feature/Admin/EncontroResourceTest.php`

**Interfaces:**
- Consumes: `Encontro` model (Task 1 — in particular the `lesson` relation name, used by
  `relationship('lesson', 'title')`). Mirrors `App\Filament\Resources\Frameworks` file-for-file.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Admin/EncontroResourceTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Encontros\Pages\CreateEncontro;
use App\Filament\Resources\Encontros\Pages\EditEncontro;
use App\Filament\Resources\Encontros\Pages\ListEncontros;
use App\Models\Course;
use App\Models\Encontro;
use App\Models\Lesson;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EncontroResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function encontro(): Encontro
    {
        return Encontro::create([
            'tema' => 'O comercial é gente', 'quem' => 'Com Douglas',
            'scheduled_at' => now()->addDay(),
        ]);
    }

    public function test_non_admin_cannot_access_the_encontros_list(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->get('/admin/encontros')->assertForbidden();
    }

    public function test_admin_can_see_an_existing_encontro_in_the_list(): void
    {
        $encontro = $this->encontro();

        $this->actingAs($this->admin());

        Livewire::test(ListEncontros::class)
            ->assertCanSeeTableRecords([$encontro]);
    }

    public function test_admin_can_create_an_encontro(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateEncontro::class)
            ->fillForm([
                'tema' => 'Precificação sem medo',
                'quem' => 'Convidada: Marina Prado',
                'scheduled_at' => '2026-09-15 19:00:00',
                'access_url' => 'https://zoom.us/j/123',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('encontros', [
            'tema' => 'Precificação sem medo',
            'quem' => 'Convidada: Marina Prado',
        ]);
    }

    public function test_admin_can_link_a_recording_when_creating_an_encontro(): void
    {
        $course = Course::create(['label' => 'Curso', 'title' => 'Teste', 'position' => 10]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Gravação',
            'video_provider' => 'vimeo', 'video_id' => '123', 'published_at' => '2026-01-01', 'position' => 1,
        ]);

        $this->actingAs($this->admin());

        Livewire::test(CreateEncontro::class)
            ->fillForm([
                'tema' => 'O comercial é gente',
                'quem' => 'Com Douglas',
                'scheduled_at' => '2026-06-17 19:00:00',
                'recording_lesson_id' => $lesson->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('encontros', [
            'tema' => 'O comercial é gente',
            'recording_lesson_id' => $lesson->id,
        ]);
    }

    public function test_admin_can_edit_an_encontro(): void
    {
        $encontro = $this->encontro();

        $this->actingAs($this->admin());

        Livewire::test(EditEncontro::class, ['record' => $encontro->getKey()])
            ->fillForm(['tema' => 'Tema atualizado'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('encontros', [
            'id' => $encontro->id,
            'tema' => 'Tema atualizado',
        ]);
    }

    public function test_admin_can_delete_an_encontro(): void
    {
        $encontro = $this->encontro();

        $this->actingAs($this->admin());

        Livewire::test(ListEncontros::class)
            ->callTableAction(DeleteAction::class, record: $encontro);

        $this->assertDatabaseMissing('encontros', ['id' => $encontro->id]);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Admin/EncontroResourceTest.php`
Expected: FAIL — none of the resource classes exist yet.

- [ ] **Step 3: Create the form schema**

Create `app/Filament/Resources/Encontros/Schemas/EncontroForm.php`:

```php
<?php

namespace App\Filament\Resources\Encontros\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class EncontroForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('tema')
                    ->required(),
                TextInput::make('quem')
                    ->label('Quem')
                    ->placeholder('Com Douglas / Convidada: Marina Prado')
                    ->required(),
                DateTimePicker::make('scheduled_at')
                    ->label('Data e hora')
                    ->seconds(false)
                    ->required(),
                TextInput::make('access_url')
                    ->label('Link de acesso (Zoom/Meet)')
                    ->url()
                    ->nullable(),
                Select::make('recording_lesson_id')
                    ->label('Gravação na biblioteca')
                    ->relationship('lesson', 'title')
                    ->searchable()
                    ->nullable(),
            ]);
    }
}
```

- [ ] **Step 4: Create the table**

Create `app/Filament/Resources/Encontros/Tables/EncontrosTable.php`:

```php
<?php

namespace App\Filament\Resources\Encontros\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EncontrosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tema')
                    ->searchable(),
                TextColumn::make('quem')
                    ->searchable(),
                TextColumn::make('scheduled_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('lesson.title')
                    ->label('Gravação')
                    ->placeholder('—'),
            ])
            ->defaultSort('scheduled_at', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
```

- [ ] **Step 5: Create the pages**

Create `app/Filament/Resources/Encontros/Pages/ListEncontros.php`:

```php
<?php

namespace App\Filament\Resources\Encontros\Pages;

use App\Filament\Resources\Encontros\EncontroResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEncontros extends ListRecords
{
    protected static string $resource = EncontroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
```

Create `app/Filament/Resources/Encontros/Pages/CreateEncontro.php`:

```php
<?php

namespace App\Filament\Resources\Encontros\Pages;

use App\Filament\Resources\Encontros\EncontroResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEncontro extends CreateRecord
{
    protected static string $resource = EncontroResource::class;
}
```

Create `app/Filament/Resources/Encontros/Pages/EditEncontro.php`:

```php
<?php

namespace App\Filament\Resources\Encontros\Pages;

use App\Filament\Resources\Encontros\EncontroResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEncontro extends EditRecord
{
    protected static string $resource = EncontroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
```

- [ ] **Step 6: Create the resource**

Create `app/Filament/Resources/Encontros/EncontroResource.php`:

```php
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
```

If `Heroicon::OutlinedCalendarDays` doesn't exist in the installed Filament icon enum, reuse
`Heroicon::OutlinedSquares2x2` (already used by `FrameworkResource`) — the icon has no effect on
any test.

- [ ] **Step 7: Run the tests**

Run: `php artisan test tests/Feature/Admin/EncontroResourceTest.php`
Expected: PASS (all 6 tests).

- [ ] **Step 8: Commit**

```bash
git add app/Filament/Resources/Encontros tests/Feature/Admin/EncontroResourceTest.php
git commit -m "feat: add Filament admin resource for Encontro"
```

---

## Task 4: Unlock the Encontros nav tab

**Files:**
- Modify: `app/Support/PersonaNavigation.php`
- Modify: `tests/Unit/Support/PersonaNavigationTest.php`
- Modify: `tests/Feature/Membros/PersonaNavigationTest.php`

**Interfaces:**
- Consumes: named route `membros.encontros` (Task 2) — must exist before this task runs, because
  `x-membros.header` calls `route($tab['route'])` for every tab marked `available: true`.
- Note: `Dashboard::quickLinks()` does NOT reference `membros.encontros` (verified — it only uses
  `membros.aulas`, `membros.frameworks`, `membros.agenda`, `membros.upgrade`), so unlike previous
  nav flips, `DashboardTest` needs no changes this time.

- [ ] **Step 1: Write the failing tests**

In `tests/Unit/Support/PersonaNavigationTest.php`, replace the club test:

```php
    public function test_club_tier_has_three_available_tabs_and_four_locked_tabs(): void
    {
        $tabs = (new PersonaNavigation)->tabs('club');

        $this->assertCount(7, $tabs);
        $this->assertSame(
            ['Início', 'Aulas', 'Meu cofre', 'Minha sessão', 'Pessoas', 'Encontros', 'Frameworks'],
            array_column($tabs, 'label'),
        );
        $this->assertSame([true, true, false, false, false, false, true], array_column($tabs, 'available'));
    }
```

with:

```php
    public function test_club_tier_has_four_available_tabs_and_three_locked_tabs(): void
    {
        $tabs = (new PersonaNavigation)->tabs('club');

        $this->assertCount(7, $tabs);
        $this->assertSame(
            ['Início', 'Aulas', 'Meu cofre', 'Minha sessão', 'Pessoas', 'Encontros', 'Frameworks'],
            array_column($tabs, 'label'),
        );
        $this->assertSame([true, true, false, false, false, true, true], array_column($tabs, 'available'));
    }
```

(The `start` and `mentor` tests are untouched — Encontros doesn't exist in either of those lists.)

In `tests/Feature/Membros/PersonaNavigationTest.php`, replace the club test:

```php
    public function test_club_tier_shows_inicio_aulas_and_frameworks_as_links_and_four_tabs_locked(): void
    {
        $user = User::factory()->create(['tier' => 'club']);
        $this->actingAs($user);

        $html = $this->get('/membros')->assertOk()->getContent();

        foreach ([
            ['href' => 'http://localhost/membros', 'label' => 'Início'],
            ['href' => 'http://localhost/membros/aulas', 'label' => 'Aulas'],
            ['href' => 'http://localhost/membros/frameworks', 'label' => 'Frameworks'],
        ] as $link) {
            $this->assertMatchesRegularExpression(
                '#<a[^>]*href="'.preg_quote($link['href'], '#').'"[^>]*>\s*'.preg_quote($link['label'], '#').'\s*</a>#s',
                $html,
            );
        }

        foreach (['Meu cofre', 'Minha sessão', 'Pessoas', 'Encontros'] as $label) {
            $this->assertMatchesRegularExpression(
                '#<span[^>]*title="Em breve"[^>]*>\s*'.preg_quote($label, '#').'#s',
                $html,
            );
        }
    }
```

with:

```php
    public function test_club_tier_shows_inicio_aulas_encontros_and_frameworks_as_links_and_three_tabs_locked(): void
    {
        $user = User::factory()->create(['tier' => 'club']);
        $this->actingAs($user);

        $html = $this->get('/membros')->assertOk()->getContent();

        foreach ([
            ['href' => 'http://localhost/membros', 'label' => 'Início'],
            ['href' => 'http://localhost/membros/aulas', 'label' => 'Aulas'],
            ['href' => 'http://localhost/membros/encontros', 'label' => 'Encontros'],
            ['href' => 'http://localhost/membros/frameworks', 'label' => 'Frameworks'],
        ] as $link) {
            $this->assertMatchesRegularExpression(
                '#<a[^>]*href="'.preg_quote($link['href'], '#').'"[^>]*>\s*'.preg_quote($link['label'], '#').'\s*</a>#s',
                $html,
            );
        }

        foreach (['Meu cofre', 'Minha sessão', 'Pessoas'] as $label) {
            $this->assertMatchesRegularExpression(
                '#<span[^>]*title="Em breve"[^>]*>\s*'.preg_quote($label, '#').'#s',
                $html,
            );
        }
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Unit/Support/PersonaNavigationTest.php tests/Feature/Membros/PersonaNavigationTest.php`
Expected: FAIL — `Encontros` is still `available: false`.

- [ ] **Step 3: Flip the flag**

In `app/Support/PersonaNavigation.php`, in the `'club'` array only, change:

```php
                ['label' => 'Encontros', 'route' => 'membros.encontros', 'available' => false],
```

to:

```php
                ['label' => 'Encontros', 'route' => 'membros.encontros', 'available' => true],
```

(One one-word edit. The `start` and `mentor` arrays don't have an Encontros entry — nothing else
changes in this file.)

- [ ] **Step 4: Run the nav tests, then the full suite**

Run: `php artisan test tests/Unit/Support/PersonaNavigationTest.php tests/Feature/Membros/PersonaNavigationTest.php`
Expected: PASS.

Run: `php artisan test`
Expected: PASS — full suite green (`Dashboard::quickLinks()` doesn't read this entry, so no other
test should be affected; if anything else fails, investigate before committing).

- [ ] **Step 5: Commit**

```bash
git add app/Support/PersonaNavigation.php tests/Unit/Support/PersonaNavigationTest.php tests/Feature/Membros/PersonaNavigationTest.php
git commit -m "feat: unlock the Encontros nav tab now that the page exists"
```

---

## Manual verification (after Task 4)

1. Log in as a CLUB member: the header nav shows "Encontros" as a real link; as a Start member it
   doesn't appear at all (not even locked), and typing `/membros/encontros` directly redirects to
   the dashboard.
2. In Filament (`/admin/encontros`), create: one future encontro with an access URL, one future
   without, one past with a linked recording, one past without. Confirm on `/membros/encontros`:
   order (nearest future first, then other futures, then past newest-first), "Próximo" badge only
   on the nearest future one, "Entrar" opening the external URL in a new tab, "Ver na biblioteca"
   opening `/membros/aulas` with that recording playing in the hero, and the two "em breve"
   disabled states.
3. Run `php artisan test` (full suite) once more — green.
