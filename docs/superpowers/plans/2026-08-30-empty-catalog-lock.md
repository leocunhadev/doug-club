# Cadeado de catálogo vazio + Materiais por aula Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close GitHub issue #26 (dedicated per-lesson materials page) and establish a reusable "em breve" full-page lock for catalog pages (Aulas, Frameworks, Materiais) that are genuinely empty — shown to regular members, bypassed for admins and the mentor, who need to see the real (possibly empty) page to fix it.

**Architecture:** A tiny reusable trait (`ComputesCatalogAccess`) exposes one `#[Computed]` boolean (`canSeeEmptyCatalog`), and a tiny Blade component (`<x-catalog-empty-lock>`) renders the full-page locked state — both built once in Task 1 and consumed by every catalog page. The new `AulaMateriais` page (Task 2) reuses the Cofre's already-shipped `.doc-row`/`.doc-ic` CSS and a `LessonMaterial::iconLabel()` accessor copied from `VaultDocument::iconLabel()`. Aulas and Frameworks (Task 3) get retrofitted to use the same lock. The `lesson-player.blade.php` dropdown (Task 4) is replaced with a plain link to the new materials page, now that it exists.

**Tech Stack:** Laravel 13, Livewire 3, Tailwind CSS v3, PHPUnit (`php artisan test`), SQLite (`:memory:` in tests).

**Spec:** `docs/superpowers/specs/2026-08-30-empty-catalog-lock-design.md`

## Global Constraints

