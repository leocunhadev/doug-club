# Fluxo de upgrade Start → CLUB Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the real `/membros/upgrade` page (closes GitHub issue #24): a Start-tier member applies for the CLUB, the mentor reviews and approves manually via Filament, and the member is notified by e-mail.

**Architecture:** `ClubApplication` is a minimal standalone model (`user_id` only, no status field — a row existing means "pending") mirroring the `BridgeRequest` pattern from #23: resolved by either an "Aprovar" Filament action (mutates `tier`, sends a mail notification, deletes the row) or the standard `DeleteAction` (just deletes — "Recusar"). Access to the application page requires exactly `tier=start`, a check `EnsureTier` doesn't support yet (it only knows "at least club" and "is mentor"), so this plan adds an `isStart()`/`'start'` case symmetric to the existing `isMentor()`/`'mentor'` case. The approval e-mail is a plain synchronous Laravel `Notification` (`toMail()`), matching the existing `ActivateUserFromPayment`'s use of `Password::sendResetLink`.

**Tech Stack:** Laravel 13, Livewire 3, Filament 4, Tailwind CSS v3, PHPUnit (`php artisan test`), SQLite (`:memory:` in tests).

**Spec:** `docs/superpowers/specs/2026-08-30-upgrade-club-design.md`

## Global Constraints

- No checkout/payment automation — this is an application-and-manual-approval flow only. `AbacatePayWebhookController` is not touched.
- `ClubApplication` has no status field. A row existing = pending. "Aprovar" mutates `tier` + notifies + deletes the row. "Recusar" (`DeleteAction`) only deletes — no rejection state, no notification.
- The `/membros/upgrade` route requires exactly `tier=start` (`->middleware([..., 'tier:start'])`) — CLUB and mentor members must be redirected away, not just Start members let in.
- `ClubApplicationApproved` is a synchronous Laravel `Notification` (no `ShouldQueue`), sent via `$user->notify(...)`.
- `apply()` on the `Upgrade` Livewire component must `unset($this->hasApplied)` immediately after `ClubApplication::create(...)` — a `#[Computed]` property's cache survives the same request's `render()`, a framework behavior already independently verified during #23 (Pessoas). Without the `unset()`, the button would keep showing "Aplicar" in the very request that just recorded the application.
- No `.btn`/`.btn.solid` prototype CSS classes — buttons use plain Tailwind utilities, matching every other real page built this session.

---

## Task 1: Data model + `tier=start` gate

**Files:**
- Create: `database/migrations/2026_08_30_140000_create_club_applications_table.php`
- Create: `app/Models/ClubApplication.php`
- Test: `tests/Unit/ClubApplicationTest.php`
- Modify: `app/Models/User.php` (add `isStart()`, right after the existing `isMentor()` method)
- Modify: `app/Http/Middleware/EnsureTier.php` (add the `'start'` case to the `match`)
- Test: `tests/Unit/UserTierTest.php` (append a new test method)

