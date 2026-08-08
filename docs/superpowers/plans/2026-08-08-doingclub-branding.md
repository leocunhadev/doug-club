# DO.ing Club / Douglas Oliveira Rebranding Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the inherited "Flávio Augusto / Estabilidade Não Existe" branding in the LMS with DO.ing Club / Douglas Oliveira branding — logo, colors, copy, and a new "Sobre" bio page.

**Architecture:** Pure presentation-layer change. A new `<x-brand-logo>` Blade component (SVG icon + wordmark) replaces `<x-application-logo>` everywhere it represents the site brand. New Tailwind color tokens (`brand`, `canvas`, `surface`, `surface-2`) replace ad-hoc `orange-500`/hex values for brand accents. Copy strings are edited in place. A new `Sobre` Livewire full-page component (same pattern as the existing `Dashboard` component) adds the bio page, sharing a new `<x-membros.footer>` component with `Dashboard`.

**Tech Stack:** Laravel 13, Livewire 3, Blade components, Tailwind CSS (JIT, no separate build step needed to review class changes), PHPUnit.

## Global Constraints

- Brand color: `#FF5100` (orange). Backgrounds: `#100B09` (canvas), `#1A120E` (surface), `#241813` (surface-2). Text: keep existing gray/white scale — spec only calls out background/accent colors, not the full gray scale.
- Do not touch `danger-button.blade.php` or `input-error.blade.php` — their red is an error color, not brand.
- Do not touch `database/seeders/LmsSeeder.php` lesson/course titles — demo data, out of scope.
- Footer copyright line reads exactly: `© DO.ing Club · {ano} Todos os direitos reservados.` — no company/legal-entity name.
- Run `php artisan test` (PHPUnit, class-based `test_*` methods, not Pest) to verify — this repo has no Pest DSL.

---

### Task 1: Tailwind brand color tokens

**Files:**
- Modify: `tailwind.config.js:14-17`

**Interfaces:**
- Produces: Tailwind utility classes `bg-brand`, `text-brand`, `border-brand`, `bg-canvas`, `bg-surface`, `bg-surface-2` (and `text-`/`border-` variants) available to every task below.

- [ ] **Step 1: Edit the color tokens**

Replace:

```js
            colors: {
                canvas: '#0a0a0b',
                surface: '#12141a',
            },
```

with:

```js
            colors: {
                canvas: '#100B09',
                surface: '#1A120E',
                'surface-2': '#241813',
                brand: '#FF5100',
            },
```

- [ ] **Step 2: Verify existing tests still pass (canvas/surface class names unchanged, only hex changed)**

Run: `php artisan test --filter=DashboardTest`
Expected: PASS (test asserts the class name `bg-canvas` is present, not its hex value)

- [ ] **Step 3: Commit**

```bash
git add tailwind.config.js
git commit -m "Update brand color tokens to match doingclub.com palette"
```

---

### Task 2: App name and Filament panel color

**Files:**
- Modify: `.env:1`
- Modify: `.env.example`
- Modify: `app/Providers/Filament/AdminPanelProvider.php:31-33`
- Test: `tests/Feature/Admin/PanelAccessTest.php` (existing, no changes needed — used to verify the panel still boots)