- The lock applies ONLY to catalog pages (Aulas, Frameworks, Materiais) — Cofre, Pessoas, Dossiês keep their existing personal/relational empty-state messages unchanged, this plan does not touch them.
- Bypass condition is `Auth::user()->is_admin || Auth::user()->isMentor()` — both roles see the real page even when empty, everyone else sees the lock.
- "Catalog empty" (locks) is distinct from "filter/search yielded nothing" (never locks, keeps existing message) — Aulas locks only when `totalCount === 0` (already tier-scoped), never on a category/search miss.
- The Materiais page locks on the SYSTEM-WIDE `LessonMaterial::count() === 0` (matching the exact condition that originally blocked issue #14) — once any material exists anywhere, an individual empty lesson shows its own normal empty message, never the lock.
- No Filament changes — the materials CRUD stays exactly as it is; only the member-facing display changes.

---

## Task 1: Shared lock trait + component + `LessonMaterial::iconLabel()`

**Files:**
- Create: `app/Livewire/Concerns/ComputesCatalogAccess.php`
- Create: `resources/views/components/catalog-empty-lock.blade.php`
- Modify: `app/Models/LessonMaterial.php`
- Test: `tests/Unit/LessonMaterialTest.php`

**Interfaces:**
- Produces: trait `ComputesCatalogAccess` with `#[Computed] public function canSeeEmptyCatalog(): bool`; Blade component `<x-catalog-empty-lock title="..." message="..." />`; `LessonMaterial::iconLabel()` accessor (`icon_label` property). Tasks 2 and 3 depend on all three.

- [ ] **Step 1: Write the failing tests**

Append these methods to `tests/Unit/LessonMaterialTest.php` (inside the existing class, after `test_has_uploaded_file_is_false_when_file_path_is_null`, before the closing `}`):

```php
    public function test_icon_label_is_pdf_for_a_pdf_upload(): void
    {
        $material = LessonMaterial::create([
            'lesson_id' => $this->lesson()->id,
            'title' => 'Apostila',
            'file_path' => 'lesson-materials/insights.pdf',
        ]);

        $this->assertSame('PDF', $material->icon_label);
    }

    public function test_icon_label_is_video_for_a_video_upload(): void
    {
        $material = LessonMaterial::create([
            'lesson_id' => $this->lesson()->id,
            'title' => 'Gravação',
            'file_path' => 'lesson-materials/gravacao.mp4',
        ]);

        $this->assertSame('VÍDEO', $material->icon_label);
    }

    public function test_icon_label_is_xlsx_for_a_spreadsheet_upload(): void
    {
        $material = LessonMaterial::create([
            'lesson_id' => $this->lesson()->id,
            'title' => 'Planilha',
            'file_path' => 'lesson-materials/tabela.xlsx',
        ]);

        $this->assertSame('XLSX', $material->icon_label);
    }

    public function test_icon_label_is_doc_for_a_word_upload(): void
    {
        $material = LessonMaterial::create([
            'lesson_id' => $this->lesson()->id,
            'title' => 'Plano',
            'file_path' => 'lesson-materials/plano.docx',
        ]);

        $this->assertSame('DOC', $material->icon_label);
    }

    public function test_icon_label_is_link_for_an_external_url_with_no_recognizable_extension(): void
    {
        $material = LessonMaterial::create([
            'lesson_id' => $this->lesson()->id,
            'title' => 'Vídeo externo',
            'file_url' => 'https://vimeo.com/76979871',
        ]);

        $this->assertSame('LINK', $material->icon_label);
    }

    public function test_icon_label_is_arquivo_for_an_unrecognized_extension(): void
    {
        $material = LessonMaterial::create([
            'lesson_id' => $this->lesson()->id,
            'title' => 'Arquivo genérico',
            'file_path' => 'lesson-materials/arquivo.zip',
        ]);

        $this->assertSame('ARQUIVO', $material->icon_label);
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Unit/LessonMaterialTest.php`
Expected: FAIL — `icon_label` accessor doesn't exist yet.

- [ ] **Step 3: Create the trait**

Create `app/Livewire/Concerns/ComputesCatalogAccess.php`:

```php
<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

trait ComputesCatalogAccess
{
    #[Computed]
    public function canSeeEmptyCatalog(): bool
    {
        return Auth::user()->is_admin || Auth::user()->isMentor();
    }
}
```

- [ ] **Step 4: Create the Blade lock component**

Create `resources/views/components/catalog-empty-lock.blade.php`:

```blade
@props(['title', 'message'])

<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-24 text-center">
    <div class="text-4xl mb-4" aria-hidden="true">🔒</div>
    <h1 class="text-2xl font-bold font-display">{{ $title }}</h1>
    <p class="mt-3 text-stone">{{ $message }}</p>
</div>
```

- [ ] **Step 5: Add `iconLabel()` to `LessonMaterial`**

In `app/Models/LessonMaterial.php`, change:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'title',
        'file_url',
        'file_path',
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function hasUploadedFile(): bool
    {
        return filled($this->file_path);
    }
}
```

to:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'title',
        'file_url',
        'file_path',
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function hasUploadedFile(): bool
    {
        return filled($this->file_path);
    }

    protected function iconLabel(): Attribute
    {
        return Attribute::get(function () {
            $path = $this->hasUploadedFile() ? $this->file_path : $this->file_url;
            $extension = strtolower((string) pathinfo((string) $path, PATHINFO_EXTENSION));

            return match (true) {
                $extension === 'pdf' => 'PDF',
                in_array($extension, ['mp4', 'mov', 'webm'], true) => 'VÍDEO',
                in_array($extension, ['xlsx', 'xls'], true) => 'XLSX',
                in_array($extension, ['doc', 'docx'], true) => 'DOC',
                ! $this->hasUploadedFile() && filled($this->file_url) => 'LINK',
                default => 'ARQUIVO',
            };
        });
    }
}
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test tests/Unit/LessonMaterialTest.php`
Expected: PASS (all tests — 2 existing + 6 new)

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/Concerns/ComputesCatalogAccess.php resources/views/components/catalog-empty-lock.blade.php app/Models/LessonMaterial.php tests/Unit/LessonMaterialTest.php
git commit -m "feat: add shared empty-catalog lock trait/component and LessonMaterial icon_label"
```

---

## Task 2: The per-lesson materials page

**Files:**
- Create: `app/Livewire/Membros/AulaMateriais.php`
- Create: `resources/views/livewire/membros/aula-materiais.blade.php`
- Modify: `routes/web.php` (add import + route)
- Test: `tests/Feature/Livewire/Membros/AulaMateriaisTest.php`

**Interfaces:**
- Consumes: `ComputesCatalogAccess` trait, `<x-catalog-empty-lock>` component, `LessonMaterial::icon_label` (Task 1); `ComputesUserInitials` trait, `Lesson::isAvailableFor()`, `LessonMaterial::hasUploadedFile()` (existing).
- Produces: route `membros.aulas.materiais`. Task 4 depends on this route existing.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Livewire/Membros/AulaMateriaisTest.php`:

```php
<?php

namespace Tests\Feature\Livewire\Membros;

use App\Livewire\Membros\AulaMateriais;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AulaMateriaisTest extends TestCase
{
    use RefreshDatabase;

    private function lesson(array $overrides = []): Lesson
    {
        $course = Course::create(['label' => 'Módulo 1', 'title' => 'Fundamentos', 'position' => 10]);

        return Lesson::create(array_merge([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula de teste',
            'video_provider' => 'youtube', 'video_id' => 'abc123', 'published_at' => '2026-01-01', 'position' => 10,
        ], $overrides));
    }

    public function test_returns_404_for_a_lesson_that_does_not_exist(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/membros/aulas/999/materiais')->assertNotFound();
    }

    public function test_returns_404_for_a_club_only_lesson_viewed_by_a_start_tier_member(): void
    {
        $lesson = $this->lesson(['tier' => 'club']);
        LessonMaterial::create(['lesson_id' => $lesson->id, 'title' => 'Apostila', 'file_url' => 'https://example.com/a.pdf']);

        $this->actingAs(User::factory()->create(['tier' => 'start']));

        $this->get(route('membros.aulas.materiais', $lesson))->assertNotFound();
    }

    public function test_lists_the_lessons_real_materials(): void
    {
        $lesson = $this->lesson();
        LessonMaterial::create(['lesson_id' => $lesson->id, 'title' => 'Apostila', 'file_url' => 'https://example.com/a.pdf']);

        $this->actingAs(User::factory()->create());

        Livewire::test(AulaMateriais::class, ['lesson' => $lesson])
            ->assertSee('Apostila');
    }

    public function test_shows_a_per_lesson_empty_message_when_the_system_has_materials_elsewhere(): void
    {
        $lessonWithMaterial = $this->lesson(['number' => 1]);
        LessonMaterial::create(['lesson_id' => $lessonWithMaterial->id, 'title' => 'Apostila', 'file_url' => 'https://example.com/a.pdf']);
        $emptyLesson = $this->lesson(['number' => 2]);

        $this->actingAs(User::factory()->create());

        Livewire::test(AulaMateriais::class, ['lesson' => $emptyLesson])
            ->assertSee('Nenhum material para esta aula ainda.');
    }

    public function test_shows_the_lock_when_the_whole_system_has_no_materials_for_a_regular_user(): void
    {
        $lesson = $this->lesson();

        $this->actingAs(User::factory()->create());

        Livewire::test(AulaMateriais::class, ['lesson' => $lesson])
            ->assertSee('Os materiais de aula estão sendo preparados.');
    }

    public function test_mentor_sees_the_real_empty_list_even_when_the_whole_system_has_no_materials(): void
    {
        $lesson = $this->lesson();

        $this->actingAs(User::factory()->create(['tier' => 'mentor']));

        Livewire::test(AulaMateriais::class, ['lesson' => $lesson])
            ->assertSee('Nenhum material para esta aula ainda.')
            ->assertDontSee('Os materiais de aula estão sendo preparados.');
    }

    public function test_admin_sees_the_real_empty_list_even_when_the_whole_system_has_no_materials(): void
    {
        $lesson = $this->lesson();

        $this->actingAs(User::factory()->create(['is_admin' => true]));

        Livewire::test(AulaMateriais::class, ['lesson' => $lesson])
            ->assertSee('Nenhum material para esta aula ainda.')
            ->assertDontSee('Os materiais de aula estão sendo preparados.');
    }

    public function test_download_link_shown_for_an_uploaded_file(): void
    {
        $lesson = $this->lesson();
        $material = LessonMaterial::create(['lesson_id' => $lesson->id, 'title' => 'Apostila', 'file_path' => 'lesson-materials/a.pdf']);

        $this->actingAs(User::factory()->create());

        Livewire::test(AulaMateriais::class, ['lesson' => $lesson])
            ->assertSee(route('membros.materials.download', $material), false);
    }

    public function test_external_link_shown_for_a_file_url_material(): void
    {
        $lesson = $this->lesson();
        LessonMaterial::create(['lesson_id' => $lesson->id, 'title' => 'Apostila', 'file_url' => 'https://example.com/a.pdf']);

        $this->actingAs(User::factory()->create());

        Livewire::test(AulaMateriais::class, ['lesson' => $lesson])
            ->assertSee('https://example.com/a.pdf', false);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/Livewire/Membros/AulaMateriaisTest.php`
