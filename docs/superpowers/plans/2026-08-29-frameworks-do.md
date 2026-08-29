# Frameworks DO Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the real `/membros/frameworks` page (closes lista-spec item #2), matching `prototype/frameworks.blade.php` with a real, admin-manageable `Framework` model — a PDF download and an optional real link to a specific `Lesson` that deep-opens in the Biblioteca de aulas player.

**Architecture:** `Framework` is a small, standalone model (no tier — confirmed the prototype shows every framework to every persona) with the exact same "upload OR external URL" file pattern `LessonMaterial` already uses, so its download controller and Filament form are near-mirrors of existing code rather than new patterns. The one piece of new mechanism is `Aulas::mount()` accepting an optional `?lesson=` query param — via trait method aliasing (`use TracksLessonProgress { mount as protected traitMount; }`) so the existing "featured lesson" logic isn't duplicated, just overridden when a valid, available lesson is requested.

**Tech Stack:** Laravel 13, Livewire 3, Filament 4, Tailwind CSS v3, PHPUnit (`php artisan test`), SQLite (`:memory:` in tests).

**Spec:** `docs/superpowers/specs/2026-08-29-frameworks-do-design.md`

## Global Constraints

- `Framework` has NO tier field — every authenticated member (start or club) sees every framework. Only `auth`, `verified`, `active` middleware, never `tier:*`.
- The "Baixar PDF" button has 3 states, in this priority order: uploaded file → download route; else `pdf_url` set → direct external link; else → disabled "PDF em breve".
- "Ver aula" only renders when `lesson_id` is set AND the linked `Lesson` still exists.
- The `?lesson=` deep-link on `/membros/aulas` must never error or leak: an invalid, missing, or tier-gated lesson ID silently falls back to the existing default-featured-lesson behavior.
- No fabricated content — this plan only wires up real data entered through the new Filament resource.

---

## Task 1: `Framework` model + migration

**Files:**
- Create: `database/migrations/2026_08_29_140000_create_frameworks_table.php`
- Create: `app/Models/Framework.php`
- Test: `tests/Unit/FrameworkTest.php`

**Interfaces:**
- Produces: `Framework` model with `$fillable` (`code`, `title`, `description`, `pdf_url`, `pdf_path`, `lesson_id`, `position`), `hasUploadedFile(): bool`, `lesson(): BelongsTo`. Task 2 (download controller), Task 4 (Livewire page/card), and Task 5 (Filament resource) all depend on this.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/FrameworkTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Framework;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrameworkTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_uploaded_file_is_true_when_pdf_path_is_set(): void
    {
        $framework = Framework::create([
            'code' => '4S', 'title' => 'Consumidor 4S', 'description' => 'Teste',
            'pdf_path' => 'framework-pdfs/4s.pdf', 'position' => 10,
        ]);

        $this->assertTrue($framework->hasUploadedFile());
    }

    public function test_has_uploaded_file_is_false_when_pdf_path_is_null(): void
    {
        $framework = Framework::create([
            'code' => '4S', 'title' => 'Consumidor 4S', 'description' => 'Teste',
            'pdf_url' => 'https://example.com/4s.pdf', 'position' => 10,
        ]);

        $this->assertFalse($framework->hasUploadedFile());
    }

    public function test_lesson_relationship_resolves(): void
    {
        $course = Course::create(['label' => 'Curso', 'title' => 'Teste', 'position' => 10]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula vinculada',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
        ]);
        $framework = Framework::create([
            'code' => '4S', 'title' => 'Consumidor 4S', 'description' => 'Teste',
            'lesson_id' => $lesson->id, 'position' => 10,
        ]);

        $this->assertTrue($framework->lesson->is($lesson));
    }

    public function test_lesson_id_is_nulled_when_the_linked_lesson_is_deleted(): void
    {
        $course = Course::create(['label' => 'Curso', 'title' => 'Teste', 'position' => 10]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula a apagar',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
        ]);
        $framework = Framework::create([
            'code' => '4S', 'title' => 'Consumidor 4S', 'description' => 'Teste',
            'lesson_id' => $lesson->id, 'position' => 10,
        ]);

        $lesson->delete();

        $this->assertNull($framework->fresh()->lesson_id);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Unit/FrameworkTest.php`
Expected: FAIL — `Framework` class and `frameworks` table don't exist yet.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_08_29_140000_create_frameworks_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frameworks', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('title');
            $table->text('description');
            $table->string('pdf_url')->nullable();
            $table->string('pdf_path')->nullable();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frameworks');
    }
};
```

- [ ] **Step 4: Create the model**

Create `app/Models/Framework.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Framework extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'title',
        'description',
        'pdf_url',
        'pdf_path',
        'lesson_id',
        'position',
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function hasUploadedFile(): bool
    {
        return filled($this->pdf_path);
    }
}
```

- [ ] **Step 5: Run migration and tests**