**Interfaces:**
- Consumes: `brand` color hex `#FF5100` (Task 1's constraint, used directly here since Filament needs a hex string, not a Tailwind class).

- [ ] **Step 1: Update APP_NAME in both env files**

In `.env`, line 1, change:
```
APP_NAME=Laravel
```
to:
```
APP_NAME="DO.ing Club"
```

Do the same edit in `.env.example` for whatever its current `APP_NAME` line is.

- [ ] **Step 2: Update the Filament panel primary color**

In `app/Providers/Filament/AdminPanelProvider.php`, replace:

```php
            ->colors([
                'primary' => Color::Amber,
            ])
```

with:

```php
            ->colors([
                'primary' => Color::hex('#FF5100'),
            ])
```

(`Color::hex()` already imported via the existing `use Filament\Support\Colors\Color;`.)

- [ ] **Step 3: Verify the panel still boots and shows the new name**

Run: `php artisan test --filter=PanelAccessTest`
Expected: PASS

Manually confirm the app name propagated: `php artisan tinker --execute="echo config('app.name');"`
Expected output: `DO.ing Club`

- [ ] **Step 4: Commit**

```bash
git add .env .env.example app/Providers/Filament/AdminPanelProvider.php
git commit -m "Rename app to DO.ing Club and use brand color in the admin panel"
```

---

### Task 3: `<x-brand-logo>` component, wired into the header and guest layout

**Files:**
- Create: `resources/views/components/brand-logo.blade.php`
- Modify: `resources/views/components/membros/header.blade.php`
- Modify: `resources/views/layouts/guest.blade.php`
- Test: `tests/Feature/Livewire/Membros/DashboardTest.php` (extend)

**Interfaces:**
- Produces: Blade component `<x-brand-logo :icon-only="bool" ... />` (attributes bag controls sizing via classes, e.g. `class="h-8 w-auto"`). Renders an `aria-label="DO.ing Club"` wrapper for accessibility and test targeting.
- Consumes: Tailwind `brand` color token from Task 1.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/Livewire/Membros/DashboardTest.php` (new test method, anywhere in the class body):

```php
    public function test_header_shows_the_brand_wordmark(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Dashboard::class)
            ->assertSee('DO.ing Club', false)
            ->assertDontSee('Estabilidade')
            ->assertDontSee('Não existe');
    }
```

- [ ] **Step 2: Run it to confirm it fails**

Run: `php artisan test --filter=test_header_shows_the_brand_wordmark`
Expected: FAIL (header still shows "Estabilidade" / "Não existe" text, no `aria-label="DO.ing Club"` anywhere yet)

- [ ] **Step 3: Create the component**

```blade
@props(['iconOnly' => false])

<span {{ $attributes->class(['inline-flex items-center gap-2']) }} aria-label="DO.ing Club">
    <svg viewBox="0 0 40 40" fill="none" aria-hidden="true" class="h-full w-auto shrink-0 text-brand">
        <circle cx="20" cy="20" r="2.6" fill="currentColor" />
        <circle cx="20" cy="20" r="8" stroke="currentColor" stroke-width="1.6" opacity=".8" />
        <circle cx="20" cy="20" r="14" stroke="currentColor" stroke-width="1.4" opacity=".45" />
        <circle cx="20" cy="20" r="19" stroke="currentColor" stroke-width="1.2" opacity=".2" />
    </svg>

    @unless ($iconOnly)
        <span class="font-bold leading-none">DO<span class="text-brand">.</span>ing<span class="opacity-60 font-semibold"> Club</span></span>
    @endunless
</span>
```

- [ ] **Step 4: Wire it into the membros header**

In `resources/views/components/membros/header.blade.php`, replace lines 5-11:

```blade
        <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2.5">
            <x-application-logo class="h-8 w-auto shrink-0 fill-current text-orange-500" />
            <span class="leading-tight">
                <span class="block text-sm font-bold uppercase tracking-wide text-white">Estabilidade</span>
                <span class="block text-[11px] font-medium uppercase tracking-widest text-gray-400">Não existe</span>
            </span>
        </a>
```

with:

```blade
        <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2.5">
            <x-brand-logo class="h-8 w-auto text-white" />
        </a>
```

(`text-white` colors the wordmark text: the wordmark spans have no explicit text color of their own, so they inherit it from this wrapper; the icon stays brand-orange regardless because its `text-brand` class is hardcoded inside the component.)

- [ ] **Step 5: Run the test from Step 1 — it should now pass**

Run: `php artisan test --filter=test_header_shows_the_brand_wordmark`
Expected: PASS

- [ ] **Step 6: Wire it into the guest (login/register) layout**

In `resources/views/layouts/guest.blade.php`, replace lines 19-23:

```blade
            <div>
                <a href="/" wire:navigate>
                    <x-application-logo class="w-20 h-20 fill-current text-gray-500" />
                </a>
            </div>
```

with:

```blade
            <div>
                <a href="/" wire:navigate>
                    <x-brand-logo class="h-14 w-auto text-gray-900" />
                </a>
            </div>