Expected: FAIL — `AulaMateriais` component and `membros.aulas.materiais` route don't exist yet.

- [ ] **Step 3: Create the Livewire component**

Create `app/Livewire/Membros/AulaMateriais.php`:

```php
<?php

namespace App\Livewire\Membros;

use App\Livewire\Concerns\ComputesCatalogAccess;
use App\Livewire\Concerns\ComputesUserInitials;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.membros')]
class AulaMateriais extends Component
{
    use ComputesUserInitials;
    use ComputesCatalogAccess;

    public Lesson $lesson;

    public function mount(Lesson $lesson): void
    {
        abort_unless($lesson->isAvailableFor(Auth::user()), 404);

        $this->lesson = $lesson;
    }

    #[Computed]
    public function materials(): Collection
    {
        return $this->lesson->materials()->orderBy('id')->get();
    }

    #[Computed]
    public function catalogIsEmpty(): bool
    {
        return LessonMaterial::query()->count() === 0;
    }

    public function render()
    {
        return view('livewire.membros.aula-materiais');
    }
}
```

- [ ] **Step 4: Create the page view**

Create `resources/views/livewire/membros/aula-materiais.blade.php`:

```blade
<div class="min-h-screen text-ink">
    <x-membros.header :initials="$this->userInitials" />

    @if ($this->catalogIsEmpty && ! $this->canSeeEmptyCatalog)
        <x-catalog-empty-lock
            title="Os materiais de aula estão sendo preparados."
            message="Em breve o Douglas vai adicionar os primeiros arquivos por aqui." />
    @else
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            <a href="{{ route('membros.aulas') }}" wire:navigate class="text-sm text-stone hover:text-ink">
                ← Voltar pra Aulas
            </a>

            <div class="mt-4 mb-8">
                <h1 class="text-[clamp(26px,4vw,38px)] leading-[1.05] font-display font-extrabold tracking-[-0.015em] text-black">
                    Materiais · {{ $this->lesson->title }}
                </h1>
            </div>

            <div class="rounded-[18px] border border-sand bg-card shadow-[0_1px_2px_rgba(11,11,12,.05),0_10px_28px_rgba(11,11,12,.07)] overflow-hidden">
                @forelse ($this->materials as $material)
                    <div class="doc-row">
                        <div class="doc-ic">{{ $material->icon_label }}</div>
                        <div class="flex-1 min-w-0">
                            <b class="block text-sm">{{ $material->title }}</b>
                        </div>
                        @if ($material->hasUploadedFile())
                            <a href="{{ route('membros.materials.download', $material) }}"
                               class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-semibold border border-sand text-ink hover:border-black">
                                Baixar
                            </a>
                        @else
                            <a href="{{ $material->file_url }}" target="_blank" rel="noopener"
                               class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-semibold border border-sand text-ink hover:border-black">
                                Abrir
                            </a>
                        @endif
                    </div>
                @empty
                    <p class="text-stone p-6">Nenhum material para esta aula ainda.</p>
                @endforelse
            </div>
        </div>
    @endif

    <x-membros.footer />
</div>
```

