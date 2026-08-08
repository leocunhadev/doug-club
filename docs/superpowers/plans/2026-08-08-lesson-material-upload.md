# Lesson Material Upload + Forced Download Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let admins upload a material file (PDF/Word) as an alternative to an external URL, and let members force-download uploaded materials instead of relying on the browser's default handling of a direct link.

**Architecture:** Task 1 covers the data model and admin side: a nullable `file_path` column alongside the now-nullable `file_url`, with Filament's native `requiredWithout()` enforcing "at least one of the two." Task 2 covers the member-facing side: a dedicated download route + invokable controller that streams the file with a forced `Content-Disposition: attachment`, and the one Blade template that renders material links today. The two are independently testable — Task 1 is done when admins can manage both kinds of materials; Task 2 is done when members can download them.

**Tech Stack:** Laravel 13 (SQLite in dev — column nullability changes verified to work natively via `->change()`, no `doctrine/dbal` required, confirmed empirically against this project's installed packages), Filament 4.12 (same `MaterialsRelationManager` from issue #17), Blade for the member-facing view.

## Global Constraints

- `file_url` and `file_path` are each optional individually, but a material must have **at least one** — enforced via Filament's `->requiredWithout()` on both fields, not a custom validation rule.
- Accepted upload types: PDF, `.doc`, `.docx` only — `acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])`.
- Upload storage: disk `public`, directory `lesson-materials` (same disk already configured and linked for lesson thumbnails in issue #17 — no new `storage:link` needed).
- Materials with only `file_url` (no uploaded file) keep today's exact behavior: direct external link, `target="_blank"`, no download route involved.
- Materials with an uploaded file always go through the new download route, never a direct `/storage/...` link — the whole point is forcing the download instead of letting the browser preview PDFs inline.
- The download route requires `auth` + `verified`, matching the existing `/membros` route's middleware — no additional per-course authorization, since the app has no paywall/access-scoping today (any authenticated, verified member can already see every lesson).
- Full suite (`php artisan test`) must stay green after each task.

---

### Task 1: Schema, model, and admin form/table for material uploads

**Files:**
- Create: `database/migrations/2026_08_08_160000_add_file_path_to_lesson_materials_table.php`
- Modify: `app/Models/LessonMaterial.php`
- Modify: `app/Filament/Resources/Lessons/RelationManagers/MaterialsRelationManager.php`
- Modify: `tests/Feature/Admin/LessonMaterialsRelationManagerTest.php`
- Test: `tests/Unit/LessonMaterialTest.php`

**Interfaces:**
- Produces: `LessonMaterial::hasUploadedFile(): bool` — Task 2's controller and the Blade view both call this to decide which link/route to use.

- [ ] **Step 1: Write the failing unit test for `hasUploadedFile()`**

Create `tests/Unit/LessonMaterialTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonMaterialTest extends TestCase
{
    use RefreshDatabase;

    private function lesson(): Lesson
    {
        $course = Course::create([
            'label' => 'Módulo 1', 'title' => 'Fundamentos', 'description' => null, 'position' => 10,
        ]);

        return Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula de teste',
            'video_provider' => 'youtube', 'video_id' => 'abc123', 'published_at' => '2026-01-01', 'position' => 10,
        ]);
    }

    public function test_has_uploaded_file_is_true_when_file_path_is_set(): void
    {
        $material = LessonMaterial::create([
            'lesson_id' => $this->lesson()->id,
            'title' => 'Apostila',
            'file_path' => 'lesson-materials/abc.pdf',
        ]);

        $this->assertTrue($material->hasUploadedFile());
    }

    public function test_has_uploaded_file_is_false_when_file_path_is_null(): void
    {
        $material = LessonMaterial::create([
            'lesson_id' => $this->lesson()->id,
            'title' => 'Slides',
            'file_url' => 'https://example.com/slides.pdf',
        ]);

        $this->assertFalse($material->hasUploadedFile());
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=LessonMaterialTest -v`
Expected: FAIL — `file_path` isn't in `LessonMaterial::$fillable` yet, so `LessonMaterial::create(['file_path' => ...])` silently drops it (the first test fails on the assertion, not a hard error), and `hasUploadedFile()` doesn't exist yet (fatal error: call to undefined method).

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_08_08_160000_add_file_path_to_lesson_materials_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_materials', function (Blueprint $table) {
            $table->string('file_url')->nullable()->change();
            $table->string('file_path')->nullable()->after('file_url');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_materials', function (Blueprint $table) {
            $table->dropColumn('file_path');
            $table->string('file_url')->nullable(false)->change();
        });
    }
};
```

- [ ] **Step 4: Run the migration**

Run: `php artisan migrate`
Expected: exits 0, output includes
`2026_08_08_160000_add_file_path_to_lesson_materials_table ... DONE`.

- [ ] **Step 5: Update `LessonMaterial`**

Read `app/Models/LessonMaterial.php` first — current content:

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
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
```

