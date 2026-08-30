# Agenda de Sessões 1:1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build recurring weekly mentor availability + a 90-minute session booking flow for CLUB members, closing GitHub issue #20.

**Architecture:** Two small tables (`mentor_availabilities` — recurring weekly blocks, no stored open/closed flag, a row's existence IS "open"; `mentor_sessions` — booked sessions, cancelled via a `cancelled_at` timestamp rather than deletion). A pure slot-generation action (`DetermineAvailableSlots`) expands the recurring blocks into concrete bookable datetimes for the next 14 days, filtering out anything already booked or inside the 24h minimum-notice window. Two Livewire pages sit on top: `Agenda` (member booking, `App\Livewire\Membros` namespace) and `Disponibilidade` (mentor's own availability CRUD, same namespace — mirrors the existing `MentorPlaceholder` convention of mentor pages living under `Membros`, not a separate `Mentor` namespace). A read-only-ish Filament resource gives the mentor/admin visibility into booked sessions.

**Tech Stack:** Laravel 13, Livewire 3, Filament 4, Tailwind CSS v3, PHPUnit (`php artisan test`), SQLite (`:memory:` in tests).

**Spec:** `docs/superpowers/specs/2026-08-29-agenda-sessoes-1-1-design.md`

## Global Constraints

- Availability is recurring weekly blocks (day-of-week + start/end time), NOT specific dates. A `MentorAvailability` row's existence means that block is open; deleting it closes the block — there is no boolean toggle field.
- Session length is fixed at 90 minutes. Booking window is the next 14 days. Minimum notice (both for booking and for cancelling) is 24 hours.
- Single mentor assumption: no mentor-picker UI anywhere. Code resolves the mentor via `User::query()->where('tier', 'mentor')->first()`.
- A member can have at most ONE active (non-cancelled, future) session at a time. While one exists, `/membros/agenda` shows that session (with a Cancelar button) instead of the booking calendar — it never shows both.
- No DB unique constraint on `(mentor_id, scheduled_at)` — a cancelled session must free that slot for someone else, and a naive unique index would collide with the old cancelled row. Double-booking protection is application-level only (a existence check inside a `DB::transaction()`), not airtight against real concurrency — this is accepted, not a bug to fix.
- No notification/reminder of any kind in this plan — tracked separately in GitHub issue #27.
- `App\Livewire\Membros\Disponibilidade` — NOT a separate `App\Livewire\Mentor\*` namespace. Route path prefix `membros/mentor/...`, route name prefix `mentor.` — mirrors the existing `MentorPlaceholder` (`membros/mentor` → `mentor.placeholder`).
- `mentor.disp` and `membros.agenda` are route names ALREADY referenced by `App\Support\PersonaNavigation` (currently `available: false`) — Task 7 flips them to `true` once the routes exist.

---

## Task 1: `MentorAvailability` + `MentorSession` models

**Files:**
- Create: `database/migrations/2026_08_29_190000_create_mentor_availabilities_table.php`
- Create: `database/migrations/2026_08_29_200000_create_mentor_sessions_table.php`
- Create: `app/Models/MentorAvailability.php`
- Create: `app/Models/MentorSession.php`
- Test: `tests/Unit/MentorAvailabilityTest.php`
- Test: `tests/Unit/MentorSessionTest.php`

**Interfaces:**
- Produces: `MentorAvailability` (`$fillable`: `mentor_id`, `day_of_week`, `start_time`, `end_time`; casts `start_time`/`end_time` as `datetime:H:i`; `mentor(): BelongsTo`). `MentorSession` (`$fillable`: `mentor_id`, `member_id`, `scheduled_at`, `cancelled_at`; casts `scheduled_at`/`cancelled_at` as `datetime`; `mentor(): BelongsTo`, `member(): BelongsTo`; `isCancelled(): bool`; `isUpcoming(): bool`). Tasks 2-6 all depend on these exact names.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/MentorAvailabilityTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\MentorAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentorAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_mentor_relationship_resolves(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $block = MentorAvailability::create([
            'mentor_id' => $mentor->id, 'day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '12:00',
        ]);

        $this->assertTrue($block->mentor->is($mentor));
    }

    public function test_start_and_end_time_are_cast_to_hi_format(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $block = MentorAvailability::create([
            'mentor_id' => $mentor->id, 'day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '12:00',
        ]);

        $this->assertSame('09:00', $block->start_time->format('H:i'));
        $this->assertSame('12:00', $block->end_time->format('H:i'));
    }

    public function test_block_is_deleted_when_the_mentor_is_deleted(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $block = MentorAvailability::create([
            'mentor_id' => $mentor->id, 'day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '12:00',
        ]);

        $mentor->delete();

        $this->assertDatabaseMissing('mentor_availabilities', ['id' => $block->id]);
    }
}
```

Create `tests/Unit/MentorSessionTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\MentorSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentorSessionTest extends TestCase
{
    use RefreshDatabase;

    private function session(array $overrides = []): MentorSession
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $member = User::factory()->create(['tier' => 'club']);

        return MentorSession::create(array_merge([
            'mentor_id' => $mentor->id, 'member_id' => $member->id,
            'scheduled_at' => now()->addDays(3),
        ], $overrides));
    }

    public function test_mentor_and_member_relationships_resolve(): void
    {
        $session = $this->session();

        $this->assertTrue($session->mentor->is(User::find($session->mentor_id)));
        $this->assertTrue($session->member->is(User::find($session->member_id)));
    }

    public function test_is_cancelled_is_true_when_cancelled_at_is_set(): void
    {
        $session = $this->session(['cancelled_at' => now()]);

        $this->assertTrue($session->isCancelled());
    }

    public function test_is_cancelled_is_false_when_cancelled_at_is_null(): void
    {
        $session = $this->session();

        $this->assertFalse($session->isCancelled());
    }

    public function test_is_upcoming_is_true_for_a_future_non_cancelled_session(): void
    {
        $session = $this->session(['scheduled_at' => now()->addDay()]);

        $this->assertTrue($session->isUpcoming());
    }

    public function test_is_upcoming_is_false_for_a_cancelled_session(): void
    {
        $session = $this->session(['scheduled_at' => now()->addDay(), 'cancelled_at' => now()]);

        $this->assertFalse($session->isUpcoming());
    }

    public function test_is_upcoming_is_false_for_a_past_session(): void
    {
        $session = $this->session(['scheduled_at' => now()->subDay()]);

        $this->assertFalse($session->isUpcoming());
    }

    public function test_session_is_deleted_when_the_member_is_deleted(): void
    {
        $session = $this->session();
        $member = $session->member;

        $member->delete();

        $this->assertDatabaseMissing('mentor_sessions', ['id' => $session->id]);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Unit/MentorAvailabilityTest.php tests/Unit/MentorSessionTest.php`
Expected: FAIL — classes and tables don't exist yet.

- [ ] **Step 3: Create the migrations**

Create `database/migrations/2026_08_29_190000_create_mentor_availabilities_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentor_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mentor_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentor_availabilities');
    }
};
```

Create `database/migrations/2026_08_29_200000_create_mentor_sessions_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentor_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mentor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('scheduled_at');
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentor_sessions');
    }
};
```

Both FKs point at `users` explicitly via `constrained('users')` — the column names (`mentor_id`,
`member_id`) don't match a table name Laravel could infer automatically anyway, so this is required,
not just defensive.

- [ ] **Step 4: Create the models**

Create `app/Models/MentorAvailability.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentorAvailability extends Model
{
    use HasFactory;

    protected $fillable = [
        'mentor_id',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }
}
```

Create `app/Models/MentorSession.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentorSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'mentor_id',
        'member_id',
        'scheduled_at',
        'cancelled_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function isCancelled(): bool
    {
        return filled($this->cancelled_at);
    }

    public function isUpcoming(): bool
    {
        return ! $this->isCancelled() && $this->scheduled_at->isFuture();
    }
}
```

The explicit `'mentor_id'`/`'member_id'` second argument on each `belongsTo(User::class, ...)` is
required — without it, Eloquent would look for `user_id`, which doesn't exist on either table.

- [ ] **Step 5: Run migrations and tests**

Run: `php artisan migrate`
Run: `php artisan test tests/Unit/MentorAvailabilityTest.php tests/Unit/MentorSessionTest.php`
Expected: PASS (3 + 6 = 9 tests).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_29_190000_create_mentor_availabilities_table.php database/migrations/2026_08_29_200000_create_mentor_sessions_table.php app/Models/MentorAvailability.php app/Models/MentorSession.php tests/Unit/MentorAvailabilityTest.php tests/Unit/MentorSessionTest.php
git commit -m "feat: add MentorAvailability and MentorSession models"
```