- [ ] **Step 5: Register the route**

In `routes/web.php`, add the import alphabetically between `Agenda` and `Aulas` (currently lines 8-9):

```php
use App\Livewire\Membros\Agenda;
use App\Livewire\Membros\AulaMateriais;
use App\Livewire\Membros\Aulas;
```

Add the route block right after the `membros/aulas` route (currently lines 54-56), before `membros/cofre`:

```php
Route::get('membros/aulas/{lesson}/materiais', AulaMateriais::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('membros.aulas.materiais');
```

**routes/web.php git hygiene**: this file has a permanently uncommitted trailing line `require __DIR__.'/prototype.php';` belonging to unrelated concurrent work. Before editing, run `git diff routes/web.php` to confirm that line is present and uncommitted. Make the edits with targeted string-replacement `Edit` calls only (one for the import, one for the route block), never a full-file rewrite. After editing, stage with `git add -p routes/web.php`, answering `y` to the hunks containing your import and route block, `n` to any hunk containing the `require __DIR__.'/prototype.php';` line. Verify with `git diff --staged routes/web.php` before committing that only your intended change is staged, and `git diff routes/web.php` after to confirm the require line still shows as unstaged.

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/Livewire/Membros/AulaMateriaisTest.php`
Expected: PASS (9 tests)

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/Membros/AulaMateriais.php resources/views/livewire/membros/aula-materiais.blade.php tests/Feature/Livewire/Membros/AulaMateriaisTest.php
git add -p routes/web.php
git commit -m "feat: add the per-lesson materials page (issue #26)"
```

---

## Task 3: Retrofit Aulas and Frameworks with the lock

**Files:**
- Modify: `app/Livewire/Membros/Aulas.php`
- Modify: `resources/views/livewire/membros/aulas.blade.php`
- Modify: `app/Livewire/Membros/Frameworks.php`
- Modify: `resources/views/livewire/membros/frameworks.blade.php`
- Test: `tests/Feature/Livewire/Membros/AulasTest.php`
- Test: `tests/Feature/Livewire/Membros/FrameworksTest.php`

**Interfaces:**
- Consumes: `ComputesCatalogAccess` trait, `<x-catalog-empty-lock>` component (Task 1).
- Produces: nothing later tasks depend on.

- [ ] **Step 1: Write the failing tests**

Append these two methods to `tests/Feature/Livewire/Membros/AulasTest.php` (inside the class, anywhere after the `course()` helper — e.g. right after `test_guests_are_redirected_to_login`):

```php
    public function test_regular_user_sees_the_lock_when_the_catalog_has_no_lessons_at_all(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Aulas::class)
            ->assertSee('Sua biblioteca de aulas está sendo preparada.');
    }

    public function test_mentor_sees_the_real_page_even_when_the_catalog_has_no_lessons_at_all(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));

        Livewire::test(Aulas::class)
            ->assertSee('Nenhuma aula nesta categoria ainda.')
            ->assertDontSee('Sua biblioteca de aulas está sendo preparada.');
    }
```

In `tests/Feature/Livewire/Membros/FrameworksTest.php`, replace `test_empty_state_shown_with_no_frameworks_published` with:

```php
    public function test_empty_state_shows_the_lock_for_a_regular_user_with_no_frameworks_published(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Frameworks::class)->assertSee('Os frameworks estão sendo preparados.');
    }

    public function test_mentor_sees_the_real_empty_state_with_no_frameworks_published(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));

        Livewire::test(Frameworks::class)
            ->assertSee('Nenhum framework publicado ainda.')
            ->assertDontSee('Os frameworks estão sendo preparados.');
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/Livewire/Membros/AulasTest.php tests/Feature/Livewire/Membros/FrameworksTest.php`
Expected: FAIL — `canSeeEmptyCatalog` isn't wired into either component/view yet, so the lock never renders (Aulas tests) and the old message still always shows (Frameworks tests).

- [ ] **Step 3: Add the trait to both components**

In `app/Livewire/Membros/Aulas.php`, change:

```php
class Aulas extends Component
{
    use ComputesUserInitials;
    use TracksLessonProgress {
        mount as protected traitMount;
    }
```

to:

```php
class Aulas extends Component
{
    use ComputesUserInitials;
    use ComputesCatalogAccess;
    use TracksLessonProgress {
        mount as protected traitMount;
    }
```

And add the import, changing:

```php
use App\Livewire\Concerns\ComputesUserInitials;
use App\Livewire\Concerns\TracksLessonProgress;
```

to:

```php
use App\Livewire\Concerns\ComputesCatalogAccess;
use App\Livewire\Concerns\ComputesUserInitials;
use App\Livewire\Concerns\TracksLessonProgress;
```

In `app/Livewire/Membros/Frameworks.php`, change:

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
```

to:

```php
<?php

namespace App\Livewire\Membros;

use App\Livewire\Concerns\ComputesCatalogAccess;
use App\Livewire\Concerns\ComputesUserInitials;
use App\Models\Framework;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.membros')]
class Frameworks extends Component
{
    use ComputesUserInitials;
    use ComputesCatalogAccess;
```

- [ ] **Step 4: Wrap the Aulas view content in the lock check**

In `resources/views/livewire/membros/aulas.blade.php`, wrap the entire `<div class="max-w-7xl ...">...</div>` block (everything between `<x-membros.header ... />` and `<x-membros.footer />`) in a conditional. Change the file from:

```blade
<div class="min-h-screen text-ink">
    <x-membros.header :initials="$this->userInitials" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="mb-8">
            <h1 class="text-[clamp(26px,4vw,38px)] leading-[1.05] font-display font-extrabold tracking-[-0.015em] text-black">
                Biblioteca de aulas
            </h1>
            <p class="mt-2 max-w-xl text-stone">
                Todos os encontros gravados, aulas de convidados e frameworks em vídeo. Aperte o play e continue de onde parou.
            </p>
        </div>

        <x-lesson-player :lesson="$this->featuredLesson" :progress="$this->featuredProgress" :has-feedback="$this->featuredHasFeedback" />
        <x-nps-modal />

        <p class="mt-4 text-sm text-stone">
            Você está assistindo agora: <b class="font-semibold text-ink">{{ $this->featuredLesson && $this->featuredLesson->isAvailableFor(auth()->user()) ? $this->featuredLesson->title : '—' }}</b>
            · {{ $this->totalCount }} {{ Str::plural('aula', $this->totalCount) }} na sua biblioteca
        </p>

        <div class="mt-6 flex flex-wrap items-center gap-2">
            @foreach (['Tudo', 'Encontros', 'Convidados', 'Frameworks'] as $cat)
                <button type="button" wire:click="selectCategory('{{ $cat }}')"
                        class="px-3.5 py-1.5 rounded-full text-sm font-medium border {{ $category === $cat ? 'bg-black text-white border-black' : 'bg-card text-stone border-sand hover:text-ink' }}">
                    {{ $cat }}
                </button>
            @endforeach

            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar aula..."
                   class="ms-auto px-3.5 py-1.5 rounded-full text-sm border border-sand bg-card text-ink placeholder:text-stone focus:outline-none focus:border-black">
        </div>

