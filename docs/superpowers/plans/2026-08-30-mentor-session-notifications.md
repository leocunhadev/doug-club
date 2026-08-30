# Notificações de sessão 1:1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close GitHub issue #27 — notify mentor and member by e-mail when a 1:1 session is booked, notify the mentor when the member cancels, and remind the member ~1h before the session — plus document how to run the queue worker this requires in production.

**Architecture:** Four `ShouldQueue` `Notification` classes carry the mail copy (mirroring the existing `ClubApplicationApproved` pattern). `BookMentorSession::handle()` and `CancelMentorSession::handle()` dispatch/notify directly (same pattern as `ActivateUserFromPayment::handle()`, which already triggers side effects like `Password::sendResetLink()` from inside an Action class). The reminder is a single `ShouldQueue` job dispatched once at booking time with `->delay($scheduledAt->subHour())` — the queue's own `available_at` timestamp handles the timing, so no cron/scheduler is needed, only a running queue worker.

**Tech Stack:** Laravel 13, PHPUnit (`php artisan test`), SQLite (`:memory:` in tests). `MAIL_MAILER=array` and `QUEUE_CONNECTION=sync` are already pinned in `phpunit.xml` as a test safety net — no test-config changes needed.

**Spec:** `docs/superpowers/specs/2026-08-30-mentor-session-notifications-design.md`

## Global Constraints

- Confirmation e-mail on booking goes to **both** member and mentor.
- Cancellation e-mail goes **only** to the mentor (the member who cancelled already sees it happen on screen).
- Reminder e-mail goes **only** to the member, ~1h before `scheduled_at`.
- All four notifications `implement ShouldQueue` (async), unlike the existing synchronous `ClubApplicationApproved`.
- No new database columns — the reminder is delay-based (one job dispatched at booking time), not a polling/cron mechanism, so no `reminder_sent_at` guard column is needed.
- Notifications/job dispatch live inside `BookMentorSession::handle()` / `CancelMentorSession::handle()`, not in the `Agenda` Livewire component — so any future caller of these actions gets the same notifications for free.
- No changes to mentor-side cancellation via Filament (`DeleteAction`) — out of scope, it doesn't go through `CancelMentorSession`.
- No session rescheduling exists in the app today — the reminder job is dispatched exactly once, with a guard clause (not a cancel/reschedule mechanism) protecting against a cancelled or already-past session.
- This work requires a running queue worker in production (`php artisan queue:work` via Supervisor) — no cron `schedule:run` is needed for this feature.

---

## Task 1: Notification classes

**Files:**
- Create: `app/Notifications/MentorSessionBookedNotification.php`
- Create: `app/Notifications/MentorSessionBookedForMentorNotification.php`
- Create: `app/Notifications/MentorSessionCancelledForMentorNotification.php`
- Create: `app/Notifications/MentorSessionReminderNotification.php`
- Test: `tests/Unit/Notifications/MentorSessionBookedNotificationTest.php`
- Test: `tests/Unit/Notifications/MentorSessionBookedForMentorNotificationTest.php`
- Test: `tests/Unit/Notifications/MentorSessionCancelledForMentorNotificationTest.php`
- Test: `tests/Unit/Notifications/MentorSessionReminderNotificationTest.php`

**Interfaces:**
- Produces: `MentorSessionBookedNotification(MentorSession $session)`, `MentorSessionBookedForMentorNotification(MentorSession $session)`, `MentorSessionCancelledForMentorNotification(MentorSession $session)`, `MentorSessionReminderNotification(MentorSession $session)` — all `implements ShouldQueue`, all with a public `MentorSession $session` property (set via constructor promotion). Tasks 2 and 3 depend on these exact class names and constructor signatures.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Notifications/MentorSessionBookedNotificationTest.php`:

```php
<?php

namespace Tests\Unit\Notifications;