```

- [ ] **Step 7: Confirm the auth pages still work**

Run: `php artisan test --filter=AuthenticationTest`
Expected: PASS (unaffected by the markup change — these tests assert form behavior, not logo markup)

- [ ] **Step 8: Commit**

```bash
git add resources/views/components/brand-logo.blade.php resources/views/components/membros/header.blade.php resources/views/layouts/guest.blade.php tests/Feature/Livewire/Membros/DashboardTest.php
git commit -m "Add brand-logo component and wire it into the membros header and guest layout"
```

---

### Task 4: Swap the video-card watermark and brand accent colors

**Files:**
- Modify: `resources/views/livewire/membros/dashboard.blade.php:21` (video watermark)
- Modify: `resources/views/components/lesson-card.blade.php`
- Modify: `resources/views/components/lesson-card-simple.blade.php`

**Interfaces:**
- Consumes: `<x-brand-logo :icon-only="true">` from Task 3, `brand` Tailwind color from Task 1.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/Livewire/Membros/DashboardTest.php`:

```php
    public function test_video_watermark_uses_the_brand_icon_not_the_default_jetstream_logo(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);
        Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula 1',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertDontSee('305.8 81.125', false); // path data unique to the old Jetstream logo artwork
    }
```

- [ ] **Step 2: Run it to confirm it fails**

Run: `php artisan test --filter=test_video_watermark_uses_the_brand_icon_not_the_default_jetstream_logo`
Expected: FAIL (the old `<x-application-logo>` path is still rendered as the watermark)

- [ ] **Step 3: Update the dashboard video watermark**

In `resources/views/livewire/membros/dashboard.blade.php`, replace line 21:

```blade
                        <x-application-logo class="pointer-events-none absolute top-3 right-3 h-6 w-auto fill-current text-orange-500 drop-shadow" />
```

with:

```blade
                        <x-brand-logo icon-only class="pointer-events-none absolute top-3 right-3 h-6 w-auto drop-shadow" />
```

- [ ] **Step 4: Update `lesson-card.blade.php`**

Replace line 11:
```blade
                <x-application-logo class="h-4 w-auto fill-current text-orange-500" />
```
with:
```blade
                <x-brand-logo icon-only class="h-4 w-auto" />
```

Replace line 21 (`Assistindo` badge gradient):
```blade
                <span class="shrink-0 text-xs font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full bg-gradient-to-r from-orange-500 to-red-600 text-white">
```
with:
```blade
                <span class="shrink-0 text-xs font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full bg-brand text-white">
```

Replace line 29 (`Aula NN` number):
```blade
            <span class="text-3xl font-extrabold text-orange-500">{{ sprintf('%02d', $lesson->number) }}</span>
```
with:
```blade
            <span class="text-3xl font-extrabold text-brand">{{ sprintf('%02d', $lesson->number) }}</span>
```

- [ ] **Step 5: Update `lesson-card-simple.blade.php`**

Replace line 13 (fallback thumbnail gradient):
```blade
            <div class="absolute inset-0 bg-gradient-to-br from-orange-500 to-red-600"></div>
```
with:
```blade
            <div class="absolute inset-0 bg-brand"></div>
```

Replace line 16:
```blade
        <x-application-logo class="absolute top-2 left-2 h-4 w-auto fill-current text-orange-500 drop-shadow" />
```
with:
```blade
        <x-brand-logo icon-only class="absolute top-2 left-2 h-4 w-auto drop-shadow" />
```

Replace line 19 (`Assistindo` badge gradient — same fix as Step 4 above):
```blade
                <span class="absolute top-2 right-2 text-xs font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full bg-gradient-to-r from-orange-500 to-red-600 text-white">
```
with:
```blade
                <span class="absolute top-2 right-2 text-xs font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full bg-brand text-white">
```

- [ ] **Step 6: Run the full dashboard test file**

Run: `php artisan test --filter=DashboardTest`
Expected: PASS (all tests, including the new watermark test and the existing `test_watching_badge_appears_on_exactly_the_featured_lesson_card`, which only checks for the literal string `Assistindo`, unaffected by the class-name swap)

- [ ] **Step 7: Commit**

```bash
git add resources/views/livewire/membros/dashboard.blade.php resources/views/components/lesson-card.blade.php resources/views/components/lesson-card-simple.blade.php tests/Feature/Livewire/Membros/DashboardTest.php
git commit -m "Replace Jetstream watermark and orange-500 accents with brand icon/color"
```

---