---

## Task 2: `DetermineAvailableSlots` action

**Files:**
- Create: `app/Actions/DetermineAvailableSlots.php`
- Test: `tests/Unit/DetermineAvailableSlotsTest.php`

**Interfaces:**
- Consumes: `MentorAvailability`, `MentorSession` (Task 1).
- Produces: `App\Actions\DetermineAvailableSlots::handle(User $mentor): \Illuminate\Support\Collection` — a flat, chronologically-sorted collection of `Carbon` instances, each the start time of one bookable 90-minute slot. Task 4's `Agenda::availableSlots()` computed property calls this by this exact name.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/DetermineAvailableSlotsTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Actions\DetermineAvailableSlots;
use App\Models\MentorAvailability;
use App\Models\MentorSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DetermineAvailableSlotsTest extends TestCase
{
    use RefreshDatabase;

    private function mentor(): User
    {
        return User::factory()->create(['tier' => 'mentor']);
    }

    public function test_a_three_hour_block_yields_exactly_two_ninety_minute_slots(): void
    {
        $mentor = $this->mentor();
        $targetDate = now()->addDays(5)->startOfDay();

        MentorAvailability::create([
            'mentor_id' => $mentor->id, 'day_of_week' => $targetDate->dayOfWeek,
            'start_time' => '09:00', 'end_time' => '12:00',
        ]);

        $slots = (new DetermineAvailableSlots)->handle($mentor)
            ->filter(fn ($slot) => $slot->isSameDay($targetDate));

        $this->assertCount(2, $slots);
        $this->assertSame('09:00', $slots->first()->format('H:i'));
        $this->assertSame('10:30', $slots->last()->format('H:i'));
    }

    public function test_a_block_that_does_not_fit_a_whole_multiple_of_ninety_minutes_has_no_partial_slot(): void
    {
        $mentor = $this->mentor();
        $targetDate = now()->addDays(5)->startOfDay();

        // 100 minutes: one full 90-min slot fits (09:00-10:30), the remaining 10 minutes don't.
        MentorAvailability::create([
            'mentor_id' => $mentor->id, 'day_of_week' => $targetDate->dayOfWeek,
            'start_time' => '09:00', 'end_time' => '10:40',
        ]);

        $slots = (new DetermineAvailableSlots)->handle($mentor)
            ->filter(fn ($slot) => $slot->isSameDay($targetDate));

        $this->assertCount(1, $slots);
        $this->assertSame('09:00', $slots->first()->format('H:i'));
    }

    public function test_slots_inside_the_24_hour_minimum_notice_window_are_excluded(): void
    {
        $mentor = $this->mentor();
        $today = now()->dayOfWeek;

        // A block covering right now through the next few hours today — entirely inside the
        // 24h notice window, so it must produce zero slots for today.
        MentorAvailability::create([
            'mentor_id' => $mentor->id, 'day_of_week' => $today,
            'start_time' => '00:00', 'end_time' => '23:59',
        ]);

        $slots = (new DetermineAvailableSlots)->handle($mentor)
            ->filter(fn ($slot) => $slot->isToday());

        $this->assertCount(0, $slots);
    }

    public function test_an_already_booked_slot_is_excluded(): void
    {
        $mentor = $this->mentor();
        $member = User::factory()->create(['tier' => 'club']);
        $targetDate = now()->addDays(5)->startOfDay();

        MentorAvailability::create([
            'mentor_id' => $mentor->id, 'day_of_week' => $targetDate->dayOfWeek,
            'start_time' => '09:00', 'end_time' => '10:30',
        ]);

        MentorSession::create([
            'mentor_id' => $mentor->id, 'member_id' => $member->id,
            'scheduled_at' => $targetDate->copy()->setTime(9, 0),
        ]);

        $slots = (new DetermineAvailableSlots)->handle($mentor)
            ->filter(fn ($slot) => $slot->isSameDay($targetDate));

        $this->assertCount(0, $slots);
    }

    public function test_a_cancelled_booking_frees_the_slot_back_up(): void
    {
        $mentor = $this->mentor();
        $member = User::factory()->create(['tier' => 'club']);
        $targetDate = now()->addDays(5)->startOfDay();

        MentorAvailability::create([
            'mentor_id' => $mentor->id, 'day_of_week' => $targetDate->dayOfWeek,
            'start_time' => '09:00', 'end_time' => '10:30',
        ]);

        MentorSession::create([
            'mentor_id' => $mentor->id, 'member_id' => $member->id,
            'scheduled_at' => $targetDate->copy()->setTime(9, 0),
            'cancelled_at' => now(),
        ]);

        $slots = (new DetermineAvailableSlots)->handle($mentor)
            ->filter(fn ($slot) => $slot->isSameDay($targetDate));

        $this->assertCount(1, $slots);
    }

    public function test_slots_beyond_the_14_day_window_are_excluded(): void
    {
        $mentor = $this->mentor();
        $farDate = now()->addDays(20)->startOfDay();

        MentorAvailability::create([
            'mentor_id' => $mentor->id, 'day_of_week' => $farDate->dayOfWeek,
            'start_time' => '09:00', 'end_time' => '10:30',
        ]);

        $slots = (new DetermineAvailableSlots)->handle($mentor)
            ->filter(fn ($slot) => $slot->isSameDay($farDate));

        $this->assertCount(0, $slots);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Unit/DetermineAvailableSlotsTest.php`
Expected: FAIL — `App\Actions\DetermineAvailableSlots` doesn't exist yet.

- [ ] **Step 3: Create the action**

Create `app/Actions/DetermineAvailableSlots.php`:

```php
<?php

namespace App\Actions;

use App\Models\MentorAvailability;
use App\Models\MentorSession;
use App\Models\User;
use Illuminate\Support\Collection;

class DetermineAvailableSlots
{
    private const SESSION_MINUTES = 90;
    private const BOOKING_WINDOW_DAYS = 14;
    private const MIN_NOTICE_HOURS = 24;

    /** @return Collection<int, \Carbon\Carbon> */
    public function handle(User $mentor): Collection
    {
        $availabilities = MentorAvailability::query()->where('mentor_id', $mentor->id)->get();

        $bookedSlots = MentorSession::query()
            ->where('mentor_id', $mentor->id)
            ->whereNull('cancelled_at')
            ->where('scheduled_at', '>=', now())
            ->pluck('scheduled_at')
            ->map(fn ($dt) => $dt->format('Y-m-d H:i:s'))
            ->all();

        $earliestBookable = now()->addHours(self::MIN_NOTICE_HOURS);
        $slots = collect();

        for ($day = 0; $day < self::BOOKING_WINDOW_DAYS; $day++) {
            $date = today()->addDays($day);

            foreach ($availabilities->where('day_of_week', $date->dayOfWeek) as $availability) {
                $slotStart = $date->copy()->setTimeFromTimeString($availability->start_time->format('H:i'));
                $blockEnd = $date->copy()->setTimeFromTimeString($availability->end_time->format('H:i'));

                while ($slotStart->copy()->addMinutes(self::SESSION_MINUTES)->lte($blockEnd)) {
                    if ($slotStart->gte($earliestBookable)
                        && ! in_array($slotStart->format('Y-m-d H:i:s'), $bookedSlots, true)) {
                        $slots->push($slotStart->copy());
                    }

                    $slotStart->addMinutes(self::SESSION_MINUTES);
                }
            }
        }

        return $slots->sortBy(fn ($slot) => $slot->timestamp)->values();
    }
}
```

- [ ] **Step 4: Run the tests**

Run: `php artisan test tests/Unit/DetermineAvailableSlotsTest.php`
Expected: PASS (all 6 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Actions/DetermineAvailableSlots.php tests/Unit/DetermineAvailableSlotsTest.php
git commit -m "feat: add DetermineAvailableSlots action"
```

---

## Task 3: `BookMentorSession` + `CancelMentorSession` actions

**Files:**
- Create: `app/Actions/BookMentorSession.php`
- Create: `app/Actions/CancelMentorSession.php`
- Test: `tests/Unit/BookMentorSessionTest.php`
- Test: `tests/Unit/CancelMentorSessionTest.php`

**Interfaces:**
- Consumes: `MentorSession` (Task 1).
- Produces: `App\Actions\BookMentorSession::handle(User $mentor, User $member, \Carbon\Carbon $scheduledAt): ?MentorSession` (returns `null`, creates nothing, when the slot is already booked and not cancelled). `App\Actions\CancelMentorSession::handle(MentorSession $session): void` (sets `cancelled_at`). Task 4's `Agenda` component calls both by these exact names.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/BookMentorSessionTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Actions\BookMentorSession;
use App\Models\MentorSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookMentorSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_session(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $member = User::factory()->create(['tier' => 'club']);
        $scheduledAt = now()->addDays(3)->setTime(9, 0);

        $session = (new BookMentorSession)->handle($mentor, $member, $scheduledAt);

        $this->assertNotNull($session);
        $this->assertDatabaseHas('mentor_sessions', [
            'mentor_id' => $mentor->id, 'member_id' => $member->id,
        ]);
    }

    public function test_returns_null_and_creates_nothing_when_the_slot_is_already_booked(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $firstMember = User::factory()->create(['tier' => 'club']);
        $secondMember = User::factory()->create(['tier' => 'club']);
        $scheduledAt = now()->addDays(3)->setTime(9, 0);

        (new BookMentorSession)->handle($mentor, $firstMember, $scheduledAt);
        $result = (new BookMentorSession)->handle($mentor, $secondMember, $scheduledAt);

        $this->assertNull($result);
        $this->assertSame(1, MentorSession::query()->where('mentor_id', $mentor->id)->count());
    }

    public function test_allows_rebooking_a_slot_whose_previous_session_was_cancelled(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $firstMember = User::factory()->create(['tier' => 'club']);
        $secondMember = User::factory()->create(['tier' => 'club']);
        $scheduledAt = now()->addDays(3)->setTime(9, 0);

        $original = (new BookMentorSession)->handle($mentor, $firstMember, $scheduledAt);
        $original->update(['cancelled_at' => now()]);

        $result = (new BookMentorSession)->handle($mentor, $secondMember, $scheduledAt);

        $this->assertNotNull($result);
        $this->assertSame($secondMember->id, $result->member_id);
    }
}
```

Create `tests/Unit/CancelMentorSessionTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Actions\CancelMentorSession;
use App\Models\MentorSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelMentorSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sets_cancelled_at(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $member = User::factory()->create(['tier' => 'club']);
        $session = MentorSession::create([
            'mentor_id' => $mentor->id, 'member_id' => $member->id,
            'scheduled_at' => now()->addDays(3),
        ]);

        (new CancelMentorSession)->handle($session);

        $this->assertNotNull($session->fresh()->cancelled_at);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Unit/BookMentorSessionTest.php tests/Unit/CancelMentorSessionTest.php`
Expected: FAIL — the action classes don't exist yet.

- [ ] **Step 3: Create the actions**

Create `app/Actions/BookMentorSession.php`:

```php
<?php

namespace App\Actions;

use App\Models\MentorSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BookMentorSession
{
    public function handle(User $mentor, User $member, Carbon $scheduledAt): ?MentorSession
    {
        return DB::transaction(function () use ($mentor, $member, $scheduledAt) {
            $alreadyBooked = MentorSession::query()
                ->where('mentor_id', $mentor->id)
                ->where('scheduled_at', $scheduledAt)
                ->whereNull('cancelled_at')
                ->exists();

            if ($alreadyBooked) {
                return null;
            }

            return MentorSession::create([
                'mentor_id' => $mentor->id,
                'member_id' => $member->id,
                'scheduled_at' => $scheduledAt,
            ]);
        });
    }
}
```

Create `app/Actions/CancelMentorSession.php`:

```php
<?php

namespace App\Actions;

use App\Models\MentorSession;

class CancelMentorSession
{
    public function handle(MentorSession $session): void
    {
        $session->update(['cancelled_at' => now()]);
    }
}
```

- [ ] **Step 4: Run the tests**

Run: `php artisan test tests/Unit/BookMentorSessionTest.php tests/Unit/CancelMentorSessionTest.php`
Expected: PASS (3 + 1 = 4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Actions/BookMentorSession.php app/Actions/CancelMentorSession.php tests/Unit/BookMentorSessionTest.php tests/Unit/CancelMentorSessionTest.php
git commit -m "feat: add BookMentorSession and CancelMentorSession actions"
```

---

## Task 4: `/membros/agenda` — member booking page

**Files:**
- Create: `app/Livewire/Membros/Agenda.php`
- Create: `resources/views/livewire/membros/agenda.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Livewire/Membros/AgendaTest.php`

**Interfaces:**
- Consumes: `DetermineAvailableSlots` (Task 2), `BookMentorSession`, `CancelMentorSession` (Task 3), `MentorSession` (Task 1).
- Produces: named route `membros.agenda` — Task 7's nav flip depends on it existing.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Livewire/Membros/AgendaTest.php`:

```php
<?php

namespace Tests\Feature\Livewire\Membros;

use App\Livewire\Membros\Agenda;
use App\Models\MentorAvailability;
use App\Models\MentorSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AgendaTest extends TestCase
{
    use RefreshDatabase;

    private function mentor(): User
    {
        return User::factory()->create(['tier' => 'mentor']);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/membros/agenda')->assertRedirect('/login');
    }

    public function test_start_tier_is_redirected_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'start']));

        $this->get('/membros/agenda')->assertRedirect('/membros');
    }

    public function test_club_member_without_a_session_sees_the_booking_calendar(): void
    {
        $mentor = $this->mentor();
        $targetDate = now()->addDays(5)->startOfDay();
        MentorAvailability::create([
            'mentor_id' => $mentor->id, 'day_of_week' => $targetDate->dayOfWeek,
            'start_time' => '09:00', 'end_time' => '10:30',
        ]);

        $this->actingAs(User::factory()->create(['tier' => 'club']));

        Livewire::test(Agenda::class)
            ->assertSee($targetDate->format('d'))
            ->assertDontSee('Cancelar sessão');
    }

    public function test_club_member_with_an_upcoming_session_sees_it_instead_of_the_calendar(): void
    {
        $mentor = $this->mentor();
        $member = User::factory()->create(['tier' => 'club']);
        MentorSession::create([
            'mentor_id' => $mentor->id, 'member_id' => $member->id,
            'scheduled_at' => now()->addDays(3)->setTime(9, 0),
        ]);

        $this->actingAs($member);

        Livewire::test(Agenda::class)
            ->assertSee('Cancelar sessão');
    }

    public function test_booking_a_valid_slot_creates_the_session(): void
    {
        $mentor = $this->mentor();
        $targetDate = now()->addDays(5)->startOfDay();
        MentorAvailability::create([
            'mentor_id' => $mentor->id, 'day_of_week' => $targetDate->dayOfWeek,
            'start_time' => '09:00', 'end_time' => '10:30',
        ]);

        $member = User::factory()->create(['tier' => 'club']);
        $this->actingAs($member);

        $slot = $targetDate->copy()->setTime(9, 0);

        Livewire::test(Agenda::class)
            ->call('bookSlot', $slot->toIso8601String())
            ->assertSee('Cancelar sessão');

        $this->assertDatabaseHas('mentor_sessions', [
            'mentor_id' => $mentor->id, 'member_id' => $member->id,
        ]);
    }

    public function test_booking_a_slot_not_in_the_available_list_is_ignored(): void
    {
        $mentor = $this->mentor();
        // No availability created at all — every slot is invalid.
        $member = User::factory()->create(['tier' => 'club']);
        $this->actingAs($member);

        $bogusSlot = now()->addDays(5)->setTime(9, 0);

        Livewire::test(Agenda::class)
            ->call('bookSlot', $bogusSlot->toIso8601String());

        $this->assertDatabaseMissing('mentor_sessions', ['member_id' => $member->id]);
    }

    public function test_cancelling_more_than_24_hours_ahead_cancels_the_session(): void
    {
        $mentor = $this->mentor();
        $member = User::factory()->create(['tier' => 'club']);
        $session = MentorSession::create([
            'mentor_id' => $mentor->id, 'member_id' => $member->id,
            'scheduled_at' => now()->addDays(3),
        ]);

        $this->actingAs($member);

        Livewire::test(Agenda::class)->call('cancelSession');

        $this->assertNotNull($session->fresh()->cancelled_at);
    }

    public function test_cancelling_inside_24_hours_is_ignored(): void
    {
        $mentor = $this->mentor();
        $member = User::factory()->create(['tier' => 'club']);
        $session = MentorSession::create([
            'mentor_id' => $mentor->id, 'member_id' => $member->id,
            'scheduled_at' => now()->addHours(12),
        ]);

        $this->actingAs($member);

        Livewire::test(Agenda::class)->call('cancelSession');

        $this->assertNull($session->fresh()->cancelled_at);
    }

    public function test_cannot_cancel_another_members_session(): void
    {
        $mentor = $this->mentor();
        $owner = User::factory()->create(['tier' => 'club']);
        $otherMember = User::factory()->create(['tier' => 'club']);
        $session = MentorSession::create([
            'mentor_id' => $mentor->id, 'member_id' => $owner->id,
            'scheduled_at' => now()->addDays(3),
        ]);

        $this->actingAs($otherMember);

        Livewire::test(Agenda::class)->call('cancelSession');

        $this->assertNull($session->fresh()->cancelled_at);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Livewire/Membros/AgendaTest.php`
Expected: FAIL — route `membros.agenda` / class `App\Livewire\Membros\Agenda` don't exist.

- [ ] **Step 3: Create the Livewire component**

Create `app/Livewire/Membros/Agenda.php`:

```php
<?php

namespace App\Livewire\Membros;

use App\Actions\BookMentorSession;
use App\Actions\CancelMentorSession;
use App\Actions\DetermineAvailableSlots;
use App\Livewire\Concerns\ComputesUserInitials;
use App\Models\MentorSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.membros')]
class Agenda extends Component
{
    use ComputesUserInitials;

    public ?string $selectedDate = null;

    #[Computed]
    public function mentor(): ?User
    {
        return User::query()->where('tier', 'mentor')->first();
    }

    #[Computed]
    public function upcomingSession(): ?MentorSession
    {
        return MentorSession::query()
            ->where('member_id', Auth::id())
            ->whereNull('cancelled_at')
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at')
            ->first();
    }

    #[Computed]
    public function availableSlots(): Collection
    {
        if (! $this->mentor || $this->upcomingSession) {
            return collect();
        }

        return (new DetermineAvailableSlots)->handle($this->mentor);
    }

    public function selectDate(string $date): void
    {
        $this->selectedDate = $date;
    }

    public function bookSlot(string $slot, BookMentorSession $action): void
    {
        if (! $this->mentor || $this->upcomingSession) {
            return;
        }

        $scheduledAt = Carbon::parse($slot);

        if (! $this->availableSlots->contains(fn ($s) => $s->equalTo($scheduledAt))) {
            return;
        }

        $session = $action->handle($this->mentor, Auth::user(), $scheduledAt);

        if (! $session) {
            session()->flash('agenda-error', 'Esse horário acabou de ser preenchido. Escolha outro.');
        }

        unset($this->availableSlots, $this->upcomingSession);
    }

    public function cancelSession(CancelMentorSession $action): void
    {
        $session = $this->upcomingSession;

        if (! $session || $session->member_id !== Auth::id()) {
            return;
        }

        if ($session->scheduled_at->lt(now()->addHours(24))) {
            return;
        }

        $action->handle($session);

        unset($this->upcomingSession, $this->availableSlots);
    }

    public function render()
    {
        return view('livewire.membros.agenda');
    }
}
```

`bookSlot`/`cancelSession` take their action class as a plain, required, type-hinted parameter —
same convention already used throughout this codebase (e.g. `TracksLessonProgress::markCompleted(int $lessonId, MarkLessonAsCompleted $action)`).
Livewire resolves that extra parameter from the container automatically, so
`Livewire::test(Agenda::class)->call('bookSlot', $slot->toIso8601String())` (passing only the one
string argument) works exactly like the existing `->call('markCompleted', $lesson->id)` calls
elsewhere in this test suite — no need for a nullable default or manual `new BookMentorSession`.

- [ ] **Step 4: Create the view**

Create `resources/views/livewire/membros/agenda.blade.php`:

```blade
<div class="min-h-screen text-ink">
    <x-membros.header :initials="$this->userInitials" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="mb-8">
            <h1 class="text-[clamp(26px,4vw,38px)] leading-[1.05] font-display font-extrabold tracking-[-0.015em] text-black">
                Minha sessão
            </h1>
            <p class="mt-2 max-w-xl text-stone">
                Escolha um horário dentro do que o Douglas deixou disponível. Sessão 1:1 de 90 minutos.
            </p>
        </div>

        @if (session('agenda-error'))
            <p class="mb-4 text-sm text-brand">{{ session('agenda-error') }}</p>
        @endif

        @if ($this->upcomingSession)
            <div class="max-w-md rounded-[18px] border border-sand bg-card p-6">
                <p class="text-xs font-bold uppercase tracking-widest text-stone mb-1">Sua próxima sessão</p>
                <p class="font-display text-lg">{{ $this->upcomingSession->scheduled_at->format('d/m/Y \à\s H:i') }}</p>
                <p class="text-sm text-stone mt-1">Sessão 1:1 · 90 minutos</p>

                @php
                    $cancellable = $this->upcomingSession->scheduled_at->gte(now()->addHours(24));
                @endphp

                @if ($cancellable)
                    <button type="button" wire:click="cancelSession"
                            class="mt-4 px-4 py-2 rounded-full text-sm font-semibold border border-sand text-ink hover:border-black">
                        Cancelar sessão
                    </button>
                @else
                    <p class="mt-4 text-xs text-stone">
                        Faltam menos de 24h — não é mais possível cancelar por aqui.
                    </p>
                    <span class="mt-1 inline-block text-sm font-semibold text-stone">Cancelar sessão</span>
                @endif
            </div>
        @elseif ($this->mentor)
            <div class="flex flex-wrap gap-2">
                @php
                    $slotsByDay = $this->availableSlots->groupBy(fn ($slot) => $slot->format('Y-m-d'));
                @endphp

                @for ($i = 0; $i < 14; $i++)
                    @php
                        $date = today()->addDays($i);
                        $key = $date->format('Y-m-d');
                        $hasSlots = $slotsByDay->has($key);
                    @endphp

                    @if ($hasSlots)
                        <button type="button" wire:click="selectDate('{{ $key }}')"
                                class="flex flex-col items-center px-3 py-2 rounded-xl border text-sm {{ $selectedDate === $key ? 'bg-black text-white border-black' : 'bg-card text-ink border-sand hover:border-black' }}">
                            <small class="uppercase text-xs">{{ $date->translatedFormat('D') }}</small>
                            <b>{{ $date->format('d') }}</b>
                        </button>
                    @else
                        <span class="flex flex-col items-center px-3 py-2 rounded-xl border border-sand text-sm text-stone/50 cursor-not-allowed">
                            <small class="uppercase text-xs">{{ $date->translatedFormat('D') }}</small>
                            <b>{{ $date->format('d') }}</b>
                        </span>
                    @endif
                @endfor
            </div>

            @if ($selectedDate && $slotsByDay->has($selectedDate))
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($slotsByDay->get($selectedDate) as $slot)
                        <button type="button" wire:click="bookSlot('{{ $slot->toIso8601String() }}')"
                                class="px-3.5 py-1.5 rounded-full text-sm font-medium border border-sand bg-card text-ink hover:border-black">
                            {{ $slot->format('H:i') }}
                        </button>
                    @endforeach
                </div>
            @endif
        @else
            <p class="text-stone">Nenhum mentor disponível no momento.</p>
        @endif
    </div>

    <x-membros.footer />
</div>
```

- [ ] **Step 5: Register the route**

In `routes/web.php`, add the import:

```php
use App\Livewire\Membros\Agenda;
```

Then, after the `membros.encontros` route block, add:

```php
Route::get('membros/agenda', Agenda::class)
    ->middleware(['auth', 'verified', 'active', 'tier:club'])
    ->name('membros.agenda');
```

**Known unrelated concurrent work:** `routes/web.php` has an uncommitted trailing
`require __DIR__.'/prototype.php';` line from separate work-in-progress — not yours to touch. Use a
targeted edit (never a full-file rewrite), then run `git diff routes/web.php` and confirm the
trailing line is still present. When staging, use `git add -p routes/web.php` and stage ONLY the
hunks containing your import + route; verify with `git diff --staged` before committing.

- [ ] **Step 6: Run the tests**

Run: `php artisan test tests/Feature/Livewire/Membros/AgendaTest.php`
Expected: PASS (all 9 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/Membros/Agenda.php resources/views/livewire/membros/agenda.blade.php tests/Feature/Livewire/Membros/AgendaTest.php
git add -p routes/web.php
git commit -m "feat: add the member booking page for Sessão 1:1"
```

---

## Task 5: `/membros/mentor/disponibilidade` — mentor availability page

**Files:**
- Create: `app/Livewire/Membros/Disponibilidade.php`
- Create: `resources/views/livewire/membros/disponibilidade.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Livewire/Membros/DisponibilidadeTest.php`

**Interfaces:**
- Consumes: `MentorAvailability` (Task 1).
- Produces: named route `mentor.disp` — Task 7's nav flip depends on it existing.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Livewire/Membros/DisponibilidadeTest.php`:

```php
<?php

namespace Tests\Feature\Livewire\Membros;

use App\Livewire\Membros\Disponibilidade;
use App\Models\MentorAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DisponibilidadeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/membros/mentor/disponibilidade')->assertRedirect('/login');
    }

    public function test_club_member_is_redirected_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'club']));

        $this->get('/membros/mentor/disponibilidade')->assertRedirect('/membros');
    }

    public function test_mentor_can_add_a_block(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $this->actingAs($mentor);

        Livewire::test(Disponibilidade::class)
            ->set('dayOfWeek', '2')
            ->set('startTime', '09:00')
            ->set('endTime', '12:00')
            ->call('addBlock');

        $this->assertDatabaseHas('mentor_availabilities', [
            'mentor_id' => $mentor->id, 'day_of_week' => 2, 'start_time' => '09:00:00', 'end_time' => '12:00:00',
        ]);
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $this->actingAs($mentor);

        Livewire::test(Disponibilidade::class)
            ->set('dayOfWeek', '2')
            ->set('startTime', '12:00')
            ->set('endTime', '09:00')
            ->call('addBlock')
            ->assertHasErrors('endTime');

        $this->assertDatabaseMissing('mentor_availabilities', ['mentor_id' => $mentor->id]);
    }

    public function test_mentor_can_remove_their_own_block(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $block = MentorAvailability::create([
            'mentor_id' => $mentor->id, 'day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '12:00',
        ]);

        $this->actingAs($mentor);

        Livewire::test(Disponibilidade::class)->call('removeBlock', $block->id);

        $this->assertDatabaseMissing('mentor_availabilities', ['id' => $block->id]);
    }

    public function test_mentor_cannot_remove_another_mentors_block(): void
    {
        $owner = User::factory()->create(['tier' => 'mentor']);
        $otherMentor = User::factory()->create(['tier' => 'mentor']);
        $block = MentorAvailability::create([
            'mentor_id' => $owner->id, 'day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '12:00',
        ]);

        $this->actingAs($otherMentor);

        Livewire::test(Disponibilidade::class)->call('removeBlock', $block->id);

        $this->assertDatabaseHas('mentor_availabilities', ['id' => $block->id]);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Livewire/Membros/DisponibilidadeTest.php`
Expected: FAIL — route `mentor.disp` / class `App\Livewire\Membros\Disponibilidade` don't exist.

- [ ] **Step 3: Create the Livewire component**

Create `app/Livewire/Membros/Disponibilidade.php`:

```php
<?php

namespace App\Livewire\Membros;

use App\Livewire\Concerns\ComputesUserInitials;
use App\Models\MentorAvailability;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.membros')]
class Disponibilidade extends Component
{
    use ComputesUserInitials;

    public string $dayOfWeek = '1';

    public string $startTime = '';

    public string $endTime = '';

    #[Computed]
    public function blocks(): Collection
    {
        return MentorAvailability::query()
            ->where('mentor_id', Auth::id())
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
    }

    public function addBlock(): void
    {
        $this->validate([
            'dayOfWeek' => ['required', 'integer', 'between:0,6'],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime' => ['required', 'date_format:H:i', 'after:startTime'],
        ]);

        MentorAvailability::create([
            'mentor_id' => Auth::id(),
            'day_of_week' => $this->dayOfWeek,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
        ]);

        $this->reset('startTime', 'endTime');
        unset($this->blocks);
    }

    public function removeBlock(int $blockId): void
    {
        MentorAvailability::query()
            ->where('id', $blockId)
            ->where('mentor_id', Auth::id())
            ->delete();

        unset($this->blocks);
    }

    public function render()
    {
        return view('livewire.membros.disponibilidade');
    }
}
```

- [ ] **Step 4: Create the view**

Create `resources/views/livewire/membros/disponibilidade.blade.php`:

```blade
<div class="min-h-screen text-ink">
    <x-membros.header :initials="$this->userInitials" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="mb-8">
            <h1 class="text-[clamp(26px,4vw,38px)] leading-[1.05] font-display font-extrabold tracking-[-0.015em] text-black">
                Sua disponibilidade
            </h1>
            <p class="mt-2 max-w-xl text-stone">
                Blocos recorrentes por dia da semana. O que estiver aqui aparece pros mentorados marcarem.
            </p>
        </div>

        <form wire:submit="addBlock" class="flex flex-wrap items-end gap-3 mb-8 max-w-xl">
            <div>
                <label class="block text-xs font-semibold text-stone mb-1">Dia da semana</label>
                <select wire:model="dayOfWeek" class="rounded-lg border border-sand bg-card px-3 py-2 text-sm">
                    <option value="0">Domingo</option>
                    <option value="1">Segunda</option>
                    <option value="2">Terça</option>
                    <option value="3">Quarta</option>
                    <option value="4">Quinta</option>
                    <option value="5">Sexta</option>
                    <option value="6">Sábado</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-stone mb-1">Início</label>
                <input type="time" wire:model="startTime" class="rounded-lg border border-sand bg-card px-3 py-2 text-sm">
                @error('startTime') <p class="text-xs text-brand mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-stone mb-1">Fim</label>
                <input type="time" wire:model="endTime" class="rounded-lg border border-sand bg-card px-3 py-2 text-sm">
                @error('endTime') <p class="text-xs text-brand mt-1">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="px-4 py-2 rounded-full text-sm font-semibold bg-black text-white hover:brightness-110">
                Adicionar
            </button>
        </form>

        <div class="flex flex-col gap-2 max-w-xl">
            @forelse ($this->blocks as $block)
                <div class="flex items-center justify-between px-4 py-3 rounded-xl border border-sand bg-card">
                    <span class="text-sm">
                        {{ ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'][$block->day_of_week] }}
                        · {{ $block->start_time->format('H:i') }} às {{ $block->end_time->format('H:i') }}
                    </span>
                    <button type="button" wire:click="removeBlock({{ $block->id }})"
                            class="text-xs font-semibold text-stone hover:text-ink">
                        Remover
                    </button>
                </div>
            @empty
                <p class="text-stone">Nenhum bloco de disponibilidade ainda.</p>
            @endforelse
        </div>
    </div>

    <x-membros.footer />
</div>
```

- [ ] **Step 5: Register the route**

In `routes/web.php`, add the import:

```php
use App\Livewire\Membros\Disponibilidade;
```

Then, after the `mentor.placeholder` route block, add:

```php
Route::get('membros/mentor/disponibilidade', Disponibilidade::class)
    ->middleware(['auth', 'verified', 'active', 'tier:mentor'])
    ->name('mentor.disp');
```

Same `routes/web.php` staging care as Task 4 — use `git add -p` and verify the trailing
`require __DIR__.'/prototype.php';` line survives uncommitted.

- [ ] **Step 6: Run the tests**

Run: `php artisan test tests/Feature/Livewire/Membros/DisponibilidadeTest.php`
Expected: PASS (all 5 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/Membros/Disponibilidade.php resources/views/livewire/membros/disponibilidade.blade.php tests/Feature/Livewire/Membros/DisponibilidadeTest.php
git add -p routes/web.php
git commit -m "feat: add the mentor availability management page"
```

---

## Task 6: Filament `MentorSessionResource`

**Files:**
- Create: `app/Filament/Resources/MentorSessions/MentorSessionResource.php`
- Create: `app/Filament/Resources/MentorSessions/Tables/MentorSessionsTable.php`
- Create: `app/Filament/Resources/MentorSessions/Pages/ListMentorSessions.php`
- Test: `tests/Feature/Admin/MentorSessionResourceTest.php`

**Interfaces:**
- Consumes: `MentorSession` (Task 1).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Admin/MentorSessionResourceTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\MentorSessions\Pages\ListMentorSessions;
use App\Models\MentorSession;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MentorSessionResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function session(): MentorSession
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $member = User::factory()->create(['tier' => 'club']);

        return MentorSession::create([
            'mentor_id' => $mentor->id, 'member_id' => $member->id,
            'scheduled_at' => now()->addDays(3),
        ]);
    }

    public function test_non_admin_cannot_access_the_list(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->get('/admin/mentor-sessions')->assertForbidden();
    }

    public function test_admin_can_see_an_existing_session_in_the_list(): void
    {
        $session = $this->session();

        $this->actingAs($this->admin());

        Livewire::test(ListMentorSessions::class)
            ->assertCanSeeTableRecords([$session]);
    }

    public function test_admin_can_delete_a_session(): void
    {
        $session = $this->session();

        $this->actingAs($this->admin());

        Livewire::test(ListMentorSessions::class)
            ->callTableAction(DeleteAction::class, record: $session);

        $this->assertDatabaseMissing('mentor_sessions', ['id' => $session->id]);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Admin/MentorSessionResourceTest.php`
Expected: FAIL — none of the resource classes exist yet.

- [ ] **Step 3: Create the table**

Create `app/Filament/Resources/MentorSessions/Tables/MentorSessionsTable.php`:

```php
<?php

namespace App\Filament\Resources\MentorSessions\Tables;

use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MentorSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('member.name')
                    ->label('Membro')
                    ->searchable(),
                TextColumn::make('scheduled_at')
                    ->label('Data/hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('cancelled_at')
                    ->label('Status')
                    ->formatStateUsing(fn (?string $state) => $state ? 'Cancelada' : 'Confirmada')
                    ->badge()
                    ->color(fn (?string $state) => $state ? 'gray' : 'success'),
            ])
            ->defaultSort('scheduled_at', 'desc')
            ->recordActions([
                DeleteAction::make(),
            ]);
    }
}
```

- [ ] **Step 4: Create the page**

Create `app/Filament/Resources/MentorSessions/Pages/ListMentorSessions.php`:

```php
<?php

namespace App\Filament\Resources\MentorSessions\Pages;

use App\Filament\Resources\MentorSessions\MentorSessionResource;
use Filament\Resources\Pages\ListRecords;

class ListMentorSessions extends ListRecords
{
    protected static string $resource = MentorSessionResource::class;
}
```

No `CreateAction` in the header — sessions are only ever created through the member booking flow
(Task 4), never directly by an admin.

- [ ] **Step 5: Create the resource**

Create `app/Filament/Resources/MentorSessions/MentorSessionResource.php`:

```php
<?php

namespace App\Filament\Resources\MentorSessions;

use App\Filament\Resources\MentorSessions\Pages\ListMentorSessions;
use App\Filament\Resources\MentorSessions\Tables\MentorSessionsTable;
use App\Models\MentorSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MentorSessionResource extends Resource
{
    protected static ?string $model = MentorSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $recordTitleAttribute = 'scheduled_at';

    public static function table(Table $table): Table
    {
        return MentorSessionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMentorSessions::route('/'),
        ];
    }
}
```

No `form()` method and only one page (`index`) — this resource is intentionally view/delete-only,
per the spec's "sem form de criar/editar" decision. `Heroicon::OutlinedCalendarDays` was already
confirmed to exist in this project's installed Filament version (used by `EncontroResource`); reuse
`Heroicon::OutlinedSquares2x2` instead if it's somehow missing.

- [ ] **Step 6: Run the tests**

Run: `php artisan test tests/Feature/Admin/MentorSessionResourceTest.php`
Expected: PASS (all 3 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/MentorSessions tests/Feature/Admin/MentorSessionResourceTest.php
git commit -m "feat: add read-only Filament resource for MentorSession"
```

---

## Task 7: Unlock the "Minha sessão" and "Disponibilidade" nav tabs

**Files:**
- Modify: `app/Support/PersonaNavigation.php`
- Modify: `tests/Unit/Support/PersonaNavigationTest.php`
- Modify: `tests/Feature/Membros/PersonaNavigationTest.php`

**Interfaces:**
- Consumes: named routes `membros.agenda` (Task 4) and `mentor.disp` (Task 5) — both must exist
  before this task runs, because `x-membros.header` calls `route($tab['route'])` for every tab
  marked `available: true`.
- Note: checked `Dashboard::quickLinks()` — it references `membros.agenda` already (the
  `hasClubAccess()` branch's third quick link), so flipping this flag also unlocks that existing
  quick-link entry on the Início page. This is expected, not a regression — confirm the existing
  `DashboardTest` assertions around that quick link still pass after this change; do not treat a
  newly-unlocked "Marcar minha sessão" link as a bug.

- [ ] **Step 1: Write the failing tests**

In `tests/Unit/Support/PersonaNavigationTest.php`, replace the club and mentor tests:

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

    public function test_mentor_tier_has_one_available_tab_and_four_locked_tabs(): void
    {
        $tabs = (new PersonaNavigation)->tabs('mentor');

        $this->assertCount(5, $tabs);
        $this->assertSame(['Painel', 'Radar', 'Dossiês', 'Publicar', 'Disponibilidade'], array_column($tabs, 'label'));
        $this->assertSame([true, false, false, false, false], array_column($tabs, 'available'));
    }
```

with:

```php
    public function test_club_tier_has_five_available_tabs_and_two_locked_tabs(): void
    {
        $tabs = (new PersonaNavigation)->tabs('club');

        $this->assertCount(7, $tabs);
        $this->assertSame(
            ['Início', 'Aulas', 'Meu cofre', 'Minha sessão', 'Pessoas', 'Encontros', 'Frameworks'],
            array_column($tabs, 'label'),
        );
        $this->assertSame([true, true, false, true, false, true, true], array_column($tabs, 'available'));
    }

    public function test_mentor_tier_has_two_available_tabs_and_three_locked_tabs(): void
    {
        $tabs = (new PersonaNavigation)->tabs('mentor');

        $this->assertCount(5, $tabs);
        $this->assertSame(['Painel', 'Radar', 'Dossiês', 'Publicar', 'Disponibilidade'], array_column($tabs, 'label'));
        $this->assertSame([true, false, false, false, true], array_column($tabs, 'available'));
    }
```

In `tests/Feature/Membros/PersonaNavigationTest.php`, replace the club test:

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

with:

```php
    public function test_club_tier_shows_inicio_aulas_agenda_encontros_and_frameworks_as_links_and_two_tabs_locked(): void
    {
        $user = User::factory()->create(['tier' => 'club']);
        $this->actingAs($user);

        $html = $this->get('/membros')->assertOk()->getContent();

        foreach ([
            ['href' => 'http://localhost/membros', 'label' => 'Início'],
            ['href' => 'http://localhost/membros/aulas', 'label' => 'Aulas'],
            ['href' => 'http://localhost/membros/agenda', 'label' => 'Minha sessão'],
            ['href' => 'http://localhost/membros/encontros', 'label' => 'Encontros'],
            ['href' => 'http://localhost/membros/frameworks', 'label' => 'Frameworks'],
        ] as $link) {
            $this->assertMatchesRegularExpression(
                '#<a[^>]*href="'.preg_quote($link['href'], '#').'"[^>]*>\s*'.preg_quote($link['label'], '#').'\s*</a>#s',
                $html,
            );
        }

        foreach (['Meu cofre', 'Pessoas'] as $label) {
            $this->assertMatchesRegularExpression(
                '#<span[^>]*title="Em breve"[^>]*>\s*'.preg_quote($label, '#').'#s',
                $html,
            );
        }
    }
```

And replace the mentor test:

```php
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
```

with:

```php
    public function test_mentor_tier_shows_disponibilidade_as_a_link_and_three_tabs_locked(): void
    {
        $user = User::factory()->create(['tier' => 'mentor']);
        $this->actingAs($user);

        $html = $this->get('/membros/mentor')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<a[^>]*href="http://localhost/membros/mentor/disponibilidade"[^>]*>\s*Disponibilidade\s*</a>#s',
            $html,
        );

        foreach (['Radar', 'Dossiês', 'Publicar'] as $label) {
            $this->assertMatchesRegularExpression(
                '#<span[^>]*title="Em breve"[^>]*>\s*'.preg_quote($label, '#').'#s',
                $html,
            );
        }
    }
```

(The old `assertStringNotContainsString('<a href="http://localhost/mentor', $html)` assertion is
dropped — it's no longer true now that `Disponibilidade` IS a real `<a href="...membros/mentor/...">`
link; the new positive assertion above replaces it.)

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Unit/Support/PersonaNavigationTest.php tests/Feature/Membros/PersonaNavigationTest.php`
Expected: FAIL — both flags are still `false`.

- [ ] **Step 3: Flip the flags**

In `app/Support/PersonaNavigation.php`, change:

```php
                ['label' => 'Minha sessão', 'route' => 'membros.agenda', 'available' => false],
```

to:

```php
                ['label' => 'Minha sessão', 'route' => 'membros.agenda', 'available' => true],
```

(in the `'club'` array), and change:

```php
                ['label' => 'Disponibilidade', 'route' => 'mentor.disp', 'available' => false],
```

to:

```php
                ['label' => 'Disponibilidade', 'route' => 'mentor.disp', 'available' => true],
```

(in the `'mentor'` array). Nothing else in the file changes.

- [ ] **Step 4: Run the nav tests, then the full suite**

Run: `php artisan test tests/Unit/Support/PersonaNavigationTest.php tests/Feature/Membros/PersonaNavigationTest.php`
Expected: PASS.

Run: `php artisan test tests/Feature/Livewire/Membros/DashboardTest.php`
Expected: PASS. If a quick-links test fails because "Marcar minha sessão" (or similar) unexpectedly
became a live link instead of a locked span, that is the correctly-anticipated interaction described
above — update that specific assertion to expect a real `<a href="...membros/agenda">` link, matching
the pattern of the equivalent fix from the Encontros ao vivo and Frameworks DO plans, and re-run.

Run: `php artisan test`
Expected: PASS — full suite green.

- [ ] **Step 5: Commit**

```bash
git add app/Support/PersonaNavigation.php tests/Unit/Support/PersonaNavigationTest.php tests/Feature/Membros/PersonaNavigationTest.php tests/Feature/Livewire/Membros/DashboardTest.php
git commit -m "feat: unlock Minha sessão and Disponibilidade nav tabs"
```

(Include `DashboardTest.php` in the commit only if Step 4 actually required editing it.)

---

## Manual verification (after Task 7)

1. As an admin, use Filament to set one `User`'s `tier` to `mentor` (or use the existing seeded
   mentor account). Log in as that mentor, go to "Disponibilidade", add a block (e.g. Terça
   09:00–12:00), confirm it appears in the list, remove it, confirm it disappears.
2. Add the block back. Log in as a CLUB member, go to "Minha sessão" — confirm the next matching
   weekday within 14 days appears as a clickable day, other days are disabled, and clicking it shows
   90-minute slots starting at 09:00 and 10:30. Book one — confirm the calendar is replaced by the
   "Sua próxima sessão" card with a working "Cancelar sessão" button (visible since it's more than
   24h away).
3. In Filament (`/admin/mentor-sessions`), confirm the booked session appears with the member's name
   and "Confirmada" status.
4. Cancel the session from `/membros/agenda` — confirm the calendar reappears and the same slot is
   bookable again.
5. Run `php artisan test` (full suite) once more — green.