use App\Models\MentorSession;
use App\Models\User;
use App\Notifications\MentorSessionBookedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentorSessionBookedNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function session(): MentorSession
    {
        $mentor = User::factory()->create(['tier' => 'mentor', 'name' => 'Douglas Oliveira']);
        $member = User::factory()->create(['tier' => 'club', 'name' => 'Carla Nunes']);

        return MentorSession::create([
            'mentor_id' => $mentor->id, 'member_id' => $member->id,
            'scheduled_at' => now()->addDays(3)->setTime(9, 0),
        ]);
    }

    public function test_it_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new MentorSessionBookedNotification($this->session()));
    }

    public function test_it_is_sent_only_via_mail(): void
    {
        $session = $this->session();

        $this->assertSame(['mail'], (new MentorSessionBookedNotification($session))->via($session->member));
    }

    public function test_mail_message_has_the_expected_subject_and_content(): void
    {
        $session = $this->session();

        $mail = (new MentorSessionBookedNotification($session))->toMail($session->member);
        $body = implode(' ', $mail->introLines);

        $this->assertSame('Sua sessão foi confirmada', $mail->subject);
        $this->assertStringContainsString('Douglas Oliveira', $body);
        $this->assertStringContainsString($session->scheduled_at->format('d/m/Y \à\s H:i'), $body);
        $this->assertSame(route('membros.agenda'), $mail->actionUrl);
    }
}
```

Create `tests/Unit/Notifications/MentorSessionBookedForMentorNotificationTest.php`:

```php
<?php

namespace Tests\Unit\Notifications;

use App\Models\MentorSession;
use App\Models\User;
use App\Notifications\MentorSessionBookedForMentorNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentorSessionBookedForMentorNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function session(): MentorSession
    {
        $mentor = User::factory()->create(['tier' => 'mentor', 'name' => 'Douglas Oliveira']);
        $member = User::factory()->create(['tier' => 'club', 'name' => 'Carla Nunes']);

        return MentorSession::create([
            'mentor_id' => $mentor->id, 'member_id' => $member->id,
            'scheduled_at' => now()->addDays(3)->setTime(9, 0),
        ]);
    }

    public function test_it_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new MentorSessionBookedForMentorNotification($this->session()));
    }

    public function test_it_is_sent_only_via_mail(): void
    {
        $session = $this->session();

        $this->assertSame(['mail'], (new MentorSessionBookedForMentorNotification($session))->via($session->mentor));
    }

    public function test_mail_message_has_the_expected_subject_and_content(): void
    {
        $session = $this->session();

        $mail = (new MentorSessionBookedForMentorNotification($session))->toMail($session->mentor);
        $body = implode(' ', $mail->introLines);

        $this->assertSame('Nova sessão marcada', $mail->subject);
        $this->assertStringContainsString('Carla Nunes', $body);
        $this->assertStringContainsString($session->scheduled_at->format('d/m/Y \à\s H:i'), $body);
    }
}
```

Create `tests/Unit/Notifications/MentorSessionCancelledForMentorNotificationTest.php`:

```php
<?php

namespace Tests\Unit\Notifications;

use App\Models\MentorSession;
use App\Models\User;
use App\Notifications\MentorSessionCancelledForMentorNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentorSessionCancelledForMentorNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function session(): MentorSession
    {
        $mentor = User::factory()->create(['tier' => 'mentor', 'name' => 'Douglas Oliveira']);
        $member = User::factory()->create(['tier' => 'club', 'name' => 'Carla Nunes']);

        return MentorSession::create([
            'mentor_id' => $mentor->id, 'member_id' => $member->id,
            'scheduled_at' => now()->addDays(3)->setTime(9, 0),
            'cancelled_at' => now(),
        ]);
    }

    public function test_it_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new MentorSessionCancelledForMentorNotification($this->session()));
    }

    public function test_it_is_sent_only_via_mail(): void
    {
        $session = $this->session();

        $this->assertSame(['mail'], (new MentorSessionCancelledForMentorNotification($session))->via($session->mentor));
    }

    public function test_mail_message_has_the_expected_subject_and_content(): void
    {
        $session = $this->session();

        $mail = (new MentorSessionCancelledForMentorNotification($session))->toMail($session->mentor);
        $body = implode(' ', $mail->introLines);

        $this->assertSame('Sessão cancelada', $mail->subject);
        $this->assertStringContainsString('Carla Nunes', $body);
        $this->assertStringContainsString($session->scheduled_at->format('d/m/Y \à\s H:i'), $body);
        $this->assertStringContainsString('abriu de novo', $body);
    }
}
```

Create `tests/Unit/Notifications/MentorSessionReminderNotificationTest.php`:

```php
<?php

