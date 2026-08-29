# Personas/planos (Start/CLUB/Mentor) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `tier` (start/club/mentor) to `User`, gate the mentor-only route behind it, render a persona-aware navigation shell in the shared header (locked tabs for features not built yet), and migrate the whole app's visual design tokens from the current dark theme to the light "paper/laranja" theme described in `doingclub.html`.

**Architecture:** A single new `tier` enum column drives two small, reusable pieces — `App\Support\PersonaNavigation::tabs()` (pure data: which tabs exist per tier, and whether each is wired to a real route yet) and `App\Http\Middleware\EnsureTier` (route gate). Both consumers (`x-membros.header` for nav, individual routes for gating) read `auth()->user()->tier` directly — there's no caching/session layer, it's a plain column read per request. The visual migration is a mechanical Tailwind token rename applied file by file, with the `/profile` page also switching off the unused Breeze default layout onto the app's real branded layout (see Task 12) and a new `POST /logout` route replacing the old `wire:click`-based logout so the header works both inside and outside a Livewire component (Task 2).

**Tech Stack:** Laravel 13, Blade, Livewire 3 + Volt, Tailwind CSS v3 (PostCSS, `tailwind.config.js`), PHPUnit (via `php artisan test`), SQLite (`:memory:` in tests).

**Spec:** `docs/superpowers/specs/2026-08-29-personas-tier-navigation-design.md`

## Global Constraints

- `tier` is `enum('start','club','mentor')`, default `'start'`, not null — no other values, no separate products table (spec §2).
- CLUB is hierarchical over Start (`hasClubAccess()` is true for both `club` and `mentor`); `mentor` is a distinct value, not "above" club (spec §1-2).
- No route uses `tier:club` yet — only `tier:mentor` (protecting `/membros/mentor`) is wired to a real route in this plan (spec §3).
- Nav tabs for features that don't exist yet render locked (🔒, no `href`, `title="Em breve"`), never as dead links (spec §4).
- Tier is assigned manually (seed/tinker/admin) — no payment-webhook integration in this plan (spec §1, §8).
- No fabricated content: the prototype's "onde paramos" mentoring-session quote block is explicitly NOT built (spec §5, §8).
- Visual migration is a token/class rename over existing structure, not a pixel-for-pixel rebuild of the prototype's cards/players/etc. (spec §6) — except `/profile`, which does change layout (off the unused Breeze scaffold, onto the branded layout) per spec §6.
- The app becomes single-theme (light/paper only) — all `dark:` Tailwind variants in touched files are removed, not just recolored (spec §6).

---

## Task 1: `tier` column, `User` helpers, and `initials` accessor

**Files:**
- Create: `database/migrations/2026_08_29_120000_add_tier_to_users_table.php`
- Modify: `app/Models/User.php`
- Modify: `app/Livewire/Concerns/ComputesUserInitials.php`
- Test: `tests/Unit/UserTierTest.php`