Replace with:

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

- [ ] **Step 6: Run the unit test to verify it passes**

Run: `php artisan test --filter=LessonMaterialTest -v`
Expected: PASS — both tests green.

- [ ] **Step 7: Update `MaterialsRelationManager`**

Read `app/Filament/Resources/Lessons/RelationManagers/MaterialsRelationManager.php` first — current
`form()`/`table()`:

```php
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                TextInput::make('file_url')
                    ->url()
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('file_url')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
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
```

Replace the whole file with:

```php
<?php

namespace App\Filament\Resources\Lessons\RelationManagers;

use App\Models\LessonMaterial;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MaterialsRelationManager extends RelationManager
{
    protected static string $relationship = 'materials';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                TextInput::make('file_url')
                    ->label('External URL')
                    ->url()
                    ->requiredWithout('file_path'),
                FileUpload::make('file_path')
                    ->label('File (PDF/Word)')
                    ->disk('public')
                    ->directory('lesson-materials')
                    ->acceptedFileTypes([
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    ])
                    ->requiredWithout('file_url'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('tipo')
                    ->label('Type')
                    ->state(fn (LessonMaterial $record): string => $record->hasUploadedFile() ? 'Upload' : 'Link')
                    ->badge(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
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

What changed: `file_url` is no longer `->required()`, gained `->requiredWithout('file_path')` and an
explicit label; new `file_path` `FileUpload` field with the accepted-types restriction and
`->requiredWithout('file_url')`; the `file_url` table column became a computed `tipo` badge column
showing "Upload" or "Link"; added the `LessonMaterial` and `FileUpload` imports.

- [ ] **Step 8: Add regression tests to `LessonMaterialsRelationManagerTest.php`**

Read `tests/Feature/Admin/LessonMaterialsRelationManagerTest.php` first — it already has
`test_admin_can_create_a_material` (via `file_url` only), `test_admin_can_edit_a_material`,
`test_admin_can_delete_a_material`, and the associate/dissociate guard test, plus a private
`lesson()` helper and a `testRelationManager()` helper. `LessonMaterial` is already imported.

Add these two imports to the existing `use` block at the top of the file:

```php
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
```

Then add these three test methods to the class (anywhere after the existing
`admin()`/`lesson()`/`testRelationManager()` helpers):

```php
    public function test_admin_can_create_a_material_with_only_an_uploaded_file(): void
    {
        Storage::fake('public');

        $lesson = $this->lesson();

        $this->actingAs($this->admin());

        $this->testRelationManager($lesson)
            ->callTableAction('create', data: [
                'title' => 'Apostila em PDF',
                'file_path' => UploadedFile::fake()->create('apostila.pdf', 10, 'application/pdf'),
            ])
            ->assertHasNoTableActionErrors();

        $material = LessonMaterial::where('title', 'Apostila em PDF')->firstOrFail();
        $this->assertTrue($material->hasUploadedFile());
        Storage::disk('public')->assertExists($material->file_path);
    }

    public function test_creating_a_material_without_a_url_or_file_fails_validation(): void
    {
        $lesson = $this->lesson();

        $this->actingAs($this->admin());

        $this->testRelationManager($lesson)
            ->callTableAction('create', data: [
                'title' => 'Sem arquivo nem link',
            ])
            ->assertHasTableActionErrors(['file_url', 'file_path']);

        $this->assertDatabaseMissing('lesson_materials', ['title' => 'Sem arquivo nem link']);
    }

    public function test_admin_can_create_a_material_with_only_a_url(): void
    {
        $lesson = $this->lesson();

        $this->actingAs($this->admin());

        $this->testRelationManager($lesson)
            ->callTableAction('create', data: [
                'title' => 'Link externo',
                'file_url' => 'https://example.com/material.pdf',
            ])
            ->assertHasNoTableActionErrors();

        $material = LessonMaterial::where('title', 'Link externo')->firstOrFail();
        $this->assertFalse($material->hasUploadedFile());
    }