namespace Tests\Unit\Notifications;

use App\Models\MentorSession;
use App\Models\User;
use App\Notifications\MentorSessionReminderNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentorSessionReminderNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function session(): MentorSession
    {
        $mentor = User::factory()->create(['tier' => 'mentor', 'name' => 'Douglas Oliveira']);
        $member = User::factory()->create(['tier' => 'club', 'name' => 'Carla Nunes']);

        return MentorSession::create([
            'mentor_id' => $mentor->id, 'member_id' => $member->id,
            'scheduled_at' => now()->addHour(),
        ]);
    }

    public function test_it_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new MentorSessionReminderNotification($this->session()));
    }

    public function test_it_is_sent_only_via_mail(): void
    {
        $session = $this->session();

        $this->assertSame(['mail'], (new MentorSessionReminderNotification($session))->via($session->member));
    }

    public function test_mail_message_has_the_expected_subject_and_content(): void
    {
        $session = $this->session();

        $mail = (new MentorSessionReminderNotification($session))->toMail($session->member);
        $body = implode(' ', $mail->introLines);

        $this->assertSame('Sua sessão é daqui a pouco', $mail->subject);
        $this->assertStringContainsString('Douglas Oliveira', $body);
        $this->assertStringContainsString($session->scheduled_at->format('H:i'), $body);
        $this->assertSame(route('membros.agenda'), $mail->actionUrl);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Unit/Notifications/MentorSessionBookedNotificationTest.php tests/Unit/Notifications/MentorSessionBookedForMentorNotificationTest.php tests/Unit/Notifications/MentorSessionCancelledForMentorNotificationTest.php tests/Unit/Notifications/MentorSessionReminderNotificationTest.php`
Expected: FAIL — the 4 notification classes don't exist yet.

- [ ] **Step 3: Create the notification classes**

Create `app/Notifications/MentorSessionBookedNotification.php`:

```php
<?php

namespace App\Notifications;

use App\Models\MentorSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MentorSessionBookedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public MentorSession $session) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Sua sessão foi confirmada')
            ->greeting("Oi, {$notifiable->name}!")
            ->line("Sua sessão 1:1 com {$this->session->mentor->name} foi confirmada para {$this->session->scheduled_at->format('d/m/Y \à\s H:i')}.")
            ->action('Ver minha agenda', route('membros.agenda'));
    }
}
```

Create `app/Notifications/MentorSessionBookedForMentorNotification.php`:

```php
<?php

namespace App\Notifications;

use App\Models\MentorSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MentorSessionBookedForMentorNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public MentorSession $session) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nova sessão marcada')
            ->greeting("Oi, {$notifiable->name}!")
            ->line("{$this->session->member->name} marcou uma sessão 1:1 com você para {$this->session->scheduled_at->format('d/m/Y \à\s H:i')}.");
    }
}
```

Create `app/Notifications/MentorSessionCancelledForMentorNotification.php`:

```php
<?php

namespace App\Notifications;

use App\Models\MentorSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MentorSessionCancelledForMentorNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public MentorSession $session) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Sessão cancelada')
            ->greeting("Oi, {$notifiable->name}!")
            ->line("{$this->session->member->name} cancelou a sessão 1:1 que estava marcada para {$this->session->scheduled_at->format('d/m/Y \à\s H:i')}.")
            ->line('O horário abriu de novo na sua agenda.');
    }
}
```

Create `app/Notifications/MentorSessionReminderNotification.php`:

```php
<?php