**Interfaces:**
- Produces: `User::hasClubAccess(): bool`, `User::isMentor(): bool`, `User::initials` (accessor, `$user->initials`), `users.tier` column (default `'start'`). Every later task that checks tier access uses these two methods — never raw `$user->tier === '...'` comparisons.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/UserTierTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTierTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_default_to_start_tier(): void
    {
        $user = User::factory()->create();

        $this->assertSame('start', $user->tier);
    }

    public function test_has_club_access_is_true_for_club_and_mentor_tiers(): void
    {
        $this->assertTrue(User::factory()->create(['tier' => 'club'])->hasClubAccess());
        $this->assertTrue(User::factory()->create(['tier' => 'mentor'])->hasClubAccess());
        $this->assertFalse(User::factory()->create(['tier' => 'start'])->hasClubAccess());
    }

    public function test_is_mentor_is_true_only_for_mentor_tier(): void
    {
        $this->assertTrue(User::factory()->create(['tier' => 'mentor'])->isMentor());
        $this->assertFalse(User::factory()->create(['tier' => 'club'])->isMentor());
        $this->assertFalse(User::factory()->create(['tier' => 'start'])->isMentor());
    }

    public function test_initials_are_computed_from_the_users_name(): void
    {
        $user = User::factory()->create(['name' => 'Ana Souza']);

        $this->assertSame('AS', $user->initials);
    }

    public function test_initials_take_at_most_two_letters(): void
    {
        $user = User::factory()->create(['name' => 'Ana Maria Souza Lima']);

        $this->assertSame('AM', $user->initials);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=UserTierTest`
Expected: FAIL — `tier` column doesn't exist / `hasClubAccess`, `isMentor`, `initials` are undefined on `User`.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_08_29_120000_add_tier_to_users_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('tier', ['start', 'club', 'mentor'])->default('start')->after('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('tier');
        });
    }
};
```

- [ ] **Step 4: Update `app/Models/User.php`**

Replace the full file with:

```php
<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'is_admin', 'access_revoked_at', 'email_verified_at', 'tier'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'access_revoked_at' => 'datetime',
        ];
    }

    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin;
    }

    public function hasClubAccess(): bool
    {
        return in_array($this->tier, ['club', 'mentor'], true);
    }

    public function isMentor(): bool
    {
        return $this->tier === 'mentor';
    }

    protected function initials(): Attribute
    {
        return Attribute::get(function () {
            $initials = collect(explode(' ', $this->name))
                ->filter()
                ->map(fn (string $part) => mb_substr($part, 0, 1))
                ->take(2)
                ->implode('');

            return mb_strtoupper($initials);
        });
    }
}
```

- [ ] **Step 5: Update `app/Livewire/Concerns/ComputesUserInitials.php`**

Replace the full file with:

```php
<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

trait ComputesUserInitials
{
    #[Computed]
    public function userInitials(): string
    {
        return Auth::user()->initials;
    }
}
```

- [ ] **Step 6: Run migration and test**

Run: `php artisan migrate` (applies to the dev SQLite DB)
Run: `php artisan test --filter=UserTierTest`
Expected: PASS (5 tests).

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_29_120000_add_tier_to_users_table.php app/Models/User.php app/Livewire/Concerns/ComputesUserInitials.php tests/Unit/UserTierTest.php
git commit -m "feat: add tier column and access helpers to User"
```

---

## Task 2: `POST /logout` route

The header (Task 5) needs a logout mechanism that works both inside a Livewire component (`Dashboard`) and on a plain Blade page (`/profile`, Task 12) — a form posting to a named route works in both. This also fixes a pre-existing dead button: the Sobre page's header "Sair" already does nothing today, since `Sobre` never defined a `logout()` Livewire method.

**Files:**
- Create: `app/Http/Controllers/Auth/LogoutController.php`
- Modify: `routes/auth.php`
- Test: `tests/Feature/Auth/LogoutTest.php`

**Interfaces:**
- Consumes: `App\Livewire\Actions\Logout` (existing, `__invoke()` logs out the `web` guard and invalidates the session).
- Produces: named route `logout` (`POST /logout`), used by the header form built in Task 5.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Auth/LogoutTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_guests_hitting_logout_are_redirected_to_login(): void
    {
        $this->post('/logout')->assertRedirect('/login');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=LogoutTest`
Expected: FAIL — route `logout` / `POST /logout` doesn't exist (404).

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Auth/LogoutController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Livewire\Actions\Logout;
use Illuminate\Http\RedirectResponse;

class LogoutController extends Controller
{
    public function __invoke(Logout $logout): RedirectResponse
    {
        $logout();

        return redirect('/login');
    }
}
```

- [ ] **Step 4: Register the route**

In `routes/auth.php`, add the import at the top:

```php
use App\Http\Controllers\Auth\LogoutController;
```

Then, inside the existing `Route::middleware('auth')->group(function () { ... });` block, add:

```php
    Route::post('logout', LogoutController::class)
        ->name('logout');
```

- [ ] **Step 5: Run the test**

Run: `php artisan test --filter=LogoutTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Auth/LogoutController.php routes/auth.php tests/Feature/Auth/LogoutTest.php
git commit -m "feat: add POST /logout route for use outside Livewire components"
```

---

## Task 3: `PersonaNavigation` support class

**Files:**
- Create: `app/Support/PersonaNavigation.php`
- Test: `tests/Unit/Support/PersonaNavigationTest.php`

**Interfaces:**
- Produces: `PersonaNavigation::tabs(string $tier): array<int, array{label: string, route: string, available: bool}>`. Task 5 (header) calls this to render nav. An entry with `available === false` must never have its `route` value passed to Laravel's `route()` helper (that route name doesn't exist yet).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Support/PersonaNavigationTest.php`:

```php
<?php

namespace Tests\Unit\Support;

use App\Support\PersonaNavigation;
use PHPUnit\Framework\TestCase;

class PersonaNavigationTest extends TestCase
{
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

    public function test_mentor_tier_has_four_locked_tabs_and_no_available_ones(): void
    {
        $tabs = (new PersonaNavigation)->tabs('mentor');

        $this->assertCount(4, $tabs);
        $this->assertSame(['Radar', 'Dossiês', 'Publicar', 'Disponibilidade'], array_column($tabs, 'label'));
        $this->assertSame([false, false, false, false], array_column($tabs, 'available'));
    }

    public function test_unknown_tier_returns_no_tabs(): void
    {
        $this->assertSame([], (new PersonaNavigation)->tabs('unknown'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PersonaNavigationTest`
Expected: FAIL — class `App\Support\PersonaNavigation` doesn't exist.

- [ ] **Step 3: Create the class**

Create `app/Support/PersonaNavigation.php`:

```php
<?php

namespace App\Support;

class PersonaNavigation
{
    /**
     * @return array<int, array{label: string, route: string, available: bool}>
     */
    public function tabs(string $tier): array
    {
        return match ($tier) {
            'start' => [
                ['label' => 'Início', 'route' => 'dashboard', 'available' => true],
                ['label' => 'Aulas', 'route' => 'membros.aulas', 'available' => false],
                ['label' => 'Frameworks', 'route' => 'membros.frameworks', 'available' => false],
                ['label' => 'Sessão 1:1', 'route' => 'membros.upgrade', 'available' => false],
            ],
            'club' => [
                ['label' => 'Início', 'route' => 'dashboard', 'available' => true],
                ['label' => 'Aulas', 'route' => 'membros.aulas', 'available' => false],
                ['label' => 'Meu cofre', 'route' => 'membros.cofre', 'available' => false],
                ['label' => 'Minha sessão', 'route' => 'membros.agenda', 'available' => false],
                ['label' => 'Pessoas', 'route' => 'membros.pessoas', 'available' => false],
                ['label' => 'Encontros', 'route' => 'membros.encontros', 'available' => false],
                ['label' => 'Frameworks', 'route' => 'membros.frameworks', 'available' => false],
            ],
            'mentor' => [
                ['label' => 'Radar', 'route' => 'mentor.radar', 'available' => false],
                ['label' => 'Dossiês', 'route' => 'mentor.dossies', 'available' => false],
                ['label' => 'Publicar', 'route' => 'mentor.conteudo', 'available' => false],
                ['label' => 'Disponibilidade', 'route' => 'mentor.disp', 'available' => false],
            ],
            default => [],
        };
    }
}
```

- [ ] **Step 4: Run the test**

Run: `php artisan test --filter=PersonaNavigationTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Support/PersonaNavigation.php tests/Unit/Support/PersonaNavigationTest.php
git commit -m "feat: add PersonaNavigation tab config per tier"
```

---

## Task 4: `EnsureTier` middleware + Mentor placeholder route

**Files:**
- Create: `app/Http/Middleware/EnsureTier.php`
- Modify: `bootstrap/app.php`
- Create: `app/Livewire/Membros/MentorPlaceholder.php`
- Create: `resources/views/livewire/membros/mentor-placeholder.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Membros/TierGatingTest.php`

**Interfaces:**
- Consumes: `User::hasClubAccess()`, `User::isMentor()` (Task 1); `ComputesUserInitials` trait (Task 1); `x-membros.header` (existing component, still only rendering logo+avatar at this point — Task 5 adds the nav row to it).
- Produces: middleware alias `tier` (usable as `'tier:club'` or `'tier:mentor'`); named route `mentor.placeholder` (`GET /membros/mentor`).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Membros/TierGatingTest.php`:

```php
<?php

namespace Tests\Feature\Membros;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TierGatingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/membros/mentor')->assertRedirect('/login');
    }

    public function test_start_tier_cannot_access_the_mentor_placeholder(): void
    {
        $user = User::factory()->create(['tier' => 'start']);
        $this->actingAs($user);

        $this->get('/membros/mentor')
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status');
    }

    public function test_club_tier_cannot_access_the_mentor_placeholder(): void
    {
        $user = User::factory()->create(['tier' => 'club']);
        $this->actingAs($user);

        $this->get('/membros/mentor')->assertRedirect(route('dashboard'));
    }

    public function test_mentor_tier_can_access_the_mentor_placeholder(): void
    {
        $user = User::factory()->create(['tier' => 'mentor']);
        $this->actingAs($user);

        $this->get('/membros/mentor')
            ->assertOk()
            ->assertSee('Seu painel de mentor está sendo construído');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TierGatingTest`
Expected: FAIL — route `/membros/mentor` doesn't exist (all four assertions fail, including the guest-redirect one, since a 404 isn't a redirect to `/login`).

- [ ] **Step 3: Create the middleware**

Create `app/Http/Middleware/EnsureTier.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTier
{
    public function handle(Request $request, Closure $next, string $minTier): Response
    {
        $user = $request->user();

        $allowed = match ($minTier) {
            'club' => $user?->hasClubAccess() ?? false,
            'mentor' => $user?->isMentor() ?? false,
            default => false,
        };

        if (! $allowed) {
            return redirect()->route('dashboard')
                ->with('status', "Esse conteúdo está disponível no {$minTier}.");
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Register the middleware alias**

In `bootstrap/app.php`, change:

```php
        $middleware->alias([
            'active' => \App\Http\Middleware\EnsureAccessIsActive::class,
        ]);
```

to:

```php
        $middleware->alias([
            'active' => \App\Http\Middleware\EnsureAccessIsActive::class,
            'tier' => \App\Http\Middleware\EnsureTier::class,
        ]);
```

- [ ] **Step 5: Create the Mentor placeholder Livewire component**

Create `app/Livewire/Membros/MentorPlaceholder.php`:

```php
<?php

namespace App\Livewire\Membros;

use App\Livewire\Concerns\ComputesUserInitials;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.membros')]
class MentorPlaceholder extends Component
{
    use ComputesUserInitials;

    public function render()
    {
        return view('livewire.membros.mentor-placeholder');
    }
}
```

Create `resources/views/livewire/membros/mentor-placeholder.blade.php`:

```blade
<div class="min-h-screen text-white">
    <x-membros.header :initials="$this->userInitials" />

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-24 text-center">
        <h1 class="text-2xl font-bold">Seu painel de mentor está sendo construído</h1>
        <p class="mt-3 text-gray-400">
            Radar do dia, dossiês dos mentorados, publicação de conteúdo e disponibilidade chegam em
            breve por aqui.
        </p>
    </div>

    <x-membros.footer />
</div>
```

(This still uses the current dark-theme classes — `text-white`/`text-gray-400` — matching every other membros view today. Task 9 recolors it along with the rest of the layout.)

- [ ] **Step 6: Register the route**

In `routes/web.php`, add the import:

```php
use App\Livewire\Membros\MentorPlaceholder;
```

Then, after the existing `membros.sobre` route registration, add:

```php
Route::get('membros/mentor', MentorPlaceholder::class)
    ->middleware(['auth', 'verified', 'active', 'tier:mentor'])
    ->name('mentor.placeholder');
```

- [ ] **Step 7: Run the test**

Run: `php artisan test --filter=TierGatingTest`
Expected: PASS (4 tests).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Middleware/EnsureTier.php bootstrap/app.php app/Livewire/Membros/MentorPlaceholder.php resources/views/livewire/membros/mentor-placeholder.blade.php routes/web.php tests/Feature/Membros/TierGatingTest.php
git commit -m "feat: gate a mentor placeholder route behind tier:mentor"
```

---

## Task 5: Persona nav in the header + logout via route

**Files:**
- Modify: `resources/views/components/membros/header.blade.php`
- Modify: `app/Livewire/Membros/Dashboard.php`
- Modify: `tests/Feature/Livewire/Membros/DashboardTest.php`
- Modify: `tests/Feature/Auth/AuthenticationTest.php`
- Test: `tests/Feature/Membros/PersonaNavigationTest.php`

**Interfaces:**
- Consumes: `PersonaNavigation::tabs()` (Task 3), named route `logout` (Task 2), `auth()->user()->tier`.
- Produces: `x-membros.header` now renders a second row of tabs below the logo/avatar bar, driven entirely by the current user's tier. Every later page that includes `x-membros.header` (Sobre, MentorPlaceholder, and — Task 12 — Profile) gets this nav automatically, no per-page wiring needed.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Membros/PersonaNavigationTest.php`:

```php
<?php

namespace Tests\Feature\Membros;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonaNavigationTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_mentor_tier_shows_all_four_tabs_locked(): void
    {
        $user = User::factory()->create(['tier' => 'mentor']);
        $this->actingAs($user);

        $html = $this->get('/membros/mentor')->assertOk()->getContent();

        foreach (['Radar', 'Dossiês', 'Publicar', 'Disponibilidade'] as $label) {
            $this->assertMatchesRegularExpression(
                '#<span[^>]*title="Em breve"[^>]*>\s*'.preg_quote($label, '#').'#s',
                $html,
            );
        }

        $this->assertStringNotContainsString('<a href="http://localhost/mentor', $html);
    }

    public function test_header_logout_button_posts_to_the_logout_route(): void
    {
        $this->actingAs(User::factory()->create());

        $html = $this->get('/membros')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<form[^>]*action="http://localhost/logout"[^>]*method="POST"#s',
            $html,
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PersonaNavigationTest`
Expected: FAIL — header doesn't render a nav row or a logout form yet.

- [ ] **Step 3: Update the header**

Replace the full content of `resources/views/components/membros/header.blade.php` with:

```blade
@props(['initials'])

<header class="border-b border-slate-800/60">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2.5">
            <x-brand-logo class="h-8 w-auto text-white" />
        </a>

        <x-dropdown align="right" width="48" contentClasses="py-1 bg-surface border border-slate-800/60">
            <x-slot name="trigger">
                <button type="button" class="h-9 w-9 rounded-full bg-gradient-to-br from-orange-500 to-red-600 text-sm font-semibold text-white flex items-center justify-center">
                    {{ $initials }}
                </button>
            </x-slot>

            <x-slot name="content">
                <a href="{{ route('profile') }}" wire:navigate class="block px-4 py-2 text-sm text-gray-300 hover:bg-slate-800/60">
                    Meu perfil
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full text-start px-4 py-2 text-sm text-gray-300 hover:bg-slate-800/60">
                        Sair
                    </button>
                </form>
            </x-slot>
        </x-dropdown>
    </div>

    <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-3 flex gap-1 overflow-x-auto" aria-label="Navegação principal">
        @foreach ((new \App\Support\PersonaNavigation)->tabs(auth()->user()->tier) as $tab)
            @if ($tab['available'])
                <a
                    href="{{ route($tab['route']) }}"
                    wire:navigate
                    @if (request()->routeIs($tab['route'])) aria-current="page" @endif
                    class="shrink-0 px-3 py-1.5 rounded-full text-sm font-medium {{ request()->routeIs($tab['route']) ? 'bg-white text-black' : 'text-gray-400 hover:text-white' }}"
                >
                    {{ $tab['label'] }}
                </a>
            @else
                <span class="shrink-0 px-3 py-1.5 rounded-full text-sm font-medium text-gray-600 cursor-not-allowed" title="Em breve">
                    {{ $tab['label'] }} 🔒
                </span>
            @endif
        @endforeach
    </nav>
</header>
```

(Still dark-theme classes throughout — Task 9 recolors this file.)

- [ ] **Step 4: Remove `Dashboard::logout()`**

In `app/Livewire/Membros/Dashboard.php`, remove the `use App\Livewire\Actions\Logout;` import line and this method:

```php
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/login', navigate: true);
    }
```

- [ ] **Step 5: Update `DashboardTest`**

In `tests/Feature/Livewire/Membros/DashboardTest.php`, remove this test entirely (the method it calls no longer exists):

```php
    public function test_user_can_log_out_from_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->call('logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }
```

- [ ] **Step 6: Update `AuthenticationTest`**

In `tests/Feature/Auth/AuthenticationTest.php`, remove this test (it targets the `layout.navigation` Volt component that Task 12 deletes; logout coverage now lives in `LogoutTest`):

```php
    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('layout.navigation');

        $component->call('logout');

        $component
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
    }
```

- [ ] **Step 7: Run the tests**

Run: `php artisan test --filter=PersonaNavigationTest`
Expected: PASS (4 tests).

Run: `php artisan test --filter=DashboardTest`
Expected: PASS (all remaining tests — one fewer than before).

Run: `php artisan test --filter=AuthenticationTest`
Expected: PASS (all remaining tests — one fewer than before).

- [ ] **Step 8: Commit**

```bash
git add resources/views/components/membros/header.blade.php app/Livewire/Membros/Dashboard.php tests/Feature/Livewire/Membros/DashboardTest.php tests/Feature/Auth/AuthenticationTest.php tests/Feature/Membros/PersonaNavigationTest.php
git commit -m "feat: render persona nav in the shared header, logout via route"
```

---

## Task 6: Tier-aware landing redirect

**Files:**
- Modify: `routes/web.php`
- Modify: `resources/views/livewire/pages/auth/login.blade.php`
- Test: `tests/Feature/Auth/AuthenticationTest.php`

**Interfaces:**
- Consumes: `User::isMentor()` (Task 1), named route `mentor.placeholder` (Task 4).

- [ ] **Step 1: Write the failing test**

In `tests/Feature/Auth/AuthenticationTest.php`, add this test (needs `use App\Models\User;`, already imported):

```php
    public function test_mentor_tier_lands_on_the_mentor_placeholder_after_login(): void
    {
        $user = User::factory()->create(['tier' => 'mentor']);

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password');

        $component->call('login');

        $component
            ->assertHasNoErrors()
            ->assertRedirect(route('mentor.placeholder', absolute: false));
    }

    public function test_root_redirects_mentor_tier_to_the_mentor_placeholder(): void
    {
        $user = User::factory()->create(['tier' => 'mentor']);
        $this->actingAs($user);

        $this->get('/')->assertRedirect(route('mentor.placeholder'));
    }

    public function test_root_redirects_club_tier_to_the_dashboard(): void
    {
        $user = User::factory()->create(['tier' => 'club']);
        $this->actingAs($user);

        $this->get('/')->assertRedirect(route('dashboard'));
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AuthenticationTest`
Expected: FAIL on the 3 new tests — mentor tier is currently redirected to `dashboard` in both places.

- [ ] **Step 3: Update the `/` route**

In `routes/web.php`, replace:

```php
Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});
```

with:

```php
Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    return auth()->user()->isMentor()
        ? redirect()->route('mentor.placeholder')
        : redirect()->route('dashboard');
});
```

- [ ] **Step 4: Update the login redirect default**

In `resources/views/livewire/pages/auth/login.blade.php`, add the import:

```php
use Illuminate\Support\Facades\Auth;
```

Then replace:

```php
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
```

with:

```php
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $default = Auth::user()->isMentor()
            ? route('mentor.placeholder', absolute: false)
            : route('dashboard', absolute: false);

        $this->redirectIntended(default: $default, navigate: true);
    }
```

- [ ] **Step 5: Run the tests**

Run: `php artisan test --filter=AuthenticationTest`
Expected: PASS (all tests, including the 3 new ones).

- [ ] **Step 6: Commit**

```bash
git add routes/web.php resources/views/livewire/pages/auth/login.blade.php tests/Feature/Auth/AuthenticationTest.php
git commit -m "feat: send mentor tier to the mentor placeholder on login and at /"
```

---

## Task 7: Persona-aware home copy

**Files:**
- Modify: `resources/views/livewire/membros/dashboard.blade.php`
- Test: `tests/Feature/Livewire/Membros/DashboardTest.php`

**Interfaces:**
- Consumes: `auth()->user()->hasClubAccess()` directly in the view (no new computed property needed — matches how the rest of `dashboard.blade.php` already reads `$course`/`$lesson` inline). `Dashboard.php` itself needs no change for this task.

- [ ] **Step 1: Write the failing test**

In `tests/Feature/Livewire/Membros/DashboardTest.php`, add:

```php
    public function test_start_tier_sees_a_generic_hero_title(): void
    {
        $user = User::factory()->create(['tier' => 'start']);
        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSee('Sua central de conteúdos')
            ->assertDontSee('Acompanhe as transmissões ao vivo e os conteúdos gravados de Douglas Oliveira');
    }

    public function test_club_tier_sees_the_full_hero_copy(): void
    {
        $user = User::factory()->create(['tier' => 'club']);
        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSee('Sua central de conteúdos')
            ->assertSee('Acompanhe as transmissões ao vivo e os conteúdos gravados de Douglas Oliveira', false);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=DashboardTest`
Expected: FAIL on `test_start_tier_sees_a_generic_hero_title` — today every tier sees the full CLUB copy.

- [ ] **Step 3: Update the view**

In `resources/views/livewire/membros/dashboard.blade.php`, replace:

```blade
            <p class="mt-1 max-w-2xl text-gray-400">
                Acompanhe as transmissões ao vivo e os conteúdos gravados de Douglas Oliveira. Tudo em um lugar só,
                exclusivo para quem decidiu agir.
            </p>
```

with:

```blade
            @if (auth()->user()->hasClubAccess())
                <p class="mt-1 max-w-2xl text-gray-400">
                    Acompanhe as transmissões ao vivo e os conteúdos gravados de Douglas Oliveira. Tudo em um lugar só,
                    exclusivo para quem decidiu agir.
                </p>
            @else
                <p class="mt-1 max-w-2xl text-gray-400">
                    Os conteúdos gravados de Douglas Oliveira, organizados pra você assistir no seu ritmo.
                </p>
            @endif
```

(`hasClubAccess()` — not a `=== 'start'` check — matches the spec's rule that this varies by "does this user have CLUB or not", so a future `mentor` visiting this page, if that ever happens, also sees the fuller copy rather than the Start-tier placeholder text.)

- [ ] **Step 4: Run the tests**

Run: `php artisan test --filter=DashboardTest`
Expected: PASS (all tests, including the 2 new ones).

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/membros/dashboard.blade.php tests/Feature/Livewire/Membros/DashboardTest.php
git commit -m "feat: vary dashboard hero copy by tier"
```

---

## Task 8: Tailwind design tokens + font swap

**Files:**
- Modify: `tailwind.config.js`
- Modify: `resources/views/layouts/membros.blade.php`
- Modify: `resources/views/layouts/guest.blade.php`

**Interfaces:**
- Produces: Tailwind utility classes `bg-paper`, `bg-card`, `bg-black`, `text-ink`, `border-sand`, `text-stone`, `bg-brand-soft`, `font-display` (Syne), `font-sans` (DM Sans, replaces Figtree). Every task from here on (9-15) uses these classes — they don't exist until this task runs, so `npm run build` would silently drop any of those classes referenced earlier.

- [ ] **Step 1: Update `tailwind.config.js`**

Replace the full file with:

```js
import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                paper: '#F6F3EE',
                ink: '#1A1A1C',
                black: '#0B0B0C',
                card: '#FFFFFF',
                sand: '#E6E0D6',
                stone: '#8B857A',
                brand: '#FF5100',
                'brand-soft': '#FFEDE4',
            },
            fontFamily: {
                display: ['Syne', ...defaultTheme.fontFamily.sans],
                sans: ['DM Sans', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
```

- [ ] **Step 2: Swap the font `<link>` in `layouts/membros.blade.php`**

Replace:

```blade
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
```

with:

```blade
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,700&display=swap" rel="stylesheet">
```

- [ ] **Step 3: Swap the font `<link>` in `layouts/guest.blade.php`**

Same replacement as Step 2, applied to `resources/views/layouts/guest.blade.php`.

- [ ] **Step 4: Verify the build**

Run: `npm run build`
Expected: builds without error.

- [ ] **Step 5: Commit**

```bash
git add tailwind.config.js resources/views/layouts/membros.blade.php resources/views/layouts/guest.blade.php
git commit -m "feat: switch design tokens to the paper/laranja palette and Syne/DM Sans"
```

---

## Task 9: Re-skin layout, header, footer

**Files:**
- Modify: `resources/views/layouts/membros.blade.php`
- Modify: `resources/views/components/membros/header.blade.php`
- Modify: `resources/views/components/membros/footer.blade.php`
- Modify: `tests/Feature/Livewire/Membros/DashboardTest.php`

Token mapping used throughout this task and Tasks 10-15 (old class → new class):

| Old | New |
|---|---|
| `bg-canvas` | `bg-paper` |
| `bg-surface` | `bg-card` |
| `text-white` | `text-ink` |
| `text-gray-400` | `text-stone` |
| `text-gray-300` | `text-stone` |
| `border-slate-800/60` | `border-sand` |
| `bg-gradient-to-br from-orange-500 to-red-600` | `bg-brand` |
| `bg-black/60` (chip on thumbnail) | unchanged (thumbnail stays a dark image backdrop) |
| `hover:bg-slate-800/60` | `hover:bg-paper` |

- [ ] **Step 1: Update `layouts/membros.blade.php`**

Replace:

```blade
    <body class="font-sans antialiased bg-canvas">
```

with:

```blade
    <body class="font-sans antialiased bg-paper text-ink">
```

- [ ] **Step 2: Update `components/membros/header.blade.php`**

Replace the full file with:

```blade
@props(['initials'])

<header class="border-b border-sand bg-paper">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2.5">
            <x-brand-logo class="h-8 w-auto text-black" />
        </a>

        <x-dropdown align="right" width="48" contentClasses="py-1 bg-card border border-sand">
            <x-slot name="trigger">
                <button type="button" class="h-9 w-9 rounded-full bg-brand text-sm font-semibold text-white flex items-center justify-center">
                    {{ $initials }}
                </button>
            </x-slot>

            <x-slot name="content">
                <a href="{{ route('profile') }}" wire:navigate class="block px-4 py-2 text-sm text-ink hover:bg-paper">
                    Meu perfil
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full text-start px-4 py-2 text-sm text-ink hover:bg-paper">
                        Sair
                    </button>
                </form>
            </x-slot>
        </x-dropdown>
    </div>

    <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-3 flex gap-1 overflow-x-auto" aria-label="Navegação principal">
        @foreach ((new \App\Support\PersonaNavigation)->tabs(auth()->user()->tier) as $tab)
            @if ($tab['available'])
                <a
                    href="{{ route($tab['route']) }}"
                    wire:navigate
                    @if (request()->routeIs($tab['route'])) aria-current="page" @endif
                    class="shrink-0 px-3 py-1.5 rounded-full text-sm font-medium {{ request()->routeIs($tab['route']) ? 'bg-black text-white' : 'text-stone hover:text-ink' }}"
                >
                    {{ $tab['label'] }}
                </a>
            @else
                <span class="shrink-0 px-3 py-1.5 rounded-full text-sm font-medium text-stone/50 cursor-not-allowed" title="Em breve">
                    {{ $tab['label'] }} 🔒
                </span>
            @endif
        @endforeach
    </nav>
</header>
```

- [ ] **Step 3: Update `components/membros/footer.blade.php`**

Replace:

```blade
<footer class="border-t border-slate-800/60 mt-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex flex-col items-center justify-center gap-2 text-center text-sm text-gray-400">
        <div class="flex gap-4">
            <a href="#" class="hover:text-white">Política de Privacidade</a>
            <a href="{{ route('membros.sobre') }}" wire:navigate class="hover:text-white">Sobre</a>
        </div>
```

with:

```blade
<footer class="border-t border-sand mt-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex flex-col items-center justify-center gap-2 text-center text-sm text-stone">
        <div class="flex gap-4">
            <a href="#" class="hover:text-ink">Política de Privacidade</a>
            <a href="{{ route('membros.sobre') }}" wire:navigate class="hover:text-ink">Sobre</a>
        </div>
```

- [ ] **Step 4: Update `DashboardTest`'s layout assertion**

In `tests/Feature/Livewire/Membros/DashboardTest.php`, replace:

```php
    public function test_membros_page_renders_through_the_dark_layout(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/membros')
            ->assertOk()
            ->assertSee('bg-canvas', false);
    }
```

with:

```php
    public function test_membros_page_renders_through_the_paper_layout(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/membros')
            ->assertOk()
            ->assertSee('bg-paper', false);
    }
```

- [ ] **Step 5: Run the tests and build**

Run: `php artisan test --filter=DashboardTest`
Expected: PASS (all tests).

Run: `php artisan test --filter=PersonaNavigationTest`
Expected: PASS (the header markup structure — tag names, `href`/`title` attributes — didn't change, only classes, so these still pass).

Run: `npm run build`
Expected: builds without error.

- [ ] **Step 6: Commit**

```bash
git add resources/views/layouts/membros.blade.php resources/views/components/membros/header.blade.php resources/views/components/membros/footer.blade.php tests/Feature/Livewire/Membros/DashboardTest.php
git commit -m "style: re-skin membros layout, header, and footer to the paper palette"
```

---

## Task 10: Re-skin dashboard and lesson cards

**Files:**
- Modify: `resources/views/livewire/membros/dashboard.blade.php`
- Modify: `resources/views/components/lesson-card.blade.php`
- Modify: `resources/views/components/lesson-card-simple.blade.php`

Read the current content of `resources/views/components/lesson-card-simple.blade.php` before editing — it has an uncommitted local change (hover ring moved to a `::after`-style overlay `div`) that must be preserved through this token swap, not reverted.

- [ ] **Step 1: Update `dashboard.blade.php`**

Apply the token mapping from Task 9 across the file. Concretely:

Replace:

```blade
        <section>
            <h1 class="text-2xl font-bold">Sua central de conteúdos</h1>
```

with:

```blade
        <section>
            <h1 class="text-2xl font-bold font-display text-ink">Sua central de conteúdos</h1>
```

Replace the two remaining `text-gray-400` occurrences in the hero (the tier-conditional paragraphs added in Task 7) with `text-stone`.

Replace:

```blade
                    class="mt-6 rounded-2xl border border-slate-800/60 bg-surface p-3 sm:p-4"
```

with:

```blade
                    class="mt-6 rounded-2xl border border-sand bg-card p-3 sm:p-4"
```

Replace both occurrences of:

```blade
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-surface border border-slate-800/60 text-gray-200 hover:bg-slate-800/60">
```

and

```blade
                        <span class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-surface border border-slate-800/60 text-gray-500 cursor-not-allowed">
```

with `bg-card border border-sand text-ink hover:bg-paper` and `bg-card border border-sand text-stone cursor-not-allowed` respectively (keep the rest of each class list — `inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium` — unchanged).

Replace:

```blade
                                 class="absolute left-0 z-10 mt-2 min-w-[14rem] rounded-lg border border-slate-800/60 bg-surface py-1 shadow-lg">
```

with:

```blade
                                 class="absolute left-0 z-10 mt-2 min-w-[14rem] rounded-lg border border-sand bg-card py-1 shadow-lg">
```

Replace both material-link classes:

```blade
                                        <a href="{{ route('membros.materials.download', $material) }}"
                                           class="block px-4 py-2 text-sm text-gray-200 hover:bg-slate-800/60">
```

```blade
                                        <a href="{{ $material->file_url }}" target="_blank" rel="noopener"
                                           class="block px-4 py-2 text-sm text-gray-200 hover:bg-slate-800/60">
```

→ `text-ink hover:bg-paper` in both.

Replace:

```blade
                <p class="mt-6 text-gray-400">Nenhuma aula disponível ainda.</p>
```

with `text-stone`.

Replace both course-heading blocks:

```blade
                        <h2 class="text-lg font-semibold">
                            {{ $course->label }}@if($course->title): {{ $course->title }}@endif
                        </h2>
                        @if ($course->description)
                            <p class="mt-2 text-sm text-gray-400">{{ $course->description }}</p>
                        @endif
```

with:

```blade
                        <h2 class="text-lg font-semibold font-display text-ink">
                            {{ $course->label }}@if($course->title): {{ $course->title }}@endif
                        </h2>
                        @if ($course->description)
                            <p class="mt-2 text-sm text-stone">{{ $course->description }}</p>
                        @endif
```

Replace both carousel arrow buttons' color classes:

```blade
                                @click="$refs.track.scrollBy({ left: -300, behavior: 'smooth' })"
                                class="hidden md:flex absolute left-2 top-1/2 -translate-y-1/2 h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-orange-500 to-red-600 text-white shadow-lg hover:brightness-110">
```

and the matching right-arrow button, replacing `bg-gradient-to-br from-orange-500 to-red-600` with `bg-brand` in both.

- [ ] **Step 2: Update `lesson-card.blade.php`**

Replace:

```blade
    {{ $attributes->class(['group relative shrink-0 w-64 text-left rounded-xl bg-surface ring-1 ring-inset ring-slate-800/60 transition hover:ring-brand hover:brightness-110']) }}
```

with:

```blade
    {{ $attributes->class(['group relative shrink-0 w-64 text-left rounded-xl bg-card ring-1 ring-inset ring-sand transition hover:ring-brand hover:brightness-110']) }}
```

Replace:

```blade
                <span class="mt-1 block truncate text-[10px] font-semibold uppercase tracking-widest text-orange-400">
```

with `text-brand` in place of `text-orange-400`.

Replace:

```blade
        <p class="text-xs text-gray-400">{{ $lesson->published_at->format('d/m/Y') }}</p>
        <p class="mt-1 text-sm font-medium text-white line-clamp-2">{{ $lesson->title }}</p>
```

with:

```blade
        <p class="text-xs text-stone">{{ $lesson->published_at->format('d/m/Y') }}</p>
        <p class="mt-1 text-sm font-medium text-ink line-clamp-2">{{ $lesson->title }}</p>
```

Leave the thumbnail's own dark backdrop (`bg-[radial-gradient(...)]`, `text-white/70`, the `bg-black/60` duration chip) untouched — that's the video thumbnail area, meant to stay dark regardless of page theme, same as the prototype's `.aula .thumb`.

- [ ] **Step 3: Read then update `lesson-card-simple.blade.php`**

Read the current file first (it has the uncommitted hover-ring change described above). Apply the same three replacements as Step 2 to whatever the current content is: `bg-surface` → `bg-card`, `ring-slate-800/60` → `ring-sand` (in both the button's own ring class and the overlay `div`'s ring class if the uncommitted change moved it there), `text-gray-400` → `text-stone`, `text-white` → `text-ink`.

- [ ] **Step 4: Verify**

Run: `php artisan test --filter=DashboardTest`
Expected: PASS.

Run: `npm run build`
Expected: builds without error.

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/membros/dashboard.blade.php resources/views/components/lesson-card.blade.php resources/views/components/lesson-card-simple.blade.php
git commit -m "style: re-skin dashboard hero and lesson cards to the paper palette"
```

---

## Task 11: Re-skin the Sobre and Mentor placeholder pages

**Files:**
- Modify: `resources/views/livewire/membros/sobre.blade.php`
- Modify: `resources/views/livewire/membros/mentor-placeholder.blade.php`
- Test: run existing `SobreTest` and `TierGatingTest` (verify only — no expected changes)

- [ ] **Step 1: Update `sobre.blade.php`**

Replace the full file with:

```blade
<div class="min-h-screen text-ink">
    <x-membros.header :initials="$this->userInitials" />

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-8">
        <h1 class="text-2xl font-bold font-display">Sobre Douglas Oliveira</h1>

        <p class="text-stone leading-relaxed">
            Douglas Oliveira soma 22 anos de mercado, sendo 16 deles dedicados aos setores de
            varejo e shopping centers. Já apoiou mais de 500 empreendedores e acumula mais de
            10.000 horas entre palestras e aulas, ajudando donos de negócio a destravar
            platôs de crescimento e enxergar pontos cegos que travam a expansão.
        </p>

        <blockquote class="border-l-4 border-brand pl-4 text-lg italic text-ink">
            &ldquo;A visão do dono do negócio é o que determina se a empresa avança ou
            estagna.&rdquo;
            <footer class="mt-1 text-sm not-italic text-stone">— Douglas Oliveira</footer>
        </blockquote>
    </div>

    <x-membros.footer />
</div>
```

- [ ] **Step 2: Update `mentor-placeholder.blade.php`**

Replace the full file with:

```blade
<div class="min-h-screen text-ink">
    <x-membros.header :initials="$this->userInitials" />

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-24 text-center">
        <h1 class="text-2xl font-bold font-display">Seu painel de mentor está sendo construído</h1>
        <p class="mt-3 text-stone">
            Radar do dia, dossiês dos mentorados, publicação de conteúdo e disponibilidade chegam em
            breve por aqui.
        </p>
    </div>

    <x-membros.footer />
</div>
```

- [ ] **Step 3: Run the tests**

Run: `php artisan test --filter=SobreTest`
Expected: PASS — the test suite only checks for text content (`Douglas Oliveira`, `500`, initials, footer copyright text), none of which changed.

Run: `php artisan test --filter=TierGatingTest`
Expected: PASS — `test_mentor_tier_can_access_the_mentor_placeholder` only checks for the heading text, which is unchanged.

- [ ] **Step 4: Commit**

```bash
git add resources/views/livewire/membros/sobre.blade.php resources/views/livewire/membros/mentor-placeholder.blade.php
git commit -m "style: re-skin the Sobre and Mentor placeholder pages to the paper palette"
```

---

## Task 12: Re-skin guest layout and login page

**Files:**
- Modify: `resources/views/layouts/guest.blade.php`
- Modify: `resources/views/livewire/pages/auth/login.blade.php`
- Test: run existing `AuthenticationTest` (no new test needed — purely visual)

- [ ] **Step 1: Update `layouts/guest.blade.php`**

Replace:

```blade
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100 dark:bg-gray-900">
            <div>
                <a href="/" wire:navigate>
                    <x-brand-logo class="h-14 w-auto text-gray-900 dark:text-white" />
                </a>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white dark:bg-gray-800 shadow-md overflow-hidden sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>
    </body>
```

with:

```blade
    <body class="font-sans text-ink antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-paper">
            <div>
                <a href="/" wire:navigate>
                    <x-brand-logo class="h-14 w-auto text-black" />
                </a>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-card border border-sand shadow-md overflow-hidden sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>
    </body>
```

- [ ] **Step 2: Update `login.blade.php`**

Replace:

```blade
        <div class="block mt-4">
            <label for="remember" class="inline-flex items-center">
                <input wire:model="form.remember" id="remember" type="checkbox" class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800" name="remember">
                <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" href="{{ route('password.request') }}" wire:navigate>
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button class="ms-3">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>

    @if ($paymentLinkUrl = config('services.abacatepay.payment_link_url'))
        <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700 text-center">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Ainda não é membro?
            </p>
            <a href="{{ $paymentLinkUrl }}" target="_blank" rel="noopener"
               class="mt-2 inline-flex items-center px-4 py-2 bg-brand border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                Quero fazer parte
            </a>
        </div>
    @endif
```

with:

```blade
        <div class="block mt-4">
            <label for="remember" class="inline-flex items-center">
                <input wire:model="form.remember" id="remember" type="checkbox" class="rounded border-sand text-brand shadow-sm focus:ring-brand" name="remember">
                <span class="ms-2 text-sm text-stone">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-stone hover:text-ink rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand" href="{{ route('password.request') }}" wire:navigate>
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button class="ms-3">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>

    @if ($paymentLinkUrl = config('services.abacatepay.payment_link_url'))
        <div class="mt-6 pt-6 border-t border-sand text-center">
            <p class="text-sm text-stone">
                Ainda não é membro?
            </p>
            <a href="{{ $paymentLinkUrl }}" target="_blank" rel="noopener"
               class="mt-2 inline-flex items-center px-4 py-2 bg-brand border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-offset-2 transition ease-in-out duration-150">
                Quero fazer parte
            </a>
        </div>
    @endif
```

- [ ] **Step 3: Run the tests and build**

Run: `php artisan test --filter=AuthenticationTest`
Expected: PASS.

Run: `npm run build`
Expected: builds without error.

- [ ] **Step 4: Commit**

```bash
git add resources/views/layouts/guest.blade.php resources/views/livewire/pages/auth/login.blade.php
git commit -m "style: re-skin guest layout and login page to the paper palette"
```

---

## Task 13: Re-skin shared form/UI components

**Files:**
- Modify: `resources/views/components/text-input.blade.php`
- Modify: `resources/views/components/primary-button.blade.php`
- Modify: `resources/views/components/secondary-button.blade.php`
- Modify: `resources/views/components/danger-button.blade.php`
- Modify: `resources/views/components/input-label.blade.php`
- Modify: `resources/views/components/input-error.blade.php`
- Modify: `resources/views/components/dropdown.blade.php`
- Modify: `resources/views/components/modal.blade.php`
- Modify: `resources/views/components/action-message.blade.php`
- Modify: `resources/views/components/auth-session-status.blade.php`

These are used by login (Task 12, already done) and by the profile forms (Task 15). All `dark:` variants are dropped — the app has no dark-mode toggle.

- [ ] **Step 1: `text-input.blade.php`**

Replace:

```blade
<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm']) }}>
```

with:

```blade
<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-sand text-ink focus:border-brand focus:ring-brand rounded-md shadow-sm']) }}>
```

- [ ] **Step 2: `primary-button.blade.php`**

Replace:

```blade
<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150']) }}>
```

with:

```blade
<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-black border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand focus:bg-brand active:bg-brand focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2 transition ease-in-out duration-150']) }}>
```

- [ ] **Step 3: `secondary-button.blade.php`**

Replace:

```blade
<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150']) }}>
```

with:

```blade
<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-card border border-sand rounded-md font-semibold text-xs text-ink uppercase tracking-widest shadow-sm hover:bg-paper focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150']) }}>
```

- [ ] **Step 4: `danger-button.blade.php`**

Replace:

```blade
<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 active:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150']) }}>
```

with (drop only the `dark:focus:ring-offset-gray-800`, the red palette is semantic and unrelated to the paper/dark token swap):

```blade
<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 active:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150']) }}>
```

- [ ] **Step 5: `input-label.blade.php`**

Replace:

```blade
<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-gray-700 dark:text-gray-300']) }}>
```

with:

```blade
<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-ink']) }}>
```

- [ ] **Step 6: `input-error.blade.php`**

Replace:

```blade
    <ul {{ $attributes->merge(['class' => 'text-sm text-red-600 dark:text-red-400 space-y-1']) }}>
```

with:

```blade
    <ul {{ $attributes->merge(['class' => 'text-sm text-red-600 space-y-1']) }}>
```

- [ ] **Step 7: `dropdown.blade.php`**

Replace the `@props` default and the ring color:

```blade
@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'py-1 bg-white dark:bg-gray-700'])
```

with:

```blade
@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'py-1 bg-card'])
```

and:

```blade
        <div class="rounded-md ring-1 ring-black ring-opacity-5 {{ $contentClasses }}">
```

with:

```blade
        <div class="rounded-md ring-1 ring-sand {{ $contentClasses }}">
```

(Every call site in this codebase already passes its own explicit `contentClasses`, so this default only matters if a future caller omits the prop — keep it consistent with the new palette regardless.)

- [ ] **Step 8: `modal.blade.php`**

Replace:

```blade
        <div class="absolute inset-0 bg-gray-500 dark:bg-gray-900 opacity-75"></div>
```

with:

```blade
        <div class="absolute inset-0 bg-black opacity-50"></div>
```

Replace:

```blade
        class="mb-6 bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-xl transform transition-all sm:w-full {{ $maxWidth }} sm:mx-auto"
```

with:

```blade
        class="mb-6 bg-card border border-sand rounded-lg overflow-hidden shadow-xl transform transition-all sm:w-full {{ $maxWidth }} sm:mx-auto"
```

- [ ] **Step 9: `action-message.blade.php`**

Replace:

```blade
    {{ $attributes->merge(['class' => 'text-sm text-gray-600 dark:text-gray-400']) }}>
```

with:

```blade
    {{ $attributes->merge(['class' => 'text-sm text-stone']) }}>
```

- [ ] **Step 10: `auth-session-status.blade.php`**

Replace:

```blade
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-green-600 dark:text-green-400']) }}>
```

with:

```blade
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-green-600']) }}>
```

- [ ] **Step 11: Verify**

Run: `php artisan test --filter=AuthenticationTest`
Expected: PASS (login page uses several of these components).

Run: `npm run build`
Expected: builds without error.

- [ ] **Step 12: Commit**

```bash
git add resources/views/components/text-input.blade.php resources/views/components/primary-button.blade.php resources/views/components/secondary-button.blade.php resources/views/components/danger-button.blade.php resources/views/components/input-label.blade.php resources/views/components/input-error.blade.php resources/views/components/dropdown.blade.php resources/views/components/modal.blade.php resources/views/components/action-message.blade.php resources/views/components/auth-session-status.blade.php
git commit -m "style: re-skin shared form/UI components, drop dark: variants"
```

---

## Task 14: Move `/profile` onto the branded layout, retire the unused Breeze nav

**Files:**
- Modify: `resources/views/profile.blade.php`
- Delete: `resources/views/layouts/app.blade.php`
- Delete: `resources/views/livewire/layout/navigation.blade.php`
- Delete: `resources/views/components/nav-link.blade.php`
- Delete: `resources/views/components/responsive-nav-link.blade.php`
- Delete: `resources/views/components/dropdown-link.blade.php`
- Test: run existing `ProfileTest` (no new test needed)

Before deleting, confirm nothing else references these (already verified during planning — only `profile.blade.php`, `layouts/app.blade.php`, and `livewire/layout/navigation.blade.php` itself referenced `x-app-layout`/`layout.navigation`/the three link components; re-run the check below to be safe against any drift since planning):

- [ ] **Step 1: Re-confirm nothing else depends on the files being deleted**

Run: `grep -rln "x-app-layout\|layout\.navigation\|nav-link\|dropdown-link" resources tests --include=*.php` (Bash tool, since Grep tool output was already captured during planning — this is a quick re-check before an irreversible-in-spirit deletion)

Expected: only `resources/views/profile.blade.php`, `resources/views/layouts/app.blade.php`, `resources/views/livewire/layout/navigation.blade.php`. If anything else shows up, stop and re-plan this task — don't delete files something else still needs.

- [ ] **Step 2: Update `profile.blade.php`**

Replace the full file with:

```blade
<div class="min-h-screen text-ink">
    <x-membros.header :initials="auth()->user()->initials" />

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-card border border-sand shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <livewire:profile.update-profile-information-form />
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-card border border-sand shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <livewire:profile.update-password-form />
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-card border border-sand shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <livewire:profile.delete-user-form />
                </div>
            </div>
        </div>
    </div>

    <x-membros.footer />
</div>
```

(The page heading "Profile" that used to live in `<x-slot name="header">` is dropped — the branded layout has no page-title slot, and the header nav already makes clear where the user is. If a heading is wanted later, add an `<h1>` above the cards; not needed to satisfy `ProfileTest`, which doesn't assert on it.)

- [ ] **Step 3: Delete the unused files**

```bash
git rm resources/views/layouts/app.blade.php
git rm resources/views/livewire/layout/navigation.blade.php
git rm resources/views/components/nav-link.blade.php
git rm resources/views/components/responsive-nav-link.blade.php
git rm resources/views/components/dropdown-link.blade.php
```

- [ ] **Step 4: Run the tests**

Run: `php artisan test --filter=ProfileTest`
Expected: PASS — `assertSeeVolt` checks for the 3 Volt component names, which are unchanged.

Run: `php artisan test`
Expected: full suite PASS. This is the first point where a stray reference to any deleted file would surface as a hard error (missing view/component), so run the whole suite here, not just a filtered subset.

- [ ] **Step 5: Commit**

```bash
git add resources/views/profile.blade.php
git commit -m "refactor: move /profile onto the branded membros layout, drop the unused Breeze nav scaffold"
```

---

## Task 15: Re-skin profile form components

**Files:**
- Modify: `resources/views/livewire/profile/update-profile-information-form.blade.php`
- Modify: `resources/views/livewire/profile/update-password-form.blade.php`
- Modify: `resources/views/livewire/profile/delete-user-form.blade.php`
- Test: run existing `ProfileTest` (no new test needed — purely visual)

- [ ] **Step 1: Update `update-profile-information-form.blade.php`**

Replace:

```blade
<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>
```

with:

```blade
<section>
    <header>
        <h2 class="text-lg font-medium text-ink">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-stone">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>
```

Replace:

```blade
                    <p class="text-sm mt-2 text-gray-800 dark:text-gray-200">
                        {{ __('Your email address is unverified.') }}

                        <button wire:click.prevent="sendVerification" class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600 dark:text-green-400">
```

with:

```blade
                    <p class="text-sm mt-2 text-ink">
                        {{ __('Your email address is unverified.') }}

                        <button wire:click.prevent="sendVerification" class="underline text-sm text-stone hover:text-ink rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
```

- [ ] **Step 2: Update `update-password-form.blade.php`**

Replace:

```blade
<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Update Password') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Ensure your account is using a long, random password to stay secure.') }}
        </p>
    </header>
```

with:

```blade
<section>
    <header>
        <h2 class="text-lg font-medium text-ink">
            {{ __('Update Password') }}
        </h2>

        <p class="mt-1 text-sm text-stone">
            {{ __('Ensure your account is using a long, random password to stay secure.') }}
        </p>
    </header>
```

- [ ] **Step 3: Update `delete-user-form.blade.php`**

Replace:

```blade
<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Delete Account') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
        </p>
    </header>
```

with:

```blade
<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-ink">
            {{ __('Delete Account') }}
        </h2>

        <p class="mt-1 text-sm text-stone">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
        </p>
    </header>
```

Replace both remaining occurrences inside the modal:

```blade
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Are you sure you want to delete your account?') }}
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
```

with:

```blade
            <h2 class="text-lg font-medium text-ink">
                {{ __('Are you sure you want to delete your account?') }}
            </h2>

            <p class="mt-1 text-sm text-stone">
```

- [ ] **Step 4: Run the tests and build**

Run: `php artisan test --filter=ProfileTest`
Expected: PASS.

Run: `npm run build`
Expected: builds without error.

Run: `php artisan test`
Expected: full suite PASS — this is the last task, confirming nothing elsewhere regressed.

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/profile/update-profile-information-form.blade.php resources/views/livewire/profile/update-password-form.blade.php resources/views/livewire/profile/delete-user-form.blade.php
git commit -m "style: re-skin profile form sections to the paper palette"
```

---

## Manual verification (after Task 15)

Automated tests don't cover pixel-level rendering. Before considering this plan done, start `npm run dev` (or `composer dev`) and, in a browser:

1. Log in as a `tier=start` user (set via `php artisan tinker`: `User::first()->update(['tier' => 'start'])`) — confirm `/membros` shows "Início" as a working tab and the other 3 as locked/greyed with a lock icon and no click behavior.
2. Switch the same user to `tier=club` — confirm the dashboard hero copy changes back to the full CLUB text, and the nav now shows 6 locked tabs instead of 3.
3. Switch to `tier=mentor` — confirm login lands on `/membros/mentor` (not `/membros`), and that a `start`/`club` user manually visiting `/membros/mentor` gets bounced back to `/membros` with a toast/status message.
4. Visit `/profile` — confirm it renders with the same header/nav as `/membros` (not a different navbar), the "Meu perfil" and "Sair" dropdown items work, and the three profile forms are legible on the light background.
5. Confirm dark-theme classes are gone: nothing on these pages should look like inverted/dark colors regardless of OS dark-mode setting (there are no more `dark:` variants to react to it).