Run: `php artisan migrate`
Run: `php artisan test tests/Unit/FrameworkTest.php`
Expected: PASS (all 4 tests).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_29_140000_create_frameworks_table.php app/Models/Framework.php tests/Unit/FrameworkTest.php
git commit -m "feat: add Framework model and migration"
```

---

## Task 2: PDF download route

**Files:**
- Create: `app/Http/Controllers/Membros/FrameworkPdfDownloadController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Membros/FrameworkPdfDownloadTest.php`

**Interfaces:**
- Consumes: `Framework::hasUploadedFile()` (Task 1).
- Produces: named route `membros.frameworks.download`. No tier check — per the Global Constraints, this route only requires `auth`, `verified`, `active`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Membros/FrameworkPdfDownloadTest.php`:

```php
<?php

namespace Tests\Feature\Membros;

use App\Models\Framework;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FrameworkPdfDownloadTest extends TestCase
{
    use RefreshDatabase;

    private function framework(array $overrides = []): Framework
    {
        return Framework::create(array_merge([
            'code' => '4S', 'title' => 'Consumidor 4S', 'description' => 'Teste', 'position' => 10,
        ], $overrides));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $framework = $this->framework(['pdf_path' => 'framework-pdfs/x.pdf']);

        $this->get(route('membros.frameworks.download', $framework))
            ->assertRedirect(route('login'));
    }

    public function test_start_tier_member_can_download(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->create('4s.pdf', 10, 'application/pdf')
            ->store('framework-pdfs', 'public');

        $framework = $this->framework(['pdf_path' => $path]);

        $this->actingAs(User::factory()->create(['tier' => 'start']));

        $this->get(route('membros.frameworks.download', $framework))
            ->assertOk()
            ->assertDownload('Consumidor 4S.pdf');
    }

    public function test_returns_404_without_an_uploaded_file(): void
    {
        $framework = $this->framework(['pdf_url' => 'https://example.com/4s.pdf']);

        $this->actingAs(User::factory()->create());

        $this->get(route('membros.frameworks.download', $framework))
            ->assertNotFound();
    }

    public function test_returns_404_when_the_file_is_missing_from_disk(): void
    {
        Storage::fake('public');

        $framework = $this->framework(['pdf_path' => 'framework-pdfs/does-not-exist.pdf']);

        $this->actingAs(User::factory()->create());

        $this->get(route('membros.frameworks.download', $framework))
            ->assertNotFound();
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Membros/FrameworkPdfDownloadTest.php`
Expected: FAIL — route `membros.frameworks.download` doesn't exist.

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Membros/FrameworkPdfDownloadController.php`:

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

(No `isAvailableFor()`-style gate here — matches the Global Constraint that frameworks have no tier.)

- [ ] **Step 4: Register the route**

In `routes/web.php`, add the import:

```php
use App\Http\Controllers\Membros\FrameworkPdfDownloadController;
```

Then, after the `membros.materials.download` route, add:

```php
Route::get('membros/frameworks/{framework}/download', FrameworkPdfDownloadController::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('membros.frameworks.download');
```

**Known unrelated concurrent work:** `routes/web.php` may have an uncommitted trailing
`require __DIR__.'/prototype.php';` line from unrelated work-in-progress — not yours to touch. Before
committing, run `git diff routes/web.php` and confirm your staged change is only the import + this
one route; if the trailing `require` line is present, either use `git add -p` to stage only your hunk,
or temporarily remove that line, commit, and restore it afterward (uncommitted).

- [ ] **Step 5: Run the tests**

Run: `php artisan test tests/Feature/Membros/FrameworkPdfDownloadTest.php`
Expected: PASS (all 4 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Membros/FrameworkPdfDownloadController.php routes/web.php tests/Feature/Membros/FrameworkPdfDownloadTest.php
git commit -m "feat: add framework PDF download route"
```

---

## Task 3: `?lesson=` deep-link on the Aulas page

**Files:**
- Modify: `app/Livewire/Membros/Aulas.php`
- Test: `tests/Feature/Livewire/Membros/AulasTest.php`

**Interfaces:**
- Consumes: `Lesson::isAvailableFor()` (existing), `TracksLessonProgress` trait's `mount()` (existing — this task aliases it, does not remove it).
- Produces: `Aulas` now honors a `?lesson={id}` query string param on page load. Task 4's `<x-framework-card>` "Ver aula" link relies on this.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Livewire/Membros/AulasTest.php` (this file already has a `course()` helper):

```php
    public function test_lesson_query_param_sets_the_featured_lesson_when_valid_and_available(): void
    {
        $course = $this->course();
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula via deep link',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
            'category' => 'Encontros', 'tier' => 'start',
        ]);

        $this->actingAs(User::factory()->create(['tier' => 'start']));

        $this->get('/membros/aulas?lesson='.$lesson->id)
            ->assertOk()
            ->assertSee("wire:key=\"hero-player-{$lesson->id}\"", false);
    }

    public function test_lesson_query_param_is_ignored_when_it_points_to_an_unavailable_lesson(): void
    {
        $course = $this->course();
        $clubLesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula club',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
            'category' => 'Encontros', 'tier' => 'club',
        ]);

        $this->actingAs(User::factory()->create(['tier' => 'start']));

        $this->get('/membros/aulas?lesson='.$clubLesson->id)
            ->assertOk()
            ->assertDontSee("wire:key=\"hero-player-{$clubLesson->id}\"", false);
    }

    public function test_lesson_query_param_is_ignored_when_the_lesson_does_not_exist(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/membros/aulas?lesson=999999')
            ->assertOk();
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Livewire/Membros/AulasTest.php --filter=test_lesson_query_param`
Expected: FAIL on the first test — `?lesson=` is not read yet, so the hero shows the default featured
lesson (none exists in that test, since the only lesson has no watch history and the deep-linked one
would otherwise be picked by position anyway — read the actual failure message to confirm it fails
for "not wired up" reasons, not a coincidence). The third test may already pass (an invalid ID
already doesn't error today) — that's fine, it's there to lock in the behavior going forward.

- [ ] **Step 3: Add the `?lesson=` handling to `Aulas.php`**

Replace the full file with:

```php
<?php

namespace App\Livewire\Membros;

use App\Actions\DetermineFeaturedLesson;
use App\Livewire\Concerns\ComputesUserInitials;
use App\Livewire\Concerns\TracksLessonProgress;
use App\Models\Lesson;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.membros')]
class Aulas extends Component
{
    use ComputesUserInitials;
    use TracksLessonProgress {
        mount as protected traitMount;
    }

    public string $category = 'Tudo';

    public function mount(DetermineFeaturedLesson $determineFeaturedLesson): void
    {
        $this->traitMount($determineFeaturedLesson);

        $requestedLessonId = request()->integer('lesson');

        if ($requestedLessonId) {
            $lesson = Lesson::find($requestedLessonId);

            if ($lesson && $lesson->isAvailableFor(Auth::user())) {
                $this->featuredLessonId = $lesson->id;
            }
        }
    }

    public function selectCategory(string $category): void
    {
        $this->category = $category;
    }

    #[Computed]
    public function lessons()
    {
        return Lesson::query()->with('course')
            ->when(! Auth::user()->hasClubAccess(), fn ($q) => $q->where('tier', 'start'))
            ->when($this->category !== 'Tudo', fn ($q) => $q->where('category', $this->category))
            ->orderByDesc('published_at')
            ->orderByDesc('position')
            ->get();
    }

    #[Computed]
    public function totalCount(): int
    {
        return Lesson::query()
            ->when(! Auth::user()->hasClubAccess(), fn ($q) => $q->where('tier', 'start'))
            ->count();
    }

    public function render()
    {
        return view('livewire.membros.aulas');
    }
}
```

Note the explicit `mount(DetermineFeaturedLesson $determineFeaturedLesson)` — this class now declares
its own `mount()`, which Livewire calls instead of the trait's (PHP class methods always take
precedence over trait methods of the same name); `traitMount(...)` is the alias this task's `use`
statement gives to the trait's original `mount()`, so its logic still runs first, unchanged.

- [ ] **Step 4: Run the tests**

Run: `php artisan test tests/Feature/Livewire/Membros/AulasTest.php`
Expected: PASS (all tests in the file, including the 3 new ones).

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`
Expected: PASS — this task only adds behavior gated behind a query param that's absent in every
other existing test, so nothing else should be affected.

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/Membros/Aulas.php tests/Feature/Livewire/Membros/AulasTest.php
git commit -m "feat: honor a ?lesson= query param to deep-link into a specific lesson"
```

---

## Task 4: The `/membros/frameworks` page

**Files:**
- Create: `app/Livewire/Membros/Frameworks.php`
- Create: `resources/views/livewire/membros/frameworks.blade.php`
- Create: `resources/views/components/framework-card.blade.php`
- Modify: `resources/css/app.css`
- Modify: `routes/web.php`
- Test: `tests/Feature/Livewire/Membros/FrameworksTest.php`

**Interfaces:**
- Consumes: `Framework` model (Task 1), `membros.frameworks.download` route (Task 2), `membros.aulas`
  route with `?lesson=` support (Task 3).
- Produces: named route `membros.frameworks`. Task 6 points `PersonaNavigation`'s `Frameworks` tab
  at this exact route name.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Livewire/Membros/FrameworksTest.php`:

```php
<?php

namespace Tests\Feature\Livewire\Membros;

use App\Livewire\Membros\Frameworks;
use App\Models\Course;
use App\Models\Framework;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FrameworksTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/membros/frameworks')->assertRedirect('/login');
    }

    public function test_start_tier_sees_every_framework(): void
    {
        Framework::create(['code' => '4S', 'title' => 'Consumidor 4S', 'description' => 'Teste', 'position' => 10]);
        Framework::create(['code' => 'DOR', 'title' => 'Framework DOR', 'description' => 'Teste', 'position' => 5]);

        $this->actingAs(User::factory()->create(['tier' => 'start']));

        Livewire::test(Frameworks::class)
            ->assertSee('Consumidor 4S')
            ->assertSee('Framework DOR');
    }

    public function test_club_tier_sees_the_same_frameworks_as_start(): void
    {
        Framework::create(['code' => '4S', 'title' => 'Consumidor 4S', 'description' => 'Teste', 'position' => 10]);

        $this->actingAs(User::factory()->create(['tier' => 'club']));

        Livewire::test(Frameworks::class)->assertSee('Consumidor 4S');
    }

    public function test_empty_state_shown_with_no_frameworks_published(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Frameworks::class)->assertSee('Nenhum framework publicado ainda.');
    }

    public function test_download_link_shown_for_an_uploaded_pdf(): void
    {
        $framework = Framework::create([
            'code' => '4S', 'title' => 'Consumidor 4S', 'description' => 'Teste',
            'pdf_path' => 'framework-pdfs/4s.pdf', 'position' => 10,
        ]);

        $this->actingAs(User::factory()->create());

        Livewire::test(Frameworks::class)
            ->assertSee(route('membros.frameworks.download', $framework), false);
    }

    public function test_external_link_shown_when_only_pdf_url_is_set(): void
    {
        Framework::create([
            'code' => '4S', 'title' => 'Consumidor 4S', 'description' => 'Teste',
            'pdf_url' => 'https://example.com/4s.pdf', 'position' => 10,
        ]);

        $this->actingAs(User::factory()->create());

        Livewire::test(Frameworks::class)->assertSee('https://example.com/4s.pdf', false);
    }

    public function test_pdf_em_breve_shown_when_neither_pdf_option_is_set(): void
    {
        Framework::create(['code' => '4S', 'title' => 'Consumidor 4S', 'description' => 'Teste', 'position' => 10]);

        $this->actingAs(User::factory()->create());

        Livewire::test(Frameworks::class)->assertSee('PDF em breve');
    }

    public function test_ver_aula_link_shown_only_when_a_lesson_is_linked(): void
    {
        $course = Course::create(['label' => 'Curso', 'title' => 'Teste', 'position' => 10]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula vinculada',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
        ]);
        Framework::create([
            'code' => '4S', 'title' => 'Com aula', 'description' => 'Teste',
            'lesson_id' => $lesson->id, 'position' => 10,
        ]);
        Framework::create(['code' => 'DOR', 'title' => 'Sem aula', 'description' => 'Teste', 'position' => 5]);

        $this->actingAs(User::factory()->create());

        $html = Livewire::test(Frameworks::class)->html();

        $this->assertSame(1, substr_count($html, 'Ver aula'));
        $this->assertStringContainsString(route('membros.aulas', ['lesson' => $lesson->id]), $html);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Livewire/Membros/FrameworksTest.php`
Expected: FAIL — route `membros.frameworks` / class `App\Livewire\Membros\Frameworks` don't exist.

- [ ] **Step 3: Create the Livewire component**

Create `app/Livewire/Membros/Frameworks.php`:

```php
<?php