namespace App\Notifications;

use App\Models\MentorSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MentorSessionReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public MentorSession $session) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Sua sessão é daqui a pouco')
            ->greeting("Oi, {$notifiable->name}!")
            ->line("Sua sessão 1:1 com {$this->session->mentor->name} é às {$this->session->scheduled_at->format('H:i')}.")
            ->action('Ver minha agenda', route('membros.agenda'));
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Unit/Notifications/MentorSessionBookedNotificationTest.php tests/Unit/Notifications/MentorSessionBookedForMentorNotificationTest.php tests/Unit/Notifications/MentorSessionCancelledForMentorNotificationTest.php tests/Unit/Notifications/MentorSessionReminderNotificationTest.php`
Expected: PASS (12 tests total)

- [ ] **Step 5: Commit**

```bash
git add app/Notifications/MentorSessionBookedNotification.php app/Notifications/MentorSessionBookedForMentorNotification.php app/Notifications/MentorSessionCancelledForMentorNotification.php app/Notifications/MentorSessionReminderNotification.php tests/Unit/Notifications/MentorSessionBookedNotificationTest.php tests/Unit/Notifications/MentorSessionBookedForMentorNotificationTest.php tests/Unit/Notifications/MentorSessionCancelledForMentorNotificationTest.php tests/Unit/Notifications/MentorSessionReminderNotificationTest.php
git commit -m "feat: add mentor session notification classes (issue #27)"
```

---

## Task 2: Reminder job

**Files:**
- Create: `app/Jobs/SendSessionReminderJob.php`
- Test: `tests/Unit/Jobs/SendSessionReminderJobTest.php`

**Interfaces:**
- Consumes: `App\Notifications\MentorSessionReminderNotification(MentorSession $session)` (Task 1).
- Produces: `SendSessionReminderJob(MentorSession $session)` — `implements ShouldQueue`, public `MentorSession $session` property. Task 3 depends on this exact class name and constructor signature, and dispatches it via `SendSessionReminderJob::dispatch($session)->delay(...)`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Jobs/SendSessionReminderJobTest.php`:

```php
<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendSessionReminderJob;
use App\Models\MentorSession;
use App\Models\User;
use App\Notifications\MentorSessionReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendSessionReminderJobTest extends TestCase
{
    use RefreshDatabase;

    private function session(array $overrides = []): MentorSession
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $member = User::factory()->create(['tier' => 'club']);

        return MentorSession::create(array_merge([
            'mentor_id' => $mentor->id, 'member_id' => $member->id,
            'scheduled_at' => now()->addHour(),
        ], $overrides));
    }

    public function test_notifies_the_member_for_a_valid_upcoming_session(): void
    {
        Notification::fake();
        $session = $this->session();

        (new SendSessionReminderJob($session))->handle();

        Notification::assertSentTo($session->member, MentorSessionReminderNotification::class);
    }

    public function test_does_not_notify_when_the_session_was_cancelled(): void
    {
        Notification::fake();
        $session = $this->session(['cancelled_at' => now()]);

        (new SendSessionReminderJob($session))->handle();

        Notification::assertNothingSent();
    }

    public function test_does_not_notify_when_the_scheduled_time_already_passed(): void
    {
        Notification::fake();
        $session = $this->session(['scheduled_at' => now()->subHour()]);

        (new SendSessionReminderJob($session))->handle();

        Notification::assertNothingSent();
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Unit/Jobs/SendSessionReminderJobTest.php`
Expected: FAIL — `SendSessionReminderJob` doesn't exist yet.

- [ ] **Step 3: Create the job**

Create `app/Jobs/SendSessionReminderJob.php`:

```php
<?php

namespace App\Jobs;

use App\Models\MentorSession;
use App\Notifications\MentorSessionReminderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSessionReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public MentorSession $session) {}

    public function handle(): void
    {
        $this->session->refresh();

        if ($this->session->isCancelled() || $this->session->scheduled_at->isPast()) {
            return;
        }

        $this->session->member->notify(new MentorSessionReminderNotification($this->session));
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Unit/Jobs/SendSessionReminderJobTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/SendSessionReminderJob.php tests/Unit/Jobs/SendSessionReminderJobTest.php
git commit -m "feat: add session reminder job (issue #27)"
```

---

## Task 3: Wire notifications into the booking/cancellation actions

**Files:**
- Modify: `app/Actions/BookMentorSession.php`
- Modify: `app/Actions/CancelMentorSession.php`
- Test: `tests/Unit/BookMentorSessionTest.php`
- Test: `tests/Unit/CancelMentorSessionTest.php`
- Test: `tests/Feature/Livewire/Membros/AgendaTest.php`

**Interfaces:**
- Consumes: `MentorSessionBookedNotification`, `MentorSessionBookedForMentorNotification`, `MentorSessionCancelledForMentorNotification` (Task 1); `SendSessionReminderJob` (Task 2).
- Produces: nothing later tasks depend on.

- [ ] **Step 1: Write the failing tests**

Replace the full contents of `tests/Unit/BookMentorSessionTest.php` with:

```php
<?php

namespace Tests\Unit;

use App\Actions\BookMentorSession;
use App\Jobs\SendSessionReminderJob;
use App\Models\MentorSession;
use App\Models\User;
use App\Notifications\MentorSessionBookedForMentorNotification;
use App\Notifications\MentorSessionBookedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
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

    public function test_notifies_the_member_and_the_mentor_when_a_session_is_booked(): void
    {
        Notification::fake();

        $mentor = User::factory()->create(['tier' => 'mentor']);
        $member = User::factory()->create(['tier' => 'club']);
        $scheduledAt = now()->addDays(3)->setTime(9, 0);

        $session = (new BookMentorSession)->handle($mentor, $member, $scheduledAt);

        Notification::assertSentTo($member, MentorSessionBookedNotification::class, function ($notification) use ($session) {
            return $notification->session->is($session);
        });
        Notification::assertSentTo($mentor, MentorSessionBookedForMentorNotification::class, function ($notification) use ($session) {
            return $notification->session->is($session);
        });
    }

    public function test_does_not_notify_anyone_when_the_slot_is_already_booked(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $firstMember = User::factory()->create(['tier' => 'club']);
        $secondMember = User::factory()->create(['tier' => 'club']);
        $scheduledAt = now()->addDays(3)->setTime(9, 0);

        (new BookMentorSession)->handle($mentor, $firstMember, $scheduledAt);

        Notification::fake();
        (new BookMentorSession)->handle($mentor, $secondMember, $scheduledAt);

        Notification::assertNotSentTo($secondMember, MentorSessionBookedNotification::class);
        Notification::assertNotSentTo($mentor, MentorSessionBookedForMentorNotification::class);
    }

    public function test_dispatches_a_reminder_job_delayed_to_one_hour_before_the_session(): void
    {
        Queue::fake();

        $mentor = User::factory()->create(['tier' => 'mentor']);
        $member = User::factory()->create(['tier' => 'club']);
        $scheduledAt = now()->addDays(3)->setTime(9, 0);

        $session = (new BookMentorSession)->handle($mentor, $member, $scheduledAt);

        Queue::assertPushed(SendSessionReminderJob::class, function ($job) use ($session, $scheduledAt) {
            return $job->session->is($session)
                && $job->delay !== null
                && abs($job->delay->timestamp - $scheduledAt->copy()->subHour()->timestamp) < 2;
        });
    }
}
```

Replace the full contents of `tests/Unit/CancelMentorSessionTest.php` with:

```php
<?php

namespace Tests\Unit;

use App\Actions\CancelMentorSession;
use App\Models\MentorSession;
use App\Models\User;
use App\Notifications\MentorSessionCancelledForMentorNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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

    public function test_notifies_the_mentor_when_the_session_is_cancelled(): void
    {
        Notification::fake();

        $mentor = User::factory()->create(['tier' => 'mentor']);
        $member = User::factory()->create(['tier' => 'club']);
        $session = MentorSession::create([
            'mentor_id' => $mentor->id, 'member_id' => $member->id,
            'scheduled_at' => now()->addDays(3),
        ]);

        (new CancelMentorSession)->handle($session);

        Notification::assertSentTo($mentor, MentorSessionCancelledForMentorNotification::class, function ($notification) use ($session) {
            return $notification->session->is($session);
        });
        Notification::assertNotSentTo($member, MentorSessionCancelledForMentorNotification::class);
    }
}
```

In `tests/Feature/Livewire/Membros/AgendaTest.php`, add these two imports after the existing `use` block (find `use Livewire\Livewire;` and add before it, keeping alphabetical order):

```php
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
```

Then, inside `test_booking_a_valid_slot_creates_the_session()`, add `Notification::fake();` and `Queue::fake();` as the first two lines of the method body (before `$mentor = $this->mentor();`):

```php
    public function test_booking_a_valid_slot_creates_the_session(): void
    {
        Notification::fake();
        Queue::fake();

        $mentor = $this->mentor();
```

And inside `test_cancelling_more_than_24_hours_ahead_cancels_the_session()`, add `Notification::fake();` as the first line of the method body (before `$mentor = $this->mentor();`):

```php
    public function test_cancelling_more_than_24_hours_ahead_cancels_the_session(): void
    {
        Notification::fake();

        $mentor = $this->mentor();
```

No other test in `AgendaTest.php` needs changes — the notification/job content itself is already covered by Task 1 and Task 2's unit tests; these two Livewire tests just need to stop sending real (array-mailer-captured) mail during their run, now that `BookMentorSession`/`CancelMentorSession` trigger real `notify()`/`dispatch()` calls.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Unit/BookMentorSessionTest.php tests/Unit/CancelMentorSessionTest.php tests/Feature/Livewire/Membros/AgendaTest.php`
Expected: FAIL — the new notification/dispatch assertions fail because `BookMentorSession`/`CancelMentorSession` don't call `notify()`/`dispatch()` yet.

- [ ] **Step 3: Wire the actions**

Change `app/Actions/BookMentorSession.php` from:

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

to:

```php
<?php

namespace App\Actions;

use App\Jobs\SendSessionReminderJob;
use App\Models\MentorSession;
use App\Models\User;
use App\Notifications\MentorSessionBookedForMentorNotification;
use App\Notifications\MentorSessionBookedNotification;
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

            $session = MentorSession::create([
                'mentor_id' => $mentor->id,
                'member_id' => $member->id,
                'scheduled_at' => $scheduledAt,
            ]);

            $session->member->notify(new MentorSessionBookedNotification($session));
            $session->mentor->notify(new MentorSessionBookedForMentorNotification($session));

            SendSessionReminderJob::dispatch($session)->delay($scheduledAt->copy()->subHour());

            return $session;
        });
    }
}
```

Change `app/Actions/CancelMentorSession.php` from:

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

to:

```php
<?php

namespace App\Actions;

use App\Models\MentorSession;
use App\Notifications\MentorSessionCancelledForMentorNotification;

class CancelMentorSession
{
    public function handle(MentorSession $session): void
    {
        $session->update(['cancelled_at' => now()]);

        $session->mentor->notify(new MentorSessionCancelledForMentorNotification($session));
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Unit/BookMentorSessionTest.php tests/Unit/CancelMentorSessionTest.php tests/Feature/Livewire/Membros/AgendaTest.php`
Expected: PASS (all tests in all three files)

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`
Expected: PASS, no regressions.

- [ ] **Step 6: Commit**

```bash
git add app/Actions/BookMentorSession.php app/Actions/CancelMentorSession.php tests/Unit/BookMentorSessionTest.php tests/Unit/CancelMentorSessionTest.php tests/Feature/Livewire/Membros/AgendaTest.php
git commit -m "feat: send booking/cancellation notifications and dispatch the reminder job (issue #27)"
```

---

## Task 4: Queue worker infra runbook

**Files:**
- Create: `docs/deploy/queue-worker.md`

**Interfaces:**
- Consumes: nothing.
- Produces: nothing later tasks depend on — this is documentation only, no automated test. The reviewer verifies the runbook's content is accurate and complete against the spec's infra section, not by running a test suite.

- [ ] **Step 1: Write the runbook**

Create `docs/deploy/queue-worker.md`:

```markdown
# Queue worker em produção (Supervisor)

