# Radar Engaged Start Members Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close GitHub issue #50 — show an aggregate card on the Radar page listing `tier=start` members who have completed every `tier=start` lesson and downloaded 2+ distinct frameworks, a signal they're ready for a personal CLUB invite.

**Architecture:** A new `framework_downloads` log table (written to by the existing download controller) supplies the "downloaded 2+ frameworks" signal; the existing `LessonProgress` table (already populated by the existing lesson-completion flow) supplies the "watched every lesson" signal. A new `Radar::engagedStartMembers()` computed property cross-references both and renders as one aggregate card in the same "Pontes sugeridas" section built in issue #41.

**Tech Stack:** Laravel 13, Livewire 3, Tailwind CSS v3, PHPUnit (`php artisan test`), SQLite (`:memory:` in tests).

**Spec:** `docs/superpowers/specs/2026-08-30-radar-engaged-start-members-design.md`

## Global Constraints

- "Assistiu todas as aulas" is literal — every `Lesson` with `tier='start'` and a non-null `published_at` needs a matching `LessonProgress` row with `status='completed'` for that member. No partial-credit threshold.
- "Baixou 2+ frameworks" counts DISTINCT `framework_id` in `framework_downloads`, not raw download rows — downloading the same framework twice never counts as 2.
- No artificial cap on the number of members shown (unlike the #41 match cards, which cap at 3 for visual density) — this card lists everyone who qualifies.
- No new "invite" action/CTA — the card is purely informational, there is no mentor-initiated invite flow in this app (the real upgrade path is the member's own Upgrade page, issue #24).
- `framework_downloads` rows cascade-delete when their `user`/`framework` is deleted (`cascadeOnDelete()`), matching every other log-table foreign key pattern already in this project.

---

## Task 1: Framework download tracking

**Files:**
- Create: `database/migrations/2026_08_30_210000_create_framework_downloads_table.php`
- Create: `app/Models/FrameworkDownload.php`
- Modify: `app/Http/Controllers/Membros/FrameworkPdfDownloadController.php`
- Test: `tests/Feature/Membros/FrameworkPdfDownloadTest.php`

**Interfaces:**
- Produces: `App\Models\FrameworkDownload` (`$fillable = ['user_id', 'framework_id']`, `user(): BelongsTo`, `framework(): BelongsTo`). Task 2 depends on this exact class name and the `framework_downloads` table's `user_id`/`framework_id` columns.

- [ ] **Step 1: Write the failing tests**

Read the current contents of `tests/Feature/Membros/FrameworkPdfDownloadTest.php` first — it has an existing `private function framework(array $overrides = []): Framework` helper and 4 existing tests. Append these 2 new tests inside the class, after `test_start_tier_member_can_download`:

```php
    public function test_download_records_a_framework_download(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->create('4s.pdf', 10, 'application/pdf')->store('framework-pdfs', 'public');
        $framework = $this->framework(['pdf_path' => $path]);
        $user = User::factory()->create(['tier' => 'start']);

        $this->actingAs($user);
        $this->get(route('membros.frameworks.download', $framework));

        $this->assertDatabaseHas('framework_downloads', [
            'user_id' => $user->id, 'framework_id' => $framework->id,
        ]);
    }

    public function test_downloading_the_same_framework_twice_records_two_rows(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->create('4s.pdf', 10, 'application/pdf')->store('framework-pdfs', 'public');
        $framework = $this->framework(['pdf_path' => $path]);
        $user = User::factory()->create(['tier' => 'start']);

        $this->actingAs($user);
        $this->get(route('membros.frameworks.download', $framework));
        $this->get(route('membros.frameworks.download', $framework));

        $this->assertSame(2, FrameworkDownload::query()
            ->where('user_id', $user->id)
            ->where('framework_id', $framework->id)
            ->count());
    }
```

Add the import `use App\Models\FrameworkDownload;` to the top of the file, alphabetically (right after `use App\Models\Framework;`).

Also modify the two existing 404 tests to assert nothing was recorded. Change `test_returns_404_without_an_uploaded_file`:

```php
    public function test_returns_404_without_an_uploaded_file(): void
    {
        $framework = $this->framework(['pdf_url' => 'https://example.com/4s.pdf']);

        $this->actingAs(User::factory()->create());

        $this->get(route('membros.frameworks.download', $framework))
            ->assertNotFound();

        $this->assertDatabaseMissing('framework_downloads', ['framework_id' => $framework->id]);
    }
```

And `test_returns_404_when_the_file_is_missing_from_disk`:

```php
    public function test_returns_404_when_the_file_is_missing_from_disk(): void
    {
        Storage::fake('public');

        $framework = $this->framework(['pdf_path' => 'framework-pdfs/does-not-exist.pdf']);

        $this->actingAs(User::factory()->create());

        $this->get(route('membros.frameworks.download', $framework))
            ->assertNotFound();

        $this->assertDatabaseMissing('framework_downloads', ['framework_id' => $framework->id]);
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/Membros/FrameworkPdfDownloadTest.php`
Expected: FAIL — `framework_downloads` table and `FrameworkDownload` model don't exist yet.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_08_30_210000_create_framework_downloads_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('framework_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('framework_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('framework_downloads');
    }
};
```

- [ ] **Step 4: Create the model**

Create `app/Models/FrameworkDownload.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FrameworkDownload extends Model
{
    protected $fillable = [
        'user_id',
        'framework_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function framework(): BelongsTo
    {
        return $this->belongsTo(Framework::class);
    }
}
```

- [ ] **Step 5: Wire the controller**

In `app/Http/Controllers/Membros/FrameworkPdfDownloadController.php`, change:

```php
<?php

namespace App\Http\Controllers\Membros;

use App\Http\Controllers\Controller;
use App\Models\Framework;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FrameworkPdfDownloadController extends Controller
{
    public function __invoke(Framework $framework): StreamedResponse
    {
        abort_unless(
            $framework->hasUploadedFile() && Storage::disk('public')->exists($framework->pdf_path),
            404,
        );

        $extension = pathinfo($framework->pdf_path, PATHINFO_EXTENSION);
        $filename = str_replace(['/', '\\'], '-', $framework->title);

        return Storage::disk('public')->download(
            $framework->pdf_path,
            "{$filename}.{$extension}",
        );
    }
}
```

to:

```php
<?php

namespace App\Http\Controllers\Membros;

use App\Http\Controllers\Controller;
use App\Models\Framework;
use App\Models\FrameworkDownload;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FrameworkPdfDownloadController extends Controller
{
    public function __invoke(Framework $framework): StreamedResponse
    {
        abort_unless(
            $framework->hasUploadedFile() && Storage::disk('public')->exists($framework->pdf_path),
            404,
        );

        FrameworkDownload::create([
            'user_id' => request()->user()->id,
            'framework_id' => $framework->id,
        ]);

        $extension = pathinfo($framework->pdf_path, PATHINFO_EXTENSION);
        $filename = str_replace(['/', '\\'], '-', $framework->title);

        return Storage::disk('public')->download(
            $framework->pdf_path,
            "{$filename}.{$extension}",
        );
    }
}
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/Membros/FrameworkPdfDownloadTest.php`
Expected: PASS (6 tests — 4 existing + 2 new)

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_30_210000_create_framework_downloads_table.php app/Models/FrameworkDownload.php app/Http/Controllers/Membros/FrameworkPdfDownloadController.php tests/Feature/Membros/FrameworkPdfDownloadTest.php
git commit -m "feat: track framework downloads (issue #50)"
```

---

## Task 2: `Radar::engagedStartMembers()` + view

**Files:**
- Modify: `app/Livewire/Membros/Radar.php`
- Modify: `resources/views/livewire/membros/radar.blade.php`
- Modify: `tests/Feature/Membros/RadarTest.php`

**Interfaces:**
- Consumes: `App\Models\FrameworkDownload` (Task 1).
- Produces: `#[Computed] Radar::engagedStartMembers(): Collection<User>`. Nothing later depends on this — final task.

- [ ] **Step 1: Write the failing tests**

In `tests/Feature/Membros/RadarTest.php`, change the existing `lesson()` helper to accept overrides (this is backward compatible — the 2 existing call sites, `$this->lesson()` with no arguments, keep working unchanged):

```php
    private function lesson(array $overrides = []): Lesson
    {
        $course = Course::create(['label' => 'Curso 1', 'title' => 'Vendas', 'position' => 10]);

        return Lesson::create(array_merge([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula 1',
            'video_provider' => 'vimeo', 'video_id' => '76979871', 'published_at' => '2026-01-01', 'position' => 1,
        ], $overrides));
    }
```

Change the top import block from:

```php
use App\Livewire\Membros\Radar;
use App\Models\BridgeRequest;
use App\Models\Course;
use App\Models\Encontro;
use App\Models\EncontroFeedback;
use App\Models\Lesson;
use App\Models\LessonFeedback;
use App\Models\MentorCommitment;
use App\Models\MentorNote;
use App\Models\MentorSession;
use App\Models\User;
use App\Notifications\BridgeSuggestedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;
```

to:

```php
use App\Livewire\Membros\Radar;
use App\Models\BridgeRequest;
use App\Models\Course;
use App\Models\Encontro;
use App\Models\EncontroFeedback;
use App\Models\Framework;
use App\Models\FrameworkDownload;
use App\Models\Lesson;
use App\Models\LessonFeedback;
use App\Models\LessonProgress;
use App\Models\MentorCommitment;
use App\Models\MentorNote;
use App\Models\MentorSession;
use App\Models\User;
use App\Notifications\BridgeSuggestedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;
```

(This adds `Framework`, `FrameworkDownload`, and `LessonProgress` — everything else in the block is unchanged from the file's current state.)

Append these tests to the class, anywhere after the Task 1 (#41) tests already in the file — e.g. right after `test_make_bridge_removes_the_pair_from_future_suggestions`:

```php
    public function test_engaged_start_member_shown_when_all_lessons_completed_and_two_frameworks_downloaded(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));
        $member = User::factory()->create(['tier' => 'start', 'name' => 'Ana Beatriz']);
        $lesson = $this->lesson(['tier' => 'start']);

        LessonProgress::create(['user_id' => $member->id, 'lesson_id' => $lesson->id, 'status' => 'completed']);
        FrameworkDownload::create(['user_id' => $member->id, 'framework_id' => Framework::create(['code' => 'A', 'title' => 'Framework A', 'description' => 'x', 'position' => 1])->id]);
        FrameworkDownload::create(['user_id' => $member->id, 'framework_id' => Framework::create(['code' => 'B', 'title' => 'Framework B', 'description' => 'x', 'position' => 2])->id]);

        Livewire::test(Radar::class)
            ->assertSee('1 membro Start')
            ->assertSee('Ana Beatriz')
            ->assertSee('Prontos para o convite ao CLUB.');
    }

    public function test_engaged_start_member_not_shown_when_only_one_framework_downloaded(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));
        $member = User::factory()->create(['tier' => 'start', 'name' => 'Carla Nunes']);
        $lesson = $this->lesson(['tier' => 'start']);

        LessonProgress::create(['user_id' => $member->id, 'lesson_id' => $lesson->id, 'status' => 'completed']);
        FrameworkDownload::create(['user_id' => $member->id, 'framework_id' => Framework::create(['code' => 'A', 'title' => 'Framework A', 'description' => 'x', 'position' => 1])->id]);

        Livewire::test(Radar::class)->assertDontSee('Carla Nunes');
    }

    public function test_engaged_start_member_not_shown_when_not_all_lessons_completed(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));
        $member = User::factory()->create(['tier' => 'start', 'name' => 'Diego Ramos']);
        $this->lesson(['tier' => 'start']);
        $this->lesson(['tier' => 'start', 'number' => 2]);
        $completedLesson = Lesson::query()->where('number', 1)->first();

        LessonProgress::create(['user_id' => $member->id, 'lesson_id' => $completedLesson->id, 'status' => 'completed']);
        FrameworkDownload::create(['user_id' => $member->id, 'framework_id' => Framework::create(['code' => 'A', 'title' => 'Framework A', 'description' => 'x', 'position' => 1])->id]);
        FrameworkDownload::create(['user_id' => $member->id, 'framework_id' => Framework::create(['code' => 'B', 'title' => 'Framework B', 'description' => 'x', 'position' => 2])->id]);

        Livewire::test(Radar::class)->assertDontSee('Diego Ramos');
    }

    public function test_repeated_download_of_the_same_framework_does_not_count_as_two(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));
        $member = User::factory()->create(['tier' => 'start', 'name' => 'Elton Braga']);
        $lesson = $this->lesson(['tier' => 'start']);
        $framework = Framework::create(['code' => 'A', 'title' => 'Framework A', 'description' => 'x', 'position' => 1]);

        LessonProgress::create(['user_id' => $member->id, 'lesson_id' => $lesson->id, 'status' => 'completed']);
        FrameworkDownload::create(['user_id' => $member->id, 'framework_id' => $framework->id]);
        FrameworkDownload::create(['user_id' => $member->id, 'framework_id' => $framework->id]);

        Livewire::test(Radar::class)->assertDontSee('Elton Braga');
    }

    public function test_no_engaged_start_members_card_when_there_are_no_start_lessons(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));

        Livewire::test(Radar::class)->assertDontSee('Prontos para o convite ao CLUB.');
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/Membros/RadarTest.php`
Expected: FAIL — `engagedStartMembers()` doesn't exist yet, and the card isn't in the view.

- [ ] **Step 3: Add the computed property**

In `app/Livewire/Membros/Radar.php`, change the import block from:

```php
use App\Livewire\Concerns\ComputesUserInitials;
use App\Models\BridgeRequest;
use App\Models\EncontroFeedback;
use App\Models\LessonFeedback;
use App\Models\MentorCommitment;
use App\Models\MentorNote;
use App\Models\MentorSession;
use App\Models\User;
use App\Notifications\BridgeSuggestedNotification;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
```

to:

```php
use App\Livewire\Concerns\ComputesUserInitials;
use App\Models\BridgeRequest;
use App\Models\EncontroFeedback;
use App\Models\FrameworkDownload;
use App\Models\Lesson;
use App\Models\LessonFeedback;
use App\Models\LessonProgress;
use App\Models\MentorCommitment;
use App\Models\MentorNote;
use App\Models\MentorSession;
use App\Models\User;
use App\Notifications\BridgeSuggestedNotification;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
```

Add the computed property right after `makeBridge()` and before `lastNoteFor()`:

```php
    #[Computed]
    public function engagedStartMembers(): Collection
    {
        $startLessonIds = Lesson::query()
            ->where('tier', 'start')
            ->whereNotNull('published_at')
            ->pluck('id');

        if ($startLessonIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->where('tier', 'start')
            ->get()
            ->filter(function (User $member) use ($startLessonIds) {
                $completedCount = LessonProgress::query()
                    ->where('user_id', $member->id)
                    ->where('status', 'completed')
                    ->whereIn('lesson_id', $startLessonIds)
                    ->count();

                if ($completedCount < $startLessonIds->count()) {
                    return false;
                }

                $distinctFrameworksDownloaded = FrameworkDownload::query()
                    ->where('user_id', $member->id)
                    ->distinct('framework_id')
                    ->count('framework_id');

                return $distinctFrameworksDownloaded >= 2;
            })
            ->values();
    }
```

- [ ] **Step 4: Add the view**

In `resources/views/livewire/membros/radar.blade.php`, insert this block right after the `@endforelse` that closes the `suggestedBridges` loop (currently line 66) and before the `<h3 class="text-[17px] font-semibold mt-6 mb-3">Antes das sessões de hoje</h3>` line:

```blade

        @if ($this->engagedStartMembers->isNotEmpty())
            <div class="match rounded-[18px] border border-sand bg-card shadow-[0_1px_2px_rgba(11,11,12,.05),0_10px_28px_rgba(11,11,12,.07)]">
                <div class="d">
                    <b>{{ $this->engagedStartMembers->count() }} {{ Str::plural('membro', $this->engagedStartMembers->count()) }} Start</b>
                    assistiram todas as aulas e baixaram 2+ frameworks: {{ $this->engagedStartMembers->pluck('name')->join(', ') }}.
                    <em>Prontos para o convite ao CLUB.</em>
                </div>
            </div>
        @endif
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/Membros/RadarTest.php`
Expected: PASS (all tests, including the 5 new ones)

- [ ] **Step 6: Run the full suite**

Run: `php artisan test`
Expected: PASS, no regressions.

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/Membros/Radar.php resources/views/livewire/membros/radar.blade.php tests/Feature/Membros/RadarTest.php
git commit -m "feat: show engaged Start members ready for CLUB invite on the Radar page (issue #50)"
```
