# Radar Pontes Sugeridas Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close GitHub issue #41 — show the mentor up to 3 "suggested bridge" matches on the Radar page (member A wants to learn something member B can teach), and let the mentor introduce the pair with one click.

**Architecture:** A new `#[Computed] Radar::suggestedBridges()` does an in-memory O(n²) comparison across `tier=club` members' `learn_tags`/`teach_tags` (case-insensitive exact match, no fuzzy matching), excluding pairs that already have a `BridgeRequest` between them, capped at 3 results. A new `Radar::makeBridge()` action creates the `BridgeRequest` and notifies both members via a single parametrized `Notification` class (mirroring the `ShouldQueue` pattern from issue #27).

**Tech Stack:** Laravel 13, Livewire 3, Tailwind CSS v3, PHPUnit (`php artisan test`), SQLite (`:memory:` in tests). `MAIL_MAILER=array` and `QUEUE_CONNECTION=sync` are already pinned in `phpunit.xml`.

**Spec:** `docs/superpowers/specs/2026-08-30-radar-suggested-bridges-design.md`

## Global Constraints

- Scope is ONLY the teach/learn match card — the "Start engajado pronto pro CLUB" card was split into issue #50, not touched by this plan.
- Matching is case-insensitive EXACT string comparison on tags (`mb_strtolower`), no fuzzy/partial matching — a documented v1 limitation, not a bug to "fix" in this plan.
- Pairs that already have a `BridgeRequest` (either direction) are excluded from suggestions.
- Results capped at 3, no ranking beyond default query order — purely a visual-density limit, not a "best match" algorithm.
- `Radar::makeBridge()` creates `BridgeRequest::create(['requester_id' => $learnerId, 'target_id' => $teacherId])` and must re-validate both users are `tier=club` server-side before creating anything (never trust the Livewire payload's IDs blindly) — matches this project's established Livewire client-payload security discipline.
- The action must be idempotent (a duplicate click, or a pair that became connected between render and click, is a silent no-op — never a duplicate `BridgeRequest` or duplicate notification).

---

## Task 1: `BridgeSuggestedNotification`

**Files:**
- Create: `app/Notifications/BridgeSuggestedNotification.php`
- Test: `tests/Unit/Notifications/BridgeSuggestedNotificationTest.php`

**Interfaces:**
- Produces: `BridgeSuggestedNotification(User $otherMember, string $tag, bool $iAmTheLearner)` — `implements ShouldQueue`. Task 3 depends on this exact class name and constructor signature.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Notifications/BridgeSuggestedNotificationTest.php`:

```php
<?php

namespace Tests\Unit\Notifications;

use App\Models\User;
use App\Notifications\BridgeSuggestedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BridgeSuggestedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_implements_should_queue(): void
    {
        $other = User::factory()->create(['name' => 'Marina Alves']);

        $this->assertInstanceOf(ShouldQueue::class, new BridgeSuggestedNotification($other, 'precificação', true));
    }

    public function test_it_is_sent_only_via_mail(): void
    {
        $notifiable = User::factory()->create();
        $other = User::factory()->create(['name' => 'Marina Alves']);

        $this->assertSame(['mail'], (new BridgeSuggestedNotification($other, 'precificação', true))->via($notifiable));
    }

    public function test_mail_message_for_the_learner_mentions_what_the_other_can_teach(): void
    {
        $notifiable = User::factory()->create(['name' => 'Ricardo Mendes']);
        $other = User::factory()->create(['name' => 'Marina Alves']);

        $mail = (new BridgeSuggestedNotification($other, 'precificação', true))->toMail($notifiable);
        $body = implode(' ', $mail->introLines);

        $this->assertSame('Uma ponte foi feita pra você', $mail->subject);
        $this->assertStringContainsString('Marina Alves', $body);
        $this->assertStringContainsString('pode te ajudar com precificação', $body);
        $this->assertSame(route('membros.pessoas'), $mail->actionUrl);
    }

    public function test_mail_message_for_the_teacher_mentions_what_the_other_wants_to_learn(): void
    {
        $notifiable = User::factory()->create(['name' => 'Marina Alves']);
        $other = User::factory()->create(['name' => 'Ricardo Mendes']);

        $mail = (new BridgeSuggestedNotification($other, 'precificação', false))->toMail($notifiable);
        $body = implode(' ', $mail->introLines);

        $this->assertStringContainsString('Ricardo Mendes', $body);
        $this->assertStringContainsString('quer aprender sobre precificação', $body);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Unit/Notifications/BridgeSuggestedNotificationTest.php`
Expected: FAIL — `BridgeSuggestedNotification` doesn't exist yet.

- [ ] **Step 3: Create the notification class**

Create `app/Notifications/BridgeSuggestedNotification.php`:

```php
<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BridgeSuggestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $otherMember,
        public string $tag,
        public bool $iAmTheLearner,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $line = $this->iAmTheLearner
            ? "O Douglas te apresentou a {$this->otherMember->name}, que pode te ajudar com {$this->tag}."
            : "O Douglas te apresentou a {$this->otherMember->name}, que quer aprender sobre {$this->tag} — e você pode ajudar.";

        return (new MailMessage)
            ->subject('Uma ponte foi feita pra você')
            ->greeting("Oi, {$notifiable->name}!")
            ->line($line)
            ->action('Ver pessoas do CLUB', route('membros.pessoas'));
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Unit/Notifications/BridgeSuggestedNotificationTest.php`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Notifications/BridgeSuggestedNotification.php tests/Unit/Notifications/BridgeSuggestedNotificationTest.php
git commit -m "feat: add BridgeSuggestedNotification (issue #41)"
```

---

## Task 2: `Radar::suggestedBridges()` + view section

**Files:**
- Modify: `app/Livewire/Membros/Radar.php`
- Modify: `resources/views/livewire/membros/radar.blade.php`
- Modify: `resources/css/app.css`
- Test: `tests/Feature/Membros/RadarTest.php`

**Interfaces:**
- Produces: `#[Computed] Radar::suggestedBridges(): Collection` — each element is `['learner' => User, 'teacher' => User, 'tag' => string]`. Task 3 depends on this exact shape and the `wire:click="makeBridge(...)"` markup this task adds to the button (Task 3 implements the method the button calls).

- [ ] **Step 1: Write the failing tests**

Append these methods to `tests/Feature/Membros/RadarTest.php` (inside the class, anywhere after the `encontro()` helper — e.g. right after `test_briefing_shows_placeholders_when_there_is_no_note_or_commitment_yet`):

```php
    public function test_suggested_bridges_shows_a_match_when_tags_overlap_case_insensitively(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));
        User::factory()->create(['tier' => 'club', 'name' => 'Ricardo Mendes', 'learn_tags' => ['Precificação']]);
        User::factory()->create(['tier' => 'club', 'name' => 'Marina Alves', 'teach_tags' => ['precificação']]);

        Livewire::test(Radar::class)
            ->assertSee('Pontes sugeridas')
            ->assertSee('Ricardo Mendes')
            ->assertSee('Marina Alves')
            ->assertSee('Precificação')
            ->assertDontSee('Nenhuma ponte sugerida no momento.');
    }

    public function test_suggested_bridges_shows_the_empty_state_when_there_are_no_matches(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));
        User::factory()->create(['tier' => 'club', 'name' => 'Ricardo Mendes', 'learn_tags' => ['Vendas']]);
        User::factory()->create(['tier' => 'club', 'name' => 'Marina Alves', 'teach_tags' => ['Marketing']]);

        Livewire::test(Radar::class)->assertSee('Nenhuma ponte sugerida no momento.');
    }

    public function test_suggested_bridges_excludes_a_pair_with_an_existing_bridge_request(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));
        $learner = User::factory()->create(['tier' => 'club', 'name' => 'Ricardo Mendes', 'learn_tags' => ['Precificação']]);
        $teacher = User::factory()->create(['tier' => 'club', 'name' => 'Marina Alves', 'teach_tags' => ['precificação']]);
        BridgeRequest::create(['requester_id' => $learner->id, 'target_id' => $teacher->id]);

        Livewire::test(Radar::class)->assertSee('Nenhuma ponte sugerida no momento.');
    }

    public function test_suggested_bridges_excludes_members_without_matching_tags(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));
        User::factory()->create(['tier' => 'club', 'name' => 'Sem Tags']);
        User::factory()->create(['tier' => 'club', 'name' => 'Também Sem Tags']);

        Livewire::test(Radar::class)->assertSee('Nenhuma ponte sugerida no momento.');
    }

    public function test_suggested_bridges_caps_results_at_three(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));

        for ($i = 1; $i <= 4; $i++) {
            User::factory()->create(['tier' => 'club', 'name' => "Aluno {$i}", 'learn_tags' => ["assunto-{$i}"]]);
            User::factory()->create(['tier' => 'club', 'name' => "Professor {$i}", 'teach_tags' => ["assunto-{$i}"]]);
        }

        $matches = Livewire::test(Radar::class)->instance()->suggestedBridges();

        $this->assertCount(3, $matches);
    }
```

Add the import `use App\Models\BridgeRequest;` to the top of `tests/Feature/Membros/RadarTest.php` (alphabetically, between the `use App\Livewire\Membros\Radar;` line and `use App\Models\Course;`).

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/Membros/RadarTest.php`
Expected: FAIL — `suggestedBridges()` doesn't exist yet, and the "Pontes sugeridas" section isn't in the view.

- [ ] **Step 3: Add the computed property**

In `app/Livewire/Membros/Radar.php`, add the import `use App\Models\BridgeRequest;` right after `use App\Livewire\Concerns\ComputesUserInitials;` and before `use App\Models\EncontroFeedback;` (alphabetical order):

```php
use App\Livewire\Concerns\ComputesUserInitials;
use App\Models\BridgeRequest;
use App\Models\EncontroFeedback;
```

Add the computed property, right after `overdueMembers()` and before `lastNoteFor()`:

```php
    #[Computed]
    public function suggestedBridges(): Collection
    {
        $members = User::query()
            ->where('tier', 'club')
            ->get(['id', 'name', 'teach_tags', 'learn_tags']);

        $connectedPairs = BridgeRequest::query()
            ->get(['requester_id', 'target_id'])
            ->flatMap(fn (BridgeRequest $br) => ["{$br->requester_id}-{$br->target_id}", "{$br->target_id}-{$br->requester_id}"])
            ->flip();

        $matches = collect();

        foreach ($members as $learner) {
            foreach ($members as $teacher) {
                if ($learner->id === $teacher->id) {
                    continue;
                }

                if (isset($connectedPairs["{$learner->id}-{$teacher->id}"])) {
                    continue;
                }

                $matchedTag = collect($learner->learn_tags ?? [])
                    ->first(fn (string $tag) => collect($teacher->teach_tags ?? [])
                        ->contains(fn (string $t) => mb_strtolower($t) === mb_strtolower($tag)));

                if ($matchedTag !== null) {
                    $matches->push([
                        'learner' => $learner,
                        'teacher' => $teacher,
                        'tag' => $matchedTag,
                    ]);
                }
            }
        }

        return $matches->take(3);
    }
```

- [ ] **Step 4: Add the view section**

In `resources/views/livewire/membros/radar.blade.php`, insert this new section right after the closing `</div>` of the `.kpis` block (i.e., right before the existing `<h3 class="text-[17px] font-semibold mb-3">Antes das sessões de hoje</h3>` line):

```blade
        <h3 class="text-[17px] font-semibold mb-3">Pontes sugeridas</h3>
        @forelse ($this->suggestedBridges as $match)
            <div class="match rounded-[18px] border border-sand bg-card shadow-[0_1px_2px_rgba(11,11,12,.05),0_10px_28px_rgba(11,11,12,.07)]">
                <div class="duo">
                    <div class="avatar">{{ $match['learner']->initials }}</div>
                    <div class="avatar o">{{ $match['teacher']->initials }}</div>
                </div>
                <div class="d">
                    <b>{{ $match['learner']->name }}</b> quer aprender <em>{{ $match['tag'] }}</em> e <b>{{ $match['teacher']->name }}</b> pode ensinar isso.
                </div>
                <button type="button" wire:click="makeBridge({{ $match['learner']->id }}, {{ $match['teacher']->id }}, '{{ $match['tag'] }}')"
                        class="px-3.5 py-1.5 rounded-full text-sm font-semibold bg-black text-white hover:bg-brand">
                    Fazer a ponte
                </button>
            </div>
        @empty
            <p class="text-stone mb-6">Nenhuma ponte sugerida no momento.</p>
        @endforelse

```

The `wire:click="makeBridge(...)"` call references a method that doesn't exist yet (added in Task 3) — this is fine, Livewire only resolves the method name when the button is actually clicked, not at render time. This task's tests never click the button, only assert the rendered text.

- [ ] **Step 5: Add the CSS**

In `resources/css/app.css`, right after the `.briefing-card { border-left-color: theme('colors.brand'); }` line, add:

```css
.match { padding: 18px 22px; display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin-bottom: 12px; }
.match .duo { display: flex; align-items: center; }
.match .duo .avatar:last-child { margin-left: -12px; border: 2px solid theme('colors.card'); }
.match .d { flex: 1; min-width: 220px; font-size: 14px; }
.match .d em { font-style: normal; color: theme('colors.brand'); font-weight: 700; }
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/Membros/RadarTest.php`
Expected: PASS (all tests, including the 5 new ones)

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/Membros/Radar.php resources/views/livewire/membros/radar.blade.php resources/css/app.css tests/Feature/Membros/RadarTest.php
git commit -m "feat: show suggested bridges on the Radar page (issue #41)"
```

---

## Task 3: `Radar::makeBridge()` action

**Files:**
- Modify: `app/Livewire/Membros/Radar.php`
- Test: `tests/Feature/Membros/RadarTest.php`

**Interfaces:**
- Consumes: `App\Notifications\BridgeSuggestedNotification(User $otherMember, string $tag, bool $iAmTheLearner)` (Task 1); `Radar::suggestedBridges()` (Task 2, for the `unset()` cache-bust).
- Produces: `Radar::makeBridge(int $learnerId, int $teacherId, string $tag): void` — the method the Task 2 button's `wire:click` already calls.

- [ ] **Step 1: Write the failing tests**

Append these methods to `tests/Feature/Membros/RadarTest.php`, after the `test_suggested_bridges_caps_results_at_three` test added in Task 2:

```php
    public function test_make_bridge_creates_a_bridge_request_and_notifies_both_members(): void
    {
        Notification::fake();

        $this->actingAs(User::factory()->create(['tier' => 'mentor']));
        $learner = User::factory()->create(['tier' => 'club', 'name' => 'Ricardo Mendes']);
        $teacher = User::factory()->create(['tier' => 'club', 'name' => 'Marina Alves']);

        Livewire::test(Radar::class)->call('makeBridge', $learner->id, $teacher->id, 'precificação');

        $this->assertDatabaseHas('bridge_requests', [
            'requester_id' => $learner->id, 'target_id' => $teacher->id,
        ]);

        Notification::assertSentTo($learner, BridgeSuggestedNotification::class, function ($notification) use ($teacher) {
            return $notification->otherMember->is($teacher) && $notification->iAmTheLearner === true;
        });
        Notification::assertSentTo($teacher, BridgeSuggestedNotification::class, function ($notification) use ($learner) {
            return $notification->otherMember->is($learner) && $notification->iAmTheLearner === false;
        });
    }

    public function test_make_bridge_is_a_no_op_when_the_pair_is_already_connected(): void
    {
        Notification::fake();

        $this->actingAs(User::factory()->create(['tier' => 'mentor']));
        $learner = User::factory()->create(['tier' => 'club']);
        $teacher = User::factory()->create(['tier' => 'club']);
        BridgeRequest::create(['requester_id' => $learner->id, 'target_id' => $teacher->id]);

        Livewire::test(Radar::class)->call('makeBridge', $learner->id, $teacher->id, 'precificação');

        $this->assertSame(1, BridgeRequest::query()->count());
        Notification::assertNothingSent();
    }

    public function test_make_bridge_is_a_no_op_when_a_user_is_not_club_tier(): void
    {
        Notification::fake();

        $this->actingAs(User::factory()->create(['tier' => 'mentor']));
        $learner = User::factory()->create(['tier' => 'club']);
        $notClub = User::factory()->create(['tier' => 'start']);

        Livewire::test(Radar::class)->call('makeBridge', $learner->id, $notClub->id, 'precificação');

        $this->assertDatabaseMissing('bridge_requests', ['requester_id' => $learner->id]);
        Notification::assertNothingSent();
    }

    public function test_make_bridge_removes_the_pair_from_future_suggestions(): void
    {
        Notification::fake();

        $this->actingAs(User::factory()->create(['tier' => 'mentor']));
        $learner = User::factory()->create(['tier' => 'club', 'name' => 'Ricardo Mendes', 'learn_tags' => ['Precificação']]);
        $teacher = User::factory()->create(['tier' => 'club', 'name' => 'Marina Alves', 'teach_tags' => ['precificação']]);

        Livewire::test(Radar::class)
            ->call('makeBridge', $learner->id, $teacher->id, 'Precificação')
            ->assertSee('Nenhuma ponte sugerida no momento.');
    }
```

Add these two imports to `tests/Feature/Membros/RadarTest.php` (alphabetically): `use App\Notifications\BridgeSuggestedNotification;` (after the `use App\Models\User;` line) and `use Illuminate\Support\Facades\Notification;` (after `use Illuminate\Foundation\Testing\RefreshDatabase;`).

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/Membros/RadarTest.php`
Expected: FAIL — `makeBridge()` doesn't exist yet.

- [ ] **Step 3: Add the action**

In `app/Livewire/Membros/Radar.php`, add the import (alphabetically, after `use App\Models\User;`):

```php
use App\Notifications\BridgeSuggestedNotification;
```

Add the method right after `suggestedBridges()` and before `lastNoteFor()`:

```php
    public function makeBridge(int $learnerId, int $teacherId, string $tag): void
    {
        $learner = User::query()->where('id', $learnerId)->where('tier', 'club')->first();
        $teacher = User::query()->where('id', $teacherId)->where('tier', 'club')->first();

        if (! $learner || ! $teacher) {
            return;
        }

        $alreadyConnected = BridgeRequest::query()
            ->where(fn ($q) => $q->where('requester_id', $learnerId)->where('target_id', $teacherId))
            ->orWhere(fn ($q) => $q->where('requester_id', $teacherId)->where('target_id', $learnerId))
            ->exists();

        if ($alreadyConnected) {
            return;
        }

        BridgeRequest::create(['requester_id' => $learnerId, 'target_id' => $teacherId]);

        $learner->notify(new BridgeSuggestedNotification($teacher, $tag, iAmTheLearner: true));
        $teacher->notify(new BridgeSuggestedNotification($learner, $tag, iAmTheLearner: false));

        unset($this->suggestedBridges);
    }
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/Membros/RadarTest.php`
Expected: PASS (all tests)

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`
Expected: PASS, no regressions.

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/Membros/Radar.php tests/Feature/Membros/RadarTest.php
git commit -m "feat: wire the makeBridge action to notify both matched members (issue #41)"
```