```

(The existing `test_admin_can_create_a_material` test already covers the "URL only, works" case in
practice, but `test_admin_can_create_a_material_with_only_a_url` above makes the "works without any
file" assertion explicit and paired with the new "fails with neither" test — keep both.)

- [ ] **Step 9: Run the relation manager test file**

Run: `php artisan test --filter=LessonMaterialsRelationManagerTest -v`
Expected: PASS — all 7 tests green (4 existing + 3 new).

- [ ] **Step 10: Run the full test suite**

Run: `php artisan test`
Expected: PASS — every test green.

- [ ] **Step 11: Commit**

```bash
git add database/migrations/2026_08_08_160000_add_file_path_to_lesson_materials_table.php app/Models/LessonMaterial.php app/Filament/Resources/Lessons/RelationManagers/MaterialsRelationManager.php tests/Feature/Admin/LessonMaterialsRelationManagerTest.php tests/Unit/LessonMaterialTest.php
git commit -m "Add file upload option to lesson materials"
```

---

### Task 2: Forced download route for members

**Files:**
- Create: `app/Http/Controllers/Membros/LessonMaterialDownloadController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/livewire/membros/dashboard.blade.php`
- Test: `tests/Feature/Membros/LessonMaterialDownloadTest.php`

**Interfaces:**
- Consumes: `App\Models\LessonMaterial::hasUploadedFile(): bool` (Task 1).
- Produces: named route `membros.materials.download`, taking a `LessonMaterial` route-bound
  parameter named `material`.

- [ ] **Step 1: Write the failing feature test**

Create `tests/Feature/Membros/LessonMaterialDownloadTest.php`:

```php
<?php

namespace Tests\Feature\Membros;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LessonMaterialDownloadTest extends TestCase
{
    use RefreshDatabase;

    private function material(array $overrides = []): LessonMaterial
    {
        $course = Course::create([
            'label' => 'Módulo 1', 'title' => 'Fundamentos', 'description' => null, 'position' => 10,
        ]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula de teste',
            'video_provider' => 'youtube', 'video_id' => 'abc123', 'published_at' => '2026-01-01', 'position' => 10,
        ]);

        return LessonMaterial::create(array_merge([
            'lesson_id' => $lesson->id,
            'title' => 'Apostila',
        ], $overrides));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $material = $this->material(['file_path' => 'lesson-materials/x.pdf']);

        $response = $this->get(route('membros.materials.download', $material));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_member_can_download_an_uploaded_material(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->create('apostila.pdf', 10, 'application/pdf')
            ->store('lesson-materials', 'public');

        $material = $this->material(['file_path' => $path]);

        $this->actingAs(User::factory()->create());

        $response = $this->get(route('membros.materials.download', $material));

        $response->assertOk();
        $response->assertDownload('Apostila.pdf');
    }