        <div class="mt-6 grid grid-cols-[repeat(auto-fill,minmax(240px,1fr))] gap-4">
            @forelse ($this->lessons as $lesson)
                <x-aula-card :lesson="$lesson" :watching="$this->featuredLessonId === $lesson->id" />
            @empty
                @if ($search !== '')
                    <p class="col-span-full text-stone">Nenhuma aula encontrada para "{{ $search }}".</p>
                @else
                    <p class="col-span-full text-stone">Nenhuma aula nesta categoria ainda.</p>
                @endif
            @endforelse
        </div>
    </div>

    <x-membros.footer />
</div>
```

to:

```blade
<div class="min-h-screen text-ink">
    <x-membros.header :initials="$this->userInitials" />

    @if ($this->totalCount > 0 || $this->canSeeEmptyCatalog)
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            <div class="mb-8">
                <h1 class="text-[clamp(26px,4vw,38px)] leading-[1.05] font-display font-extrabold tracking-[-0.015em] text-black">
                    Biblioteca de aulas
                </h1>
                <p class="mt-2 max-w-xl text-stone">
                    Todos os encontros gravados, aulas de convidados e frameworks em vídeo. Aperte o play e continue de onde parou.
                </p>
            </div>

            <x-lesson-player :lesson="$this->featuredLesson" :progress="$this->featuredProgress" :has-feedback="$this->featuredHasFeedback" />
            <x-nps-modal />

            <p class="mt-4 text-sm text-stone">
                Você está assistindo agora: <b class="font-semibold text-ink">{{ $this->featuredLesson && $this->featuredLesson->isAvailableFor(auth()->user()) ? $this->featuredLesson->title : '—' }}</b>
                · {{ $this->totalCount }} {{ Str::plural('aula', $this->totalCount) }} na sua biblioteca
            </p>

            <div class="mt-6 flex flex-wrap items-center gap-2">
                @foreach (['Tudo', 'Encontros', 'Convidados', 'Frameworks'] as $cat)
                    <button type="button" wire:click="selectCategory('{{ $cat }}')"
                            class="px-3.5 py-1.5 rounded-full text-sm font-medium border {{ $category === $cat ? 'bg-black text-white border-black' : 'bg-card text-stone border-sand hover:text-ink' }}">
                        {{ $cat }}
                    </button>
                @endforeach

                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar aula..."
                       class="ms-auto px-3.5 py-1.5 rounded-full text-sm border border-sand bg-card text-ink placeholder:text-stone focus:outline-none focus:border-black">
            </div>

            <div class="mt-6 grid grid-cols-[repeat(auto-fill,minmax(240px,1fr))] gap-4">
                @forelse ($this->lessons as $lesson)
                    <x-aula-card :lesson="$lesson" :watching="$this->featuredLessonId === $lesson->id" />
                @empty
                    @if ($search !== '')
                        <p class="col-span-full text-stone">Nenhuma aula encontrada para "{{ $search }}".</p>
                    @else
                        <p class="col-span-full text-stone">Nenhuma aula nesta categoria ainda.</p>
                    @endif
                @endforelse
            </div>
        </div>
    @else
        <x-catalog-empty-lock
            title="Sua biblioteca de aulas está sendo preparada."
            message="Em breve os primeiros conteúdos chegam por aqui." />
    @endif

    <x-membros.footer />
</div>
```

- [ ] **Step 5: Wrap the Frameworks view content in the lock check**

In `resources/views/livewire/membros/frameworks.blade.php`, change:

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

to:

```blade
<div class="min-h-screen text-ink">
    <x-membros.header :initials="$this->userInitials" />

    @if ($this->frameworks->isNotEmpty() || $this->canSeeEmptyCatalog)
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
    @else
        <x-catalog-empty-lock
            title="Os frameworks estão sendo preparados."
            message="Em breve as primeiras ferramentas chegam por aqui." />
    @endif