namespace App\Livewire\Membros;

use App\Livewire\Concerns\ComputesUserInitials;
use App\Models\Framework;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.membros')]
class Frameworks extends Component
{
    use ComputesUserInitials;

    #[Computed]
    public function frameworks()
    {
        return Framework::query()->with('lesson')->orderByDesc('position')->get();
    }

    public function render()
    {
        return view('livewire.membros.frameworks');
    }
}
```

- [ ] **Step 4: Add the framework-card CSS**

Add to the end of `resources/css/app.css`:

```css
.framework-card-code {
    font-family: 'Syne', sans-serif;
    font-weight: 800;
    font-size: 56px;
    line-height: 1;
    color: transparent;
    -webkit-text-stroke: 1.5px theme('colors.brand');
}
```

- [ ] **Step 5: Create `<x-framework-card>`**

Create `resources/views/components/framework-card.blade.php`:

```blade
@props(['framework'])

<div class="flex flex-col gap-2.5 p-6 rounded-[18px] border border-sand bg-card shadow-[0_1px_2px_rgba(11,11,12,.05),0_10px_28px_rgba(11,11,12,.07)]">
    <div class="framework-card-code">{{ $framework->code }}</div>
    <h3 class="font-display text-base">{{ $framework->title }}</h3>
    <p class="text-sm text-stone flex-1">{{ $framework->description }}</p>
    <div class="flex gap-2 mt-1.5">
        @if ($framework->hasUploadedFile())
            <a href="{{ route('membros.frameworks.download', $framework) }}"
               class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-semibold bg-black text-white hover:brightness-110">
                Baixar PDF
            </a>
        @elseif ($framework->pdf_url)
            <a href="{{ $framework->pdf_url }}" target="_blank" rel="noopener"
               class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-semibold bg-black text-white hover:brightness-110">
                Baixar PDF
            </a>
        @else
            <span class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-semibold bg-card border border-sand text-stone cursor-not-allowed">
                PDF em breve
            </span>
        @endif

        @if ($framework->lesson_id && $framework->lesson)
            <a href="{{ route('membros.aulas', ['lesson' => $framework->lesson_id]) }}" wire:navigate
               class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-semibold border border-sand text-ink hover:border-black">
                Ver aula
            </a>
        @endif
    </div>