**Interfaces:**
- Produces: `ClubApplication` model — `$fillable = ['user_id']`, `user(): BelongsTo` (no explicit FK — `user_id` is Eloquent's default inference for a `user()` method, unlike `member_id`/`requester_id` in earlier features). `User::isStart(): bool`. `EnsureTier` accepts `'start'` as a valid `$minTier` value. Tasks 3 and 4 depend on the model; Task 3 depends on the `tier:start` middleware value working.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/ClubApplicationTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\ClubApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_relationship_resolves(): void
    {
        $user = User::factory()->create(['tier' => 'start']);
        $application = ClubApplication::create(['user_id' => $user->id]);

        $this->assertTrue($application->user->is($user));
    }

    public function test_application_is_deleted_when_the_user_is_deleted(): void
    {
        $user = User::factory()->create(['tier' => 'start']);
        $application = ClubApplication::create(['user_id' => $user->id]);

        $user->delete();

        $this->assertDatabaseMissing('club_applications', ['id' => $application->id]);
    }
}
```

Append this method to `tests/Unit/UserTierTest.php`, right after `test_is_mentor_is_true_only_for_mentor_tier`:

```php
    public function test_is_start_is_true_only_for_start_tier(): void
    {
        $this->assertTrue(User::factory()->create(['tier' => 'start'])->isStart());
        $this->assertFalse(User::factory()->create(['tier' => 'club'])->isStart());
        $this->assertFalse(User::factory()->create(['tier' => 'mentor'])->isStart());
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Unit/ClubApplicationTest.php tests/Unit/UserTierTest.php`
Expected: FAIL — `ClubApplication` class and `club_applications` table don't exist yet; `isStart()` doesn't exist on `User` yet.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_08_30_140000_create_club_applications_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_applications');
    }
};
```

`constrained()` with no argument is correct here — `user_id` is Eloquent/Laravel's default inference target (`users` table), the same pattern already used in `create_lesson_feedback_table.php` and `create_encontro_feedback_table.php`. This is different from `member_id`/`mentor_id`/`requester_id`/`target_id` in earlier features, which all needed an explicit table name because their column names don't match the inference.

- [ ] **Step 4: Create the model**

Create `app/Models/ClubApplication.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubApplication extends Model
{
    protected $fillable = [
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 5: Add `isStart()` to `User`**

In `app/Models/User.php`, add this method right after the existing `isMentor()` method:

```php
    public function isStart(): bool
    {
        return $this->tier === 'start';
    }
```

- [ ] **Step 6: Add the `'start'` case to `EnsureTier`**

In `app/Http/Middleware/EnsureTier.php`, change the `match` block from:

```php
        $allowed = match ($minTier) {
            'club' => $user?->hasClubAccess() ?? false,
            'mentor' => $user?->isMentor() ?? false,
            default => false,
        };
```

to:

```php
        $allowed = match ($minTier) {
            'club' => $user?->hasClubAccess() ?? false,
            'mentor' => $user?->isMentor() ?? false,
            'start' => $user?->isStart() ?? false,
            default => false,
        };
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `php artisan test tests/Unit/ClubApplicationTest.php tests/Unit/UserTierTest.php`
Expected: PASS (2 + 5 tests — `UserTierTest` had 4 existing methods, now 5)

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_08_30_140000_create_club_applications_table.php app/Models/ClubApplication.php tests/Unit/ClubApplicationTest.php app/Models/User.php app/Http/Middleware/EnsureTier.php tests/Unit/UserTierTest.php
git commit -m "feat: add ClubApplication model and exact tier=start gate"
```

---

## Task 2: `ClubApplicationApproved` e-mail notification

**Files:**
- Create: `app/Notifications/ClubApplicationApproved.php`
- Test: `tests/Unit/Notifications/ClubApplicationApprovedTest.php`

**Interfaces:**
- Produces: `App\Notifications\ClubApplicationApproved` (a Laravel `Notification`, sent via `$user->notify(new ClubApplicationApproved)`). Task 4's Filament "Aprovar" action depends on this class existing.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Notifications/ClubApplicationApprovedTest.php`:

```php
<?php

namespace Tests\Unit\Notifications;

use App\Models\User;
use App\Notifications\ClubApplicationApproved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubApplicationApprovedTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_is_sent_only_via_mail(): void
    {
        $user = User::factory()->create();

        $this->assertSame(['mail'], (new ClubApplicationApproved)->via($user));
    }

    public function test_mail_message_has_the_expected_subject_and_action_url(): void
    {
        $user = User::factory()->create(['name' => 'Carla Nunes']);

        $mail = (new ClubApplicationApproved)->toMail($user);

        $this->assertSame('Você foi aprovado pro CLUB!', $mail->subject);
        $this->assertSame(route('dashboard'), $mail->actionUrl);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Unit/Notifications/ClubApplicationApprovedTest.php`
Expected: FAIL — `ClubApplicationApproved` class doesn't exist yet.

- [ ] **Step 3: Create the notification**

Create `app/Notifications/ClubApplicationApproved.php`:

```php
<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClubApplicationApproved extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Você foi aprovado pro CLUB!')
            ->greeting("Oi, {$notifiable->name}!")
            ->line('O Douglas analisou sua aplicação e você já faz parte do CLUB.')
            ->line('Sessões 1:1, cofre de documentos, encontros ao vivo e a rede de pessoas do CLUB já estão liberados pra você.')
            ->action('Entrar no CLUB', route('dashboard'))
            ->line('Bem-vindo!');
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Unit/Notifications/ClubApplicationApprovedTest.php`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Notifications/ClubApplicationApproved.php tests/Unit/Notifications/ClubApplicationApprovedTest.php
git commit -m "feat: add the ClubApplicationApproved e-mail notification"
```

---

## Task 3: The `/membros/upgrade` page

**Files:**
- Create: `app/Livewire/Membros/Upgrade.php`
- Create: `resources/views/livewire/membros/upgrade.blade.php`
- Modify: `routes/web.php` (add import + route)
- Modify: `resources/css/app.css` (append new classes)
- Test: `tests/Feature/Membros/UpgradeTest.php`

**Interfaces:**
- Consumes: `ClubApplication` model, `ComputesUserInitials` trait (existing), `tier:start` middleware value (Task 1).
- Produces: route `membros.upgrade`. Task 5 flips this route's nav entry from locked to available.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Membros/UpgradeTest.php`:

```php
<?php

namespace Tests\Feature\Membros;

use App\Livewire\Membros\Upgrade;
use App\Models\ClubApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/membros/upgrade')->assertRedirect(route('login'));
    }

    public function test_club_tier_member_is_denied(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'club']));

        $this->get('/membros/upgrade')->assertRedirect(route('dashboard'));
    }

    public function test_mentor_tier_member_is_denied(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));

        $this->get('/membros/upgrade')->assertRedirect(route('dashboard'));
    }

    public function test_start_tier_member_sees_the_page(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'start']));

        $this->get('/membros/upgrade')
            ->assertOk()
            ->assertSee('Aplicar para o CLUB');
    }

    public function test_apply_creates_a_club_application(): void
    {
        $user = User::factory()->create(['tier' => 'start']);
        $this->actingAs($user);

        Livewire::test(Upgrade::class)->call('apply');

        $this->assertDatabaseHas('club_applications', ['user_id' => $user->id]);
    }

    public function test_applying_twice_does_not_duplicate_the_record(): void
    {
        $user = User::factory()->create(['tier' => 'start']);
        $this->actingAs($user);

        Livewire::test(Upgrade::class)
            ->call('apply')
            ->call('apply');

        $this->assertSame(1, ClubApplication::where('user_id', $user->id)->count());
    }

    public function test_button_shows_application_sent_after_applying(): void
    {
        $user = User::factory()->create(['tier' => 'start']);
        $this->actingAs($user);

        Livewire::test(Upgrade::class)
            ->call('apply')
            ->assertSee('Aplicação enviada');
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/Membros/UpgradeTest.php`
Expected: FAIL — `Upgrade` component and `membros.upgrade` route don't exist yet.

- [ ] **Step 3: Create the Livewire component**

Create `app/Livewire/Membros/Upgrade.php`:

```php
<?php

namespace App\Livewire\Membros;

use App\Livewire\Concerns\ComputesUserInitials;
use App\Models\ClubApplication;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.membros')]
class Upgrade extends Component
{
    use ComputesUserInitials;

    #[Computed]
    public function hasApplied(): bool
    {
        return ClubApplication::query()
            ->where('user_id', Auth::id())
            ->exists();
    }

    public function apply(): void
    {
        if ($this->hasApplied) {
            return;
        }

        ClubApplication::create(['user_id' => Auth::id()]);

        unset($this->hasApplied);
    }

    public function render()
    {
        return view('livewire.membros.upgrade');
    }
}
```

- [ ] **Step 4: Create the page view**

Create `resources/views/livewire/membros/upgrade.blade.php`:

```blade
<div class="min-h-screen text-ink">
    <x-membros.header :initials="$this->userInitials" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="upg rounded-[18px] shadow-[0_1px_2px_rgba(11,11,12,.05),0_10px_28px_rgba(11,11,12,.07)] max-w-3xl">
            <p class="eyebrow">Isso vive no CLUB</p>
            <h2>O Start te dá o conteúdo.<br>O CLUB te dá o Douglas.</h2>
            <p>Sessões individuais, dossiê vivo da sua empresa e pontes curadas com outros empresários. É mentoria de verdade, com poucas cadeiras por ano.</p>
            <ul>
                <li>Sessão 1:1 mensal de 90 minutos com o Douglas, com agenda direta na plataforma</li>
                <li>O fio da mentoria: cada decisão e compromisso registrado, sessão após sessão</li>
                <li>Seu cofre: insights, planos e gravações privadas de cada sessão, organizados para você</li>
                <li>Pontes curadas: o Douglas apresenta você a quem pode destravar seu negócio</li>
                <li>Encontros ao vivo com participação, não só a gravação</li>
            </ul>

            @if ($this->hasApplied)
                <button type="button" disabled
                    class="rounded-full bg-brand text-white text-sm font-semibold px-5 py-2.5 disabled:opacity-40 disabled:cursor-not-allowed">
                    Aplicação enviada — o Douglas responde em até 48h.
                </button>
            @else
                <button type="button" wire:click="apply"
                    class="rounded-full bg-brand text-white text-sm font-semibold px-5 py-2.5 hover:brightness-110">
                    Aplicar para o CLUB
                </button>
            @endif
        </div>
    </div>

    <x-membros.footer />
</div>
```

- [ ] **Step 5: Register the route**

In `routes/web.php`, add the import alphabetically between `Sobre` and the `Illuminate\Support\Facades\Route` import (currently lines 18-19):

```php
use App\Livewire\Membros\Sobre;
use App\Livewire\Membros\Upgrade;
use Illuminate\Support\Facades\Route;
```

Add the route block right after the `membros/frameworks` route (currently lines 71-73), before `membros/mentor`:

```php
Route::get('membros/upgrade', Upgrade::class)
    ->middleware(['auth', 'verified', 'active', 'tier:start'])
    ->name('membros.upgrade');
```

**routes/web.php git hygiene**: this file has a permanently uncommitted trailing line `require __DIR__.'/prototype.php';` belonging to unrelated concurrent work. Before editing, run `git diff routes/web.php` to confirm that line is present and uncommitted. Make the edits with targeted string-replacement `Edit` calls only (one for the import, one for the route block), never a full-file rewrite. After editing, stage with `git add -p routes/web.php`, answering `y` to the hunks containing your import and route block, `n` to any hunk containing the `require __DIR__.'/prototype.php';` line. Verify with `git diff --staged routes/web.php` before committing that only your intended change is staged, and `git diff routes/web.php` after to confirm the require line still shows as unstaged.

- [ ] **Step 6: Append the CSS**

Append to the end of `resources/css/app.css` (after the existing `.tag.ensina { ... }` rule, which is the current last line):

```css
.upg { background: theme('colors.black'); border: none; color: #fff; padding: clamp(28px,5vw,48px);
  position: relative; overflow: hidden; }
.upg::after { content: "CLUB"; position: absolute; right: -20px; bottom: -38px;
  font-family: 'Syne', sans-serif; font-weight: 800; font-size: 160px; color: transparent;
  -webkit-text-stroke: 1px rgba(255,81,0,.35); pointer-events: none; }
.upg .eyebrow { font-size: 11px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase;
  color: theme('colors.brand'); }
.upg h2 { font-size: clamp(26px,4.4vw,40px); line-height: 1.05; margin: 10px 0 14px; max-width: 560px; }
.upg p { color: #B9B4AB; max-width: 520px; }
.upg ul { list-style: none; margin: 20px 0 26px; display: flex; flex-direction: column; gap: 11px;
  max-width: 520px; }
.upg li { display: flex; gap: 12px; font-size: 14.5px; }
.upg li::before { content: ""; width: 8px; height: 8px; border-radius: 50%;
  background: theme('colors.brand'); flex-shrink: 0; margin-top: 7px; }
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/Membros/UpgradeTest.php`
Expected: PASS (7 tests)

- [ ] **Step 8: Commit**

```bash
git add app/Livewire/Membros/Upgrade.php resources/views/livewire/membros/upgrade.blade.php resources/css/app.css tests/Feature/Membros/UpgradeTest.php
git add -p routes/web.php
git commit -m "feat: add the real upgrade Start to CLUB page"
```

---

## Task 4: Admin (Filament) — `ClubApplicationResource`

**Files:**
- Create: `app/Filament/Resources/ClubApplications/ClubApplicationResource.php`
- Create: `app/Filament/Resources/ClubApplications/Tables/ClubApplicationsTable.php`
- Create: `app/Filament/Resources/ClubApplications/Pages/ListClubApplications.php`
- Test: `tests/Feature/Admin/ClubApplicationResourceTest.php`

**Interfaces:**
- Consumes: `ClubApplication` model, `user()` relation (Task 1); `ClubApplicationApproved` notification (Task 2).
- Produces: nothing later tasks depend on — this is a leaf feature.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Admin/ClubApplicationResourceTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ClubApplications\Pages\ListClubApplications;
use App\Models\ClubApplication;
use App\Models\User;
use App\Notifications\ClubApplicationApproved;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ClubApplicationResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function application(): ClubApplication
    {
        $applicant = User::factory()->create([
            'tier' => 'start', 'name' => 'Carla Nunes', 'email' => 'carla@example.com',
        ]);

        return ClubApplication::create(['user_id' => $applicant->id]);
    }

    public function test_non_admin_cannot_access_the_list(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->get('/admin/club-applications')->assertForbidden();
    }

    public function test_admin_can_see_an_existing_application_in_the_list(): void
    {
        $application = $this->application();

        $this->actingAs($this->admin());

        Livewire::test(ListClubApplications::class)
            ->assertCanSeeTableRecords([$application])
            ->assertSee('Carla Nunes')
            ->assertSee('carla@example.com');
    }

    public function test_approving_upgrades_the_user_notifies_them_and_removes_the_application(): void
    {
        Notification::fake();

        $application = $this->application();
        $applicant = $application->user;

        $this->actingAs($this->admin());

        Livewire::test(ListClubApplications::class)
            ->callTableAction('approve', record: $application);

        $this->assertSame('club', $applicant->fresh()->tier);
        $this->assertDatabaseMissing('club_applications', ['id' => $application->id]);
        Notification::assertSentTo($applicant, ClubApplicationApproved::class);
    }

    public function test_declining_only_deletes_the_application(): void
    {
        $application = $this->application();
        $applicant = $application->user;

        $this->actingAs($this->admin());

        Livewire::test(ListClubApplications::class)
            ->callTableAction(DeleteAction::class, record: $application);

        $this->assertDatabaseMissing('club_applications', ['id' => $application->id]);
        $this->assertSame('start', $applicant->fresh()->tier);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/Admin/ClubApplicationResourceTest.php`
Expected: FAIL — `ClubApplicationResource` and `/admin/club-applications` don't exist yet.

- [ ] **Step 3: Create the resource**

Create `app/Filament/Resources/ClubApplications/ClubApplicationResource.php`:

```php
<?php

namespace App\Filament\Resources\ClubApplications;

use App\Filament\Resources\ClubApplications\Pages\ListClubApplications;
use App\Filament\Resources\ClubApplications\Tables\ClubApplicationsTable;
use App\Models\ClubApplication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ClubApplicationResource extends Resource
{
    protected static ?string $model = ClubApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?string $recordTitleAttribute = 'id';

    public static function table(Table $table): Table
    {
        return ClubApplicationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClubApplications::route('/'),
        ];
    }
}
```

Create `app/Filament/Resources/ClubApplications/Tables/ClubApplicationsTable.php`:

```php
<?php

namespace App\Filament\Resources\ClubApplications\Tables;

use App\Models\ClubApplication;
use App\Notifications\ClubApplicationApproved;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClubApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Quem aplicou')
                    ->searchable(),
                TextColumn::make('user.email')
                    ->label('E-mail')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Quando')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('approve')
                    ->label('Aprovar')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (ClubApplication $record) {
                        $record->user->update(['tier' => 'club']);
                        $record->user->notify(new ClubApplicationApproved);
                        $record->delete();
                    }),
                DeleteAction::make()
                    ->label('Recusar'),
            ]);
    }
}
```

Create `app/Filament/Resources/ClubApplications/Pages/ListClubApplications.php`:

```php
<?php