### Task 5: "Sobre" bio page

**Files:**
- Create: `app/Livewire/Concerns/ComputesUserInitials.php`
- Modify: `app/Livewire/Membros/Dashboard.php` (use the new trait instead of its inline method)
- Create: `app/Livewire/Membros/Sobre.php`
- Create: `resources/views/livewire/membros/sobre.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Livewire/Membros/SobreTest.php` (new)

**Interfaces:**
- Produces: route `membros.sobre` (GET `/membros/sobre`, `auth`+`verified` middleware).
- Produces: trait `App\Livewire\Concerns\ComputesUserInitials` with `#[Computed] public function userInitials(): string` — usable by any Livewire component that needs the same initials logic.
- Consumes: the existing `<x-membros.header :initials="..." />` component (its logo was updated in Task 3).
- This page has no footer yet (Task 6 adds `<x-membros.footer>` here and wires the "Sobre" link to this route) — it renders standalone under the header for this task.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Livewire/Membros/SobreTest.php`:

```php
<?php

namespace Tests\Feature\Livewire\Membros;

use App\Livewire\Membros\Sobre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SobreTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/membros/sobre')->assertRedirect('/login');
    }

    public function test_page_renders_the_douglas_oliveira_bio(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Sobre::class)
            ->assertSee('Douglas Oliveira')
            ->assertSee('500')
            ->assertSee('10.000')
            ->assertSee('A visão do dono do negócio');
    }

    public function test_header_renders_with_user_initials(): void
    {
        $user = User::factory()->create(['name' => 'Ana Souza']);
        $this->actingAs($user);

        Livewire::test(Sobre::class)->assertSee('AS');
    }
}
```

- [ ] **Step 2: Run to confirm they fail**

Run: `php artisan test --filter=SobreTest`
Expected: FAIL (class `App\Livewire\Membros\Sobre` does not exist)

- [ ] **Step 3: Extract the initials trait**

Create `app/Livewire/Concerns/ComputesUserInitials.php`:

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
        $initials = collect(explode(' ', Auth::user()->name))
            ->filter()
            ->map(fn (string $part) => mb_substr($part, 0, 1))
            ->take(2)
            ->implode('');

        return mb_strtoupper($initials);
    }
}
```

In `app/Livewire/Membros/Dashboard.php`, remove the inline `userInitials()` method (lines 40-50) and add `use App\Livewire\Concerns\ComputesUserInitials;` to the imports plus `use ComputesUserInitials;` as the first line inside the class body:

```php
use App\Livewire\Concerns\ComputesUserInitials;
// ...existing imports...

#[Layout('layouts.membros')]
class Dashboard extends Component
{
    use ComputesUserInitials;

    public ?int $featuredLessonId = null;
    // ...rest of the class unchanged, minus the old userInitials() method...
```

- [ ] **Step 4: Run the Dashboard tests to confirm the trait extraction didn't break anything**

Run: `php artisan test --filter=DashboardTest`
Expected: PASS (all Dashboard tests, including `test_user_initials_are_computed_from_name`)

- [ ] **Step 5: Add the route**

In `routes/web.php`, add the import `use App\Livewire\Membros\Sobre;` and, after the `membros.materials.download` route:

```php
Route::get('membros/sobre', Sobre::class)
    ->middleware(['auth', 'verified'])
    ->name('membros.sobre');
```

- [ ] **Step 6: Create the Sobre component**

`app/Livewire/Membros/Sobre.php`:

```php
<?php

namespace App\Livewire\Membros;

use App\Livewire\Concerns\ComputesUserInitials;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.membros')]
class Sobre extends Component
{
    use ComputesUserInitials;

    public function render()
    {
        return view('livewire.membros.sobre');
    }
}
```

- [ ] **Step 7: Create the Sobre view**

`resources/views/livewire/membros/sobre.blade.php`:

```blade
<div class="min-h-screen text-white">
    <x-membros.header :initials="$this->userInitials" />

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-8">
        <h1 class="text-2xl font-bold">Sobre Douglas Oliveira</h1>

        <p class="text-gray-300 leading-relaxed">
            Douglas Oliveira soma 22 anos de mercado, sendo 16 deles dedicados aos setores de
            varejo e shopping centers. Já apoiou mais de 500 empreendedores e acumula mais de
            10.000 horas entre palestras e aulas, ajudando donos de negócio a destravar
            platôs de crescimento e enxergar pontos cegos que travam a expansão.
        </p>

        <blockquote class="border-l-4 border-brand pl-4 text-lg italic text-gray-200">
            &ldquo;A visão do dono do negócio é o que determina se a empresa avança ou
            estagna.&rdquo;
            <footer class="mt-1 text-sm not-italic text-gray-400">— Douglas Oliveira</footer>
        </blockquote>
    </div>
</div>
```

- [ ] **Step 8: Run the new tests to confirm they pass**

Run: `php artisan test --filter=SobreTest`
Expected: PASS

- [ ] **Step 9: Commit**

```bash
git add app/Livewire/Concerns/ComputesUserInitials.php app/Livewire/Membros/Dashboard.php app/Livewire/Membros/Sobre.php resources/views/livewire/membros/sobre.blade.php routes/web.php tests/Feature/Livewire/Membros/SobreTest.php
git commit -m "Add Sobre page with Douglas Oliveira bio"
```

---

### Task 6: Shared footer component + dashboard copy

**Files:**
- Create: `resources/views/components/membros/footer.blade.php`
- Modify: `resources/views/livewire/membros/dashboard.blade.php:5-141` (copy + footer extraction)
- Modify: `resources/views/livewire/membros/sobre.blade.php` (wire in the footer)
- Modify: `tests/Feature/Livewire/Membros/DashboardTest.php` (update footer assertions)
- Modify: `tests/Feature/Livewire/Membros/SobreTest.php` (add footer assertion)

**Interfaces:**
- Produces: `<x-membros.footer />` — no props, renders the privacy/sobre links, copyright line, and the WhatsApp floating button (reads `config('services.whatsapp.number')` itself, same as today).
- Consumes: `route('membros.sobre')` from Task 5 — already exists, so this component's tests can pass immediately, no cross-task breakage.

- [ ] **Step 1: Write the failing tests**

In `tests/Feature/Livewire/Membros/DashboardTest.php`, replace the existing `test_footer_renders_privacy_about_links_and_copyright` test:

```php
    public function test_footer_renders_privacy_about_links_and_copyright(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Dashboard::class)
            ->assertSee('Política de Privacidade')
            ->assertSee('Sobre')
            ->assertSee('DO.ing Club')
            ->assertSee('Todos os direitos reservados')
            ->assertDontSee('Flávio Augusto')
            ->assertDontSee('Geração de Valor');
    }
```

Also add a new hero-copy test:

```php
    public function test_hero_copy_references_douglas_oliveira(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Dashboard::class)
            ->assertSee('Douglas Oliveira')
            ->assertDontSee('Flávio Augusto');
    }
```

In `tests/Feature/Livewire/Membros/SobreTest.php`, add:

```php
    public function test_footer_renders_below_the_bio(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Sobre::class)->assertSee('Todos os direitos reservados');
    }
```

- [ ] **Step 2: Run to confirm they fail**

Run: `php artisan test --filter=DashboardTest` and `php artisan test --filter=SobreTest`
Expected: FAIL — old copy still present in the dashboard footer/hero; `Sobre` page has no footer yet.

- [ ] **Step 3: Create the footer component**

`resources/views/components/membros/footer.blade.php` — move the existing footer block (current `dashboard.blade.php:124-141`) here, updating the copyright line and the "Sobre" link's `href`:

```blade
<footer class="border-t border-slate-800/60 mt-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex flex-col items-center justify-center gap-2 text-center text-sm text-gray-400">
        <div class="flex gap-4">
            <a href="#" class="hover:text-white">Política de Privacidade</a>
            <a href="{{ route('membros.sobre') }}" wire:navigate class="hover:text-white">Sobre</a>
        </div>
        <p>&copy; DO.ing Club &middot; {{ now()->year }} Todos os direitos reservados.</p>
    </div>
</footer>

@if ($whatsappNumber = config('services.whatsapp.number'))
    <a href="https://wa.me/{{ $whatsappNumber }}" target="_blank" rel="noopener"
       class="fixed bottom-4 right-4 h-14 w-14 rounded-full bg-[#25D366] flex items-center justify-center shadow-lg hover:brightness-110">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-7 w-7 fill-white">
            <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.79.47 3.47 1.29 4.93L2 22l5.28-1.38a9.9 9.9 0 0 0 4.76 1.21h.01c5.46 0 9.9-4.45 9.9-9.91C21.96 6.45 17.5 2 12.04 2Zm5.8 14.08c-.24.68-1.4 1.3-1.93 1.38-.5.08-1.11.11-1.79-.11-.41-.13-.94-.3-1.62-.6-2.85-1.23-4.7-4.1-4.84-4.29-.14-.19-1.16-1.54-1.16-2.94s.73-2.09 1-2.38c.24-.26.53-.32.71-.32h.5c.16 0 .38-.03.58.44.24.57.81 1.98.88 2.12.07.15.11.31.02.5-.09.19-.14.31-.28.47-.14.16-.29.36-.42.48-.14.13-.28.28-.12.55.16.27.71 1.17 1.53 1.9 1.05.93 1.94 1.22 2.21 1.36.27.13.43.11.59-.07.16-.19.68-.79.86-1.06.18-.27.36-.22.6-.13.24.09 1.55.73 1.82.86.27.14.44.2.51.32.07.13.07.72-.17 1.4Z"/>
        </svg>
    </a>
@endif
```

- [ ] **Step 4: Update `dashboard.blade.php`**

Replace the hero paragraph (line 8):
```blade
                Acompanhe as transmissões ao vivo e os conteúdos gravados de Flávio Augusto. Tudo em um lugar só,
                exclusivo para quem decidiu agir.
```
with:
```blade
                Acompanhe as transmissões ao vivo e os conteúdos gravados de Douglas Oliveira. Tudo em um lugar só,
                exclusivo para quem decidiu agir.
```

Replace the footer block (lines 124-141) with:
```blade
    <x-membros.footer />
```

- [ ] **Step 5: Wire the footer into the Sobre page**

In `resources/views/livewire/membros/sobre.blade.php`, add `<x-membros.footer />` right before the closing `</div>` (after the bio `<div class="max-w-3xl ...">` block).

- [ ] **Step 6: Run all affected test files**

Run: `php artisan test --filter=DashboardTest` then `php artisan test --filter=SobreTest`
Expected: PASS on both.

- [ ] **Step 7: Commit**

```bash
git add resources/views/components/membros/footer.blade.php resources/views/livewire/membros/dashboard.blade.php resources/views/livewire/membros/sobre.blade.php tests/Feature/Livewire/Membros/DashboardTest.php tests/Feature/Livewire/Membros/SobreTest.php
git commit -m "Extract shared membros footer, wire into Sobre, and update dashboard copy to Douglas Oliveira"
```

---

### Task 7: Full-suite verification and manual browser check

**Files:** none (verification only)

- [ ] **Step 1: Run the full automated test suite**

Run: `php artisan test`
Expected: PASS, 0 failures.

- [ ] **Step 2: Grep for any remaining old-brand references outside the out-of-scope seeder**

Run: `grep -rniE "Flávio|Flavio|Estabilidade|Geração de Valor|application-logo" resources/views app --include=*.blade.php --include=*.php`
Expected: No matches inside files this plan touched. (`application-logo.blade.php` itself will still match its own filename if you grep filenames too — that's expected, the file stays unused in the repo per the design spec.)

- [ ] **Step 3: Manual browser check**

Start the dev server (`php artisan serve` and `npm run dev` if not already running), log in as a seeded user, and visually confirm:
- Membros header shows the orange circular icon + "DO.ing Club" wordmark, no "Estabilidade / Não existe" text.
- Login page shows the same logo in dark text on the light background.
- Video watermark and lesson-card badges use solid brand orange (no red-to-orange gradient).
- Footer reads "© DO.ing Club · {ano} Todos os direitos reservados." and the "Sobre" link opens `/membros/sobre` with the Douglas Oliveira bio.
- Admin panel at `/admin` shows "DO.ing Club" as the brand name and orange as the primary color.

- [ ] **Step 4: No commit for this task** — it's verification only. If Step 2 or Step 3 surfaces an issue, fix it as part of the relevant earlier task's files and amend that task's commit scope (create a small follow-up commit, do not amend already-pushed history).