</div>
```

- [ ] **Step 6: Create the view**

Create `resources/views/livewire/membros/frameworks.blade.php`:

```blade
<div class="min-h-screen text-ink">
    <x-membros.header :initials="$this->userInitials" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="mb-8">
            <h1 class="text-[clamp(26px,4vw,38px)] leading-[1.05] font-display font-extrabold tracking-[-0.015em] text-black">
                Frameworks DO
            </h1>
            <p class="mt-2 max-w-xl text-stone">
                As ferramentas proprietárias do método Decisão Orientada. Cada uma tem o material para baixar e a aula que ensina a aplicar.
            </p>
        </div>

        <div class="grid grid-cols-[repeat(auto-fill,minmax(250px,1fr))] gap-4">
            @forelse ($this->frameworks as $framework)
                <x-framework-card :framework="$framework" />
            @empty
                <p class="col-span-full text-stone">Nenhum framework publicado ainda.</p>
            @endforelse
        </div>
    </div>

    <x-membros.footer />
</div>
```

- [ ] **Step 7: Register the route**

In `routes/web.php`, add the import:

```php
use App\Livewire\Membros\Frameworks;
```

Then, after the `membros.aulas` route, add:

```php
Route::get('membros/frameworks', Frameworks::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('membros.frameworks');
```

Use the same careful staging approach as Task 2 if the unrelated `require __DIR__.'/prototype.php';`
line is still present in the working tree.

- [ ] **Step 8: Run the tests**

Run: `php artisan test tests/Feature/Livewire/Membros/FrameworksTest.php`
Expected: PASS (all 8 tests).

Run: `npm run build`
Expected: builds without error.

- [ ] **Step 9: Commit**

```bash
git add app/Livewire/Membros/Frameworks.php resources/views/livewire/membros/frameworks.blade.php resources/views/components/framework-card.blade.php resources/css/app.css routes/web.php tests/Feature/Livewire/Membros/FrameworksTest.php
git commit -m "feat: add the real Frameworks DO page"
```

---

## Task 5: Filament admin for Framework

**Files:**
- Create: `app/Filament/Resources/Frameworks/FrameworkResource.php`
- Create: `app/Filament/Resources/Frameworks/Schemas/FrameworkForm.php`
- Create: `app/Filament/Resources/Frameworks/Tables/FrameworksTable.php`
- Create: `app/Filament/Resources/Frameworks/Pages/ListFrameworks.php`
- Create: `app/Filament/Resources/Frameworks/Pages/CreateFramework.php`
- Create: `app/Filament/Resources/Frameworks/Pages/EditFramework.php`
- Test: `tests/Feature/Admin/FrameworkResourceTest.php`

**Interfaces:**
- Consumes: `Framework` model (Task 1). Mirrors `App\Filament\Resources\Lessons` file-for-file.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Admin/FrameworkResourceTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Frameworks\Pages\CreateFramework;
use App\Filament\Resources\Frameworks\Pages\EditFramework;
use App\Filament\Resources\Frameworks\Pages\ListFrameworks;
use App\Models\Course;
use App\Models\Framework;
use App\Models\Lesson;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FrameworkResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_non_admin_cannot_access_the_frameworks_list(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/admin/frameworks')->assertForbidden();
    }

    public function test_admin_can_see_an_existing_framework_in_the_list(): void
    {
        $framework = Framework::create([
            'code' => '4S', 'title' => 'Consumidor 4S', 'description' => 'Teste', 'position' => 10,
        ]);

        $this->actingAs($this->admin());

        Livewire::test(ListFrameworks::class)
            ->assertCanSeeTableRecords([$framework]);
    }

    public function test_admin_can_create_a_framework(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateFramework::class)
            ->fillForm([
                'code' => '4S',
                'title' => 'Consumidor 4S',
                'description' => 'O mapa de como seu cliente decide.',
                'pdf_url' => 'https://example.com/4s.pdf',
                'position' => 10,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('frameworks', [
            'code' => '4S',
            'title' => 'Consumidor 4S',
        ]);
    }

    public function test_admin_can_link_a_lesson_when_creating_a_framework(): void
    {
        $course = Course::create(['label' => 'Curso', 'title' => 'Teste', 'position' => 10]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula vinculada',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
        ]);

        $this->actingAs($this->admin());

        Livewire::test(CreateFramework::class)
            ->fillForm([
                'code' => '4S',
                'title' => 'Consumidor 4S',
                'description' => 'Teste',
                'pdf_url' => 'https://example.com/4s.pdf',
                'lesson_id' => $lesson->id,
                'position' => 10,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('frameworks', [
            'title' => 'Consumidor 4S',
            'lesson_id' => $lesson->id,
        ]);
    }

    public function test_admin_can_upload_a_pdf_and_it_resolves_to_a_public_storage_url(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin());

        Livewire::test(CreateFramework::class)
            ->fillForm([
                'code' => '4S',
                'title' => 'Consumidor 4S',
                'description' => 'Teste',
                'pdf_path' => UploadedFile::fake()->create('4s.pdf', 10, 'application/pdf'),
                'position' => 10,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $framework = Framework::where('title', 'Consumidor 4S')->firstOrFail();

        Storage::disk('public')->assertExists($framework->pdf_path);
    }

    public function test_admin_can_edit_a_framework(): void
    {
        $framework = Framework::create([
            'code' => '4S', 'title' => 'Título original', 'description' => 'Teste',
            'pdf_url' => 'https://example.com/4s.pdf', 'position' => 10,
        ]);

        $this->actingAs($this->admin());

        Livewire::test(EditFramework::class, ['record' => $framework->getKey()])
            ->fillForm(['title' => 'Título atualizado'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('frameworks', [
            'id' => $framework->id,
            'title' => 'Título atualizado',
        ]);
    }

    public function test_admin_can_delete_a_framework(): void
    {
        $framework = Framework::create([
            'code' => '4S', 'title' => 'A remover', 'description' => 'Teste',
            'pdf_url' => 'https://example.com/4s.pdf', 'position' => 10,
        ]);

        $this->actingAs($this->admin());

        Livewire::test(ListFrameworks::class)
            ->callTableAction(DeleteAction::class, record: $framework);

        $this->assertDatabaseMissing('frameworks', ['id' => $framework->id]);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Admin/FrameworkResourceTest.php`
Expected: FAIL — none of the resource classes exist yet.

- [ ] **Step 3: Create the form schema**

Create `app/Filament/Resources/Frameworks/Schemas/FrameworkForm.php`:

```php
<?php

namespace App\Filament\Resources\Frameworks\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FrameworkForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Código')
                    ->required(),
                TextInput::make('title')
                    ->required(),
                Textarea::make('description')
                    ->required(),
                TextInput::make('pdf_url')
                    ->label('External URL')
                    ->url()
                    ->requiredWithout('pdf_path'),
                FileUpload::make('pdf_path')
                    ->label('File (PDF)')
                    ->disk('public')
                    ->directory('framework-pdfs')
                    ->acceptedFileTypes(['application/pdf'])
                    ->requiredWithout('pdf_url'),
                Select::make('lesson_id')
                    ->label('Aula vinculada')
                    ->relationship('lesson', 'title')
                    ->searchable()
                    ->nullable(),
                TextInput::make('position')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
```

- [ ] **Step 4: Create the table**

Create `app/Filament/Resources/Frameworks/Tables/FrameworksTable.php`:

```php
<?php

namespace App\Filament\Resources\Frameworks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FrameworksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable(),
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('lesson.title')
                    ->label('Aula vinculada')
                    ->placeholder('—'),
                TextColumn::make('position')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('position', 'desc')
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

Create `app/Filament/Resources/Frameworks/Pages/ListFrameworks.php`:

```php
<?php

namespace App\Filament\Resources\Frameworks\Pages;

use App\Filament\Resources\Frameworks\FrameworkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFrameworks extends ListRecords
{
    protected static string $resource = FrameworkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
```

Create `app/Filament/Resources/Frameworks/Pages/CreateFramework.php`:

```php
<?php

namespace App\Filament\Resources\Frameworks\Pages;

use App\Filament\Resources\Frameworks\FrameworkResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFramework extends CreateRecord
{
    protected static string $resource = FrameworkResource::class;
}
```

Create `app/Filament/Resources/Frameworks/Pages/EditFramework.php`:

```php
<?php

namespace App\Filament\Resources\Frameworks\Pages;

use App\Filament\Resources\Frameworks\FrameworkResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFramework extends EditRecord
{
    protected static string $resource = FrameworkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
```

- [ ] **Step 6: Create the resource**

Create `app/Filament/Resources/Frameworks/FrameworkResource.php`:

```php
<?php

namespace App\Filament\Resources\Frameworks;

use App\Filament\Resources\Frameworks\Pages\CreateFramework;
use App\Filament\Resources\Frameworks\Pages\EditFramework;
use App\Filament\Resources\Frameworks\Pages\ListFrameworks;
use App\Filament\Resources\Frameworks\Schemas\FrameworkForm;
use App\Filament\Resources\Frameworks\Tables\FrameworksTable;
use App\Models\Framework;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FrameworkResource extends Resource
{
    protected static ?string $model = Framework::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return FrameworkForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FrameworksTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFrameworks::route('/'),
            'create' => CreateFramework::route('/create'),
            'edit' => EditFramework::route('/{record}/edit'),
        ];
    }
}
```

If `Heroicon::OutlinedSquares2x2` doesn't exist in this project's installed Filament icon set, pick
any other `Heroicon::Outlined*` case already used elsewhere in `app/Filament/Resources/` (e.g. reuse
`Heroicon::OutlinedRectangleStack` from `LessonResource` if unsure) — the specific icon has no
functional effect on any test.

- [ ] **Step 7: Run the tests**

Run: `php artisan test tests/Feature/Admin/FrameworkResourceTest.php`
Expected: PASS (all 7 tests).

- [ ] **Step 8: Commit**

```bash
git add app/Filament/Resources/Frameworks tests/Feature/Admin/FrameworkResourceTest.php
git commit -m "feat: add Filament admin resource for Framework"
```

---

## Task 6: Unlock the Frameworks nav tab

**Files:**
- Modify: `app/Support/PersonaNavigation.php`
- Modify: `tests/Unit/Support/PersonaNavigationTest.php`
- Modify: `tests/Feature/Membros/PersonaNavigationTest.php`
- Modify: `tests/Feature/Livewire/Membros/DashboardTest.php`

**Interfaces:**
- Consumes: named route `membros.frameworks` (Task 4) — must exist before this task runs, for the
  same reason as the equivalent step in the Biblioteca de aulas plan: `x-membros.header` calls
  `route($tab['route'])` for every tab marked `available: true`.

- [ ] **Step 1: Write the failing tests**

In `tests/Unit/Support/PersonaNavigationTest.php`, replace:

```php
    public function test_start_tier_has_two_available_tabs_and_two_locked_tabs(): void
    {
        $tabs = (new PersonaNavigation)->tabs('start');

        $this->assertCount(4, $tabs);
        $this->assertSame(['Início', 'Aulas', 'Frameworks', 'Sessão 1:1'], array_column($tabs, 'label'));
        $this->assertSame([true, true, false, false], array_column($tabs, 'available'));
    }

    public function test_club_tier_has_two_available_tabs_and_five_locked_tabs(): void
    {
        $tabs = (new PersonaNavigation)->tabs('club');

        $this->assertCount(7, $tabs);
        $this->assertSame(
            ['Início', 'Aulas', 'Meu cofre', 'Minha sessão', 'Pessoas', 'Encontros', 'Frameworks'],
            array_column($tabs, 'label'),
        );
        $this->assertSame([true, true, false, false, false, false, false], array_column($tabs, 'available'));
    }
```

with:

```php
    public function test_start_tier_has_three_available_tabs_and_one_locked_tab(): void
    {
        $tabs = (new PersonaNavigation)->tabs('start');

        $this->assertCount(4, $tabs);
        $this->assertSame(['Início', 'Aulas', 'Frameworks', 'Sessão 1:1'], array_column($tabs, 'label'));
        $this->assertSame([true, true, true, false], array_column($tabs, 'available'));
    }

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

In `tests/Feature/Membros/PersonaNavigationTest.php`, replace:

```php
    public function test_start_tier_shows_inicio_and_aulas_as_links_and_the_rest_locked(): void
    {
        $user = User::factory()->create(['tier' => 'start']);
        $this->actingAs($user);

        $html = $this->get('/membros')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<a[^>]*href="http://localhost/membros"[^>]*>\s*Início\s*</a>#s',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '#<a[^>]*href="http://localhost/membros/aulas"[^>]*>\s*Aulas\s*</a>#s',
            $html,
        );

        foreach (['Frameworks', 'Sessão 1:1'] as $label) {
            $this->assertMatchesRegularExpression(
                '#<span[^>]*title="Em breve"[^>]*>\s*'.preg_quote($label, '#').'#s',
                $html,
            );
        }
    }

    public function test_club_tier_shows_inicio_and_aulas_as_links_and_five_tabs_locked(): void
    {
        $user = User::factory()->create(['tier' => 'club']);
        $this->actingAs($user);

        $html = $this->get('/membros')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<a[^>]*href="http://localhost/membros"[^>]*>\s*Início\s*</a>#s',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '#<a[^>]*href="http://localhost/membros/aulas"[^>]*>\s*Aulas\s*</a>#s',
            $html,
        );

        foreach (['Meu cofre', 'Minha sessão', 'Pessoas', 'Encontros', 'Frameworks'] as $label) {
            $this->assertMatchesRegularExpression(
                '#<span[^>]*title="Em breve"[^>]*>\s*'.preg_quote($label, '#').'#s',
                $html,
            );
        }
    }
```

with:

```php
    public function test_start_tier_shows_inicio_aulas_and_frameworks_as_links_and_the_rest_locked(): void
    {
        $user = User::factory()->create(['tier' => 'start']);
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

        $this->assertMatchesRegularExpression(
            '#<span[^>]*title="Em breve"[^>]*>\s*Sessão 1:1#s',
            $html,
        );
    }

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

(Note: `Frameworks` moved from the locked-labels loop to the linked-labels loop in both tests, and
the loop structure changed slightly to check multiple links cleanly — the assertion intent is
identical to the Biblioteca de aulas plan's equivalent step, just extended to 3 links instead of 2.)

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Unit/Support/PersonaNavigationTest.php tests/Feature/Membros/PersonaNavigationTest.php`
Expected: FAIL — `Frameworks` is still `available: false`.

- [ ] **Step 3: Update `PersonaNavigation.php`**

In `app/Support/PersonaNavigation.php`, change the `Frameworks` entry's `available` from `false` to
`true` in both the `'start'` and `'club'` arrays (2 one-word edits):

```php
            'start' => [
                ['label' => 'Início', 'route' => 'dashboard', 'available' => true],
                ['label' => 'Aulas', 'route' => 'membros.aulas', 'available' => true],
                ['label' => 'Frameworks', 'route' => 'membros.frameworks', 'available' => true],
                ['label' => 'Sessão 1:1', 'route' => 'membros.upgrade', 'available' => false],
            ],
            'club' => [
                ['label' => 'Início', 'route' => 'dashboard', 'available' => true],
                ['label' => 'Aulas', 'route' => 'membros.aulas', 'available' => true],
                ['label' => 'Meu cofre', 'route' => 'membros.cofre', 'available' => false],
                ['label' => 'Minha sessão', 'route' => 'membros.agenda', 'available' => false],
                ['label' => 'Pessoas', 'route' => 'membros.pessoas', 'available' => false],
                ['label' => 'Encontros', 'route' => 'membros.encontros', 'available' => false],
                ['label' => 'Frameworks', 'route' => 'membros.frameworks', 'available' => true],
            ],
```

- [ ] **Step 4: Run the nav tests**

Run: `php artisan test tests/Unit/Support/PersonaNavigationTest.php tests/Feature/Membros/PersonaNavigationTest.php`
Expected: PASS.

- [ ] **Step 5: Fix `DashboardTest`'s quick-links test**

Run: `php artisan test tests/Feature/Livewire/Membros/DashboardTest.php`
Expected: FAIL — `test_quick_links_render_locked_with_no_href_when_route_does_not_exist_yet` asserts
"Frameworks DO" (the Início "Atalhos" card's link, reading the same `PersonaNavigation` flag) renders
locked. That's no longer true. In `tests/Feature/Livewire/Membros/DashboardTest.php`, replace:

```php
    public function test_quick_links_render_locked_with_no_href_when_route_does_not_exist_yet(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'club']));

        $html = Livewire::test(Dashboard::class)->html();

        foreach (['Frameworks DO', 'Marcar minha sessão'] as $label) {
            $this->assertMatchesRegularExpression(
                '#<span[^>]*>\s*'.preg_quote($label, '#').'.*?🔒#s',
                $html,
            );
        }

        $this->assertMatchesRegularExpression(
            '#<a[^>]*href="http://localhost/membros/aulas"[^>]*>\s*Biblioteca de aulas#s',
            $html,
        );
    }
```

with:

```php
    public function test_quick_links_render_locked_with_no_href_when_route_does_not_exist_yet(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'club']));

        $html = Livewire::test(Dashboard::class)->html();

        $this->assertMatchesRegularExpression(
            '#<span[^>]*>\s*Marcar minha sessão.*?🔒#s',
            $html,
        );

        $this->assertMatchesRegularExpression(
            '#<a[^>]*href="http://localhost/membros/aulas"[^>]*>\s*Biblioteca de aulas#s',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '#<a[^>]*href="http://localhost/membros/frameworks"[^>]*>\s*Frameworks DO#s',
            $html,
        );
    }
```

Run: `php artisan test tests/Feature/Livewire/Membros/DashboardTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Support/PersonaNavigation.php tests/Unit/Support/PersonaNavigationTest.php tests/Feature/Membros/PersonaNavigationTest.php tests/Feature/Livewire/Membros/DashboardTest.php
git commit -m "feat: unlock the Frameworks nav tab now that the page exists"
```

---

## Manual verification (after Task 6)

1. Log in as any member (start or club). Confirm: the header nav shows "Frameworks" as a real link
   for both tiers (unlike Aulas, no tier difference); the Início "Atalhos" card's "Frameworks DO"
   link now works too.
2. In Filament (`/admin/frameworks`), create a framework with an uploaded PDF and a linked lesson.
   Confirm it appears on `/membros/frameworks` with a working "Baixar PDF" button and a "Ver aula"
   button that opens `/membros/aulas` with that exact lesson already playing in the hero.
3. Create a second framework with only `pdf_url` set (no upload) — confirm "Baixar PDF" opens the
   external URL directly in a new tab, not the download route.
4. Create a third framework with neither `pdf_path` nor `pdf_url` — confirm it shows "PDF em breve"
   instead of a broken or missing button.
5. Run `php artisan test` (full suite) once more after all 6 tasks — should be green with no manual
   fixes needed beyond what each task's steps already cover.