    public function test_downloading_a_material_without_an_uploaded_file_returns_404(): void
    {
        $material = $this->material(['file_url' => 'https://example.com/x.pdf']);

        $this->actingAs(User::factory()->create());

        $response = $this->get(route('membros.materials.download', $material));

        $response->assertNotFound();
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=LessonMaterialDownloadTest -v`
Expected: FAIL — the `membros.materials.download` named route doesn't exist yet, so
`route('membros.materials.download', $material)` throws
`Symfony\Component\Routing\Exception\RouteNotFoundException`.

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Membros/LessonMaterialDownloadController.php`:

```php
<?php

namespace App\Http\Controllers\Membros;

use App\Http\Controllers\Controller;
use App\Models\LessonMaterial;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LessonMaterialDownloadController extends Controller
{
    public function __invoke(LessonMaterial $material): StreamedResponse
    {
        abort_unless($material->hasUploadedFile(), 404);

        $extension = pathinfo($material->file_path, PATHINFO_EXTENSION);

        return Storage::disk('public')->download(
            $material->file_path,
            "{$material->title}.{$extension}",
        );
    }
}
```

- [ ] **Step 4: Register the route**

Read `routes/web.php` first — current content:

```php
<?php

use App\Livewire\Membros\Dashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::get('membros', Dashboard::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
```

Replace with:

```php
<?php

use App\Http\Controllers\Membros\LessonMaterialDownloadController;
use App\Livewire\Membros\Dashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::get('membros', Dashboard::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('membros/materiais/{material}/download', LessonMaterialDownloadController::class)
    ->middleware(['auth', 'verified'])
    ->name('membros.materials.download');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=LessonMaterialDownloadTest -v`
Expected: PASS — all 3 tests green.

- [ ] **Step 6: Update the materials dropdown in the dashboard view**

Read `resources/views/livewire/membros/dashboard.blade.php` first — the relevant block (inside
the materials dropdown, around line 38):

```blade
                                @foreach ($lesson->materials as $material)
                                    <a href="{{ $material->file_url }}" target="_blank" rel="noopener"
                                       class="block px-4 py-2 text-sm text-gray-200 hover:bg-slate-800/60">
                                        {{ $material->title }}
                                    </a>
                                @endforeach
```

Replace with:

```blade
                                @foreach ($lesson->materials as $material)
                                    @if ($material->hasUploadedFile())
                                        <a href="{{ route('membros.materials.download', $material) }}"
                                           class="block px-4 py-2 text-sm text-gray-200 hover:bg-slate-800/60">
                                            {{ $material->title }}
                                        </a>
                                    @else
                                        <a href="{{ $material->file_url }}" target="_blank" rel="noopener"
                                           class="block px-4 py-2 text-sm text-gray-200 hover:bg-slate-800/60">
                                            {{ $material->title }}
                                        </a>
                                    @endif
                                @endforeach
```

- [ ] **Step 7: Add a coverage case to the existing Dashboard test**

Read `tests/Feature/Livewire/Membros/DashboardTest.php`'s
`test_dashboard_renders_featured_lesson_embed_and_materials` test (around line 116) — it already
creates a `file_url`-only material and asserts the dashboard renders it; this step adds a second
material with an uploaded file to the same test to confirm the new branch also renders. Current:

```php
    public function test_dashboard_renders_featured_lesson_embed_and_materials(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['label' => 'Módulo 4', 'title' => 'Modelos de Negócio', 'position' => 40]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 5, 'title' => 'Aula 05',
            'video_provider' => 'youtube', 'video_id' => 'dQw4w9WgXcQ', 'published_at' => '2026-07-17', 'position' => 1,
        ]);
        $lesson->materials()->create(['title' => 'Slides', 'file_url' => 'https://example.com/slides.pdf']);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSee('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertSee('Slides')
            ->assertSee('Aula 05')
            ->assertSee('Módulo 4');
    }
```

Replace with:

```php
    public function test_dashboard_renders_featured_lesson_embed_and_materials(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['label' => 'Módulo 4', 'title' => 'Modelos de Negócio', 'position' => 40]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 5, 'title' => 'Aula 05',
            'video_provider' => 'youtube', 'video_id' => 'dQw4w9WgXcQ', 'published_at' => '2026-07-17', 'position' => 1,
        ]);
        $lesson->materials()->create(['title' => 'Slides', 'file_url' => 'https://example.com/slides.pdf']);
        $uploaded = $lesson->materials()->create(['title' => 'Apostila', 'file_path' => 'lesson-materials/apostila.pdf']);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSee('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertSee('Slides')
            ->assertSee('Apostila')
            ->assertSee(route('membros.materials.download', $uploaded), false)
            ->assertSee('Aula 05')
            ->assertSee('Módulo 4');
    }
```

- [ ] **Step 8: Run the Dashboard test file**

Run: `php artisan test --filter=DashboardTest -v`
Expected: PASS — all tests in the file green, including the updated one.

- [ ] **Step 9: Run the full test suite**

Run: `php artisan test`
Expected: PASS — every test green.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/Membros/LessonMaterialDownloadController.php routes/web.php resources/views/livewire/membros/dashboard.blade.php tests/Feature/Membros/LessonMaterialDownloadTest.php tests/Feature/Livewire/Membros/DashboardTest.php
git commit -m "Add forced-download route for uploaded lesson materials"
```