namespace App\Filament\Resources\ClubApplications\Pages;

use App\Filament\Resources\ClubApplications\ClubApplicationResource;
use Filament\Resources\Pages\ListRecords;

class ListClubApplications extends ListRecords
{
    protected static string $resource = ClubApplicationResource::class;
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/ClubApplicationResourceTest.php`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/ClubApplications tests/Feature/Admin/ClubApplicationResourceTest.php
git commit -m "feat: add Filament admin resource for ClubApplication"
```

---

## Task 5: Unlock the "Sessão 1:1" nav tab

**Files:**
- Modify: `app/Support/PersonaNavigation.php:17`
- Modify: `tests/Unit/Support/PersonaNavigationTest.php`
- Modify: `tests/Feature/Membros/PersonaNavigationTest.php`

**Interfaces:**
- Consumes: route `membros.upgrade` (Task 3).
- Produces: nothing later — this is the final task.

- [ ] **Step 1: Update the failing-first tests**

In `tests/Unit/Support/PersonaNavigationTest.php`, replace `test_start_tier_has_three_available_tabs_and_one_locked_tab` with:

```php
    public function test_start_tier_has_four_available_tabs(): void
    {
        $tabs = (new PersonaNavigation)->tabs('start');

        $this->assertCount(4, $tabs);
        $this->assertSame(['Início', 'Aulas', 'Frameworks', 'Sessão 1:1'], array_column($tabs, 'label'));
        $this->assertSame([true, true, true, true], array_column($tabs, 'available'));
    }
```

In `tests/Feature/Membros/PersonaNavigationTest.php`, replace `test_start_tier_shows_inicio_aulas_and_frameworks_as_links_and_the_rest_locked` with:

```php
    public function test_start_tier_shows_inicio_aulas_frameworks_and_upgrade_as_links(): void
    {
        $user = User::factory()->create(['tier' => 'start']);
        $this->actingAs($user);

        $html = $this->get('/membros')->assertOk()->getContent();

        foreach ([
            ['href' => 'http://localhost/membros', 'label' => 'Início'],
            ['href' => 'http://localhost/membros/aulas', 'label' => 'Aulas'],
            ['href' => 'http://localhost/membros/frameworks', 'label' => 'Frameworks'],
            ['href' => 'http://localhost/membros/upgrade', 'label' => 'Sessão 1:1'],
        ] as $link) {
            $this->assertMatchesRegularExpression(
                '#<a[^>]*href="'.preg_quote($link['href'], '#').'"[^>]*>\s*'.preg_quote($link['label'], '#').'\s*</a>#s',
                $html,
            );
        }
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Unit/Support/PersonaNavigationTest.php tests/Feature/Membros/PersonaNavigationTest.php`
Expected: FAIL — `Sessão 1:1` is still locked (`available: false`), so the new assertions don't match.

- [ ] **Step 3: Flip the flag**

In `app/Support/PersonaNavigation.php`, change line 17 from:

```php
                ['label' => 'Sessão 1:1', 'route' => 'membros.upgrade', 'available' => false],
```

to:

```php
                ['label' => 'Sessão 1:1', 'route' => 'membros.upgrade', 'available' => true],
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Unit/Support/PersonaNavigationTest.php tests/Feature/Membros/PersonaNavigationTest.php`
Expected: PASS

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`
Expected: PASS, no regressions.

- [ ] **Step 6: Commit**

```bash
git add app/Support/PersonaNavigation.php tests/Unit/Support/PersonaNavigationTest.php tests/Feature/Membros/PersonaNavigationTest.php
git commit -m "feat: unlock the Sessão 1:1 nav tab now that the upgrade page exists"
```