Este projeto usa fila de e-mails (notificações de sessão 1:1, issue #27) via `QUEUE_CONNECTION=database`. Sem um worker de fila rodando continuamente em produção, nenhum e-mail enfileirado é enviado — eles ficam acumulados na tabela `jobs`, aguardando um worker.

## Passo a passo (Ubuntu/Debian, ajuste para sua distro)

1. Instalar o Supervisor:
   \`\`\`bash
   sudo apt update
   sudo apt install supervisor
   \`\`\`

2. Criar o arquivo de configuração `/etc/supervisor/conf.d/doing-club-worker.conf`:
   \`\`\`ini
   [program:doing-club-worker]
   process_name=%(program_name)s_%(process_num)02d
   command=php /caminho/completo/para/doug-club/artisan queue:work --sleep=3 --tries=3 --max-time=3600
   autostart=true
   autorestart=true
   stopasgroup=true
   killasgroup=true
   user=www-data
   numprocs=1
   redirect_stderr=true
   stdout_logfile=/caminho/completo/para/doug-club/storage/logs/worker.log
   stopwaitsecs=3600
   \`\`\`

   Substitua `/caminho/completo/para/doug-club` pelo caminho real do projeto no VPS, e `user=www-data` pelo usuário que roda o deploy (o mesmo dono dos arquivos do projeto).

3. Registrar e iniciar:
   \`\`\`bash
   sudo supervisorctl reread
   sudo supervisorctl update
   sudo supervisorctl start doing-club-worker:*
   \`\`\`

4. Verificar que está rodando:
   \`\`\`bash
   sudo supervisorctl status
   \`\`\`
   Deve mostrar `doing-club-worker:doing-club-worker_00   RUNNING`.

## Teste de fumaça pós-deploy

Depois de configurar o worker, confirme que ele está processando jobs de verdade:

1. No servidor, rode `php artisan tinker`.
2. Marque uma sessão de teste (ou use o próprio fluxo da Agenda como membro/mentor de teste) para gerar uma notificação real na fila.
3. Rode `tail -f storage/logs/worker.log` e confirme que o job aparece como processado (`Processed: App\Jobs\SendSessionReminderJob` ou `App\Notifications\...`) em poucos segundos.
4. Se nada aparecer no log, confira `sudo supervisorctl status` — se o processo não estiver `RUNNING`, olhe `sudo supervisorctl tail doing-club-worker stderr` para o erro.

## Por que não precisa de cron

O `->delay()` usado pelo lembrete de sessão (`SendSessionReminderJob`) é resolvido inteiramente pela fila (`available_at` na tabela `jobs`) — o worker já verifica isso continuamente. Não é necessário configurar `php artisan schedule:run` via crontab para este recurso.

## Se o worker cair

O Supervisor reinicia o processo automaticamente (`autorestart=true`). Se o VPS inteiro reiniciar, o Supervisor também precisa estar configurado para iniciar no boot — isso já é o comportamento padrão de uma instalação padrão do Supervisor via `apt`, mas confirme com:
\`\`\`bash
sudo systemctl is-enabled supervisor
\`\`\`
Se não estiver `enabled`, rode `sudo systemctl enable supervisor`.
```

- [ ] **Step 2: Commit**

```bash
git add docs/deploy/queue-worker.md
git commit -m "docs: add production queue worker runbook (issue #27)"
```