    <x-membros.footer />
</div>
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/Livewire/Membros/AulasTest.php tests/Feature/Livewire/Membros/FrameworksTest.php`
Expected: PASS (all tests in both files)

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/Membros/Aulas.php resources/views/livewire/membros/aulas.blade.php app/Livewire/Membros/Frameworks.php resources/views/livewire/membros/frameworks.blade.php tests/Feature/Livewire/Membros/AulasTest.php tests/Feature/Livewire/Membros/FrameworksTest.php
git commit -m "feat: lock Aulas and Frameworks behind an em-breve state when truly empty"
```

---

## Task 4: Replace the materials dropdown with a link to the new page

**Files:**
- Modify: `resources/views/components/lesson-player.blade.php`
- Test: `tests/Feature/Livewire/Membros/AulasTest.php`

**Interfaces:**
- Consumes: route `membros.aulas.materiais` (Task 2).
- Produces: nothing later tasks depend on — this is the final task.

- [ ] **Step 1: Write the failing test**

Append this method to `tests/Feature/Livewire/Membros/AulasTest.php` (inside the class, anywhere convenient — e.g. right after the two tests added in Task 3):

```php
    public function test_materiais_de_aula_link_points_to_the_dedicated_materials_page(): void
    {
        $course = $this->course();
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula com materiais',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
        ]);

        $this->actingAs(User::factory()->create());

        $this->get('/membros/aulas')
            ->assertOk()
            ->assertSee(route('membros.aulas.materiais', $lesson), false);
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/Livewire/Membros/AulasTest.php --filter test_materiais_de_aula_link_points_to_the_dedicated_materials_page`
Expected: FAIL — the dropdown still links nowhere (it's a `<button>`/`<span>`, not an `<a>` to the materials route).

- [ ] **Step 3: Replace the dropdown with a link**

In `resources/views/components/lesson-player.blade.php`, change:

```blade
    <div class="mt-4">
        @if ($lesson->materials->isNotEmpty())
            <div x-data="{ open: false }" class="relative inline-block">
                <button type="button" @click="open = !open" @click.outside="open = false"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-card border border-sand text-ink hover:bg-paper">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 fill-current">
                        <path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6Z"/>
                    </svg>
                    Materiais de aula
                </button>

                <div x-show="open" x-cloak x-transition
                     class="absolute left-0 z-10 mt-2 min-w-[14rem] rounded-lg border border-sand bg-card py-1 shadow-lg">
                    @foreach ($lesson->materials as $material)
                        @if ($material->hasUploadedFile())
                            <a href="{{ route('membros.materials.download', $material) }}"
                               class="block px-4 py-2 text-sm text-ink hover:bg-paper">
                                {{ $material->title }}
                            </a>
                        @else
                            <a href="{{ $material->file_url }}" target="_blank" rel="noopener"
                               class="block px-4 py-2 text-sm text-ink hover:bg-paper">
                                {{ $material->title }}
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        @else
            <span class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-card border border-sand text-stone cursor-not-allowed">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 fill-current">
                    <path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6Z"/>
                </svg>
                Materiais de aula
            </span>
        @endif
    </div>
```

to:

```blade
    <div class="mt-4">
        <a href="{{ route('membros.aulas.materiais', $lesson) }}" wire:navigate
           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-card border border-sand text-ink hover:bg-paper">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 fill-current">
                <path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6Z"/>
            </svg>
            Materiais de aula
        </a>
    </div>
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Feature/Livewire/Membros/AulasTest.php`
Expected: PASS (all tests)

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`
Expected: PASS, no regressions.

- [ ] **Step 6: Commit**

```bash
git add resources/views/components/lesson-player.blade.php tests/Feature/Livewire/Membros/AulasTest.php
git commit -m "feat: link Materiais de aula to the dedicated materials page instead of a dropdown"
```
