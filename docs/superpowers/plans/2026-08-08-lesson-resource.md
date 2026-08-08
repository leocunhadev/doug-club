# LessonResource + Materials Relation Manager Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a Filament `LessonResource` (full CRUD on `Lesson` at `/admin/lessons`) with a `LessonMaterial` relation manager on the edit page, per issue #17 (sub-issue 3/3, last, of #11).

**Architecture:** Same generator-first approach as issue #16's `CourseResource`: scaffold with `php artisan make:filament-resource`/`make:filament-relation-manager`, then hand-edit only the pieces that need customization beyond what schema introspection can infer (enum options, a friendly duration input, a real file upload, scope-appropriate table columns, a course filter, and a reorder feature that's only meaningful when scoped to one course). Two tasks: Task 1 builds `LessonResource` on its own (independently testable — list/create/edit/delete/filter/reorder); Task 2 adds the `MaterialsRelationManager` on top of it (independently testable — material CRUD from the lesson's edit page).

**Tech Stack:** Filament 4.12, same as issues #15/#16. Tests use `Livewire::test()` against the generated page/relation-manager classes.

## Global Constraints

- `course_id` Select/column/filter all use `course.label`, never `course.title` — the "Boas Vindas" course has `title => ''` (established in issue #16).
- `duration_seconds` is edited as a `mm:ss` / `h:mm:ss` string in the form, converted to/from an integer via `formatStateUsing`/`dehydrateStateUsing` — not a raw-seconds number input.
- `video_provider` is a `Select` with exactly two options (`vimeo` => `Vimeo`, `youtube` => `YouTube`) — never a free-text input, since `Lesson::embedUrl()` throws on any other value.
- `thumbnail_path` is a real `FileUpload` (disk `public`, directory `lesson-thumbnails`), not a text field — requires `php artisan storage:link` to have been run.
- Lessons table columns are exactly: `course.label`, `number`, `title`, `published_at`, `position`. The generator's other default columns (`duration_seconds`, `video_provider`, `video_id`, `thumbnail_path`, `created_at`, `updated_at`) must not be present in the final table.
- Reordering (`->reorderable('position', direction: 'desc')`) must only be active when the `course_id` table filter has a value — `position` orders lessons *within* a course, so reordering across an unfiltered (multi-course) list would be meaningless. Gate it with the `condition` parameter: `fn ($livewire): bool => filled($livewire->tableFilters['course_id']['value'] ?? null)`.
- The materials relation manager must **not** include `AssociateAction`, `DissociateAction`, or `DissociateBulkAction` — `lesson_materials.lesson_id` is `NOT NULL`, so "dissociate" (which sets the foreign key to `null`) would violate the database constraint. Only `CreateAction`, `EditAction`, `DeleteAction`, `DeleteBulkAction` are present.
- Record title attribute for `LessonResource` is `title` (unlike `Course`, `Lesson.title` is always non-empty).
- Full suite (`php artisan test`) must stay green after each task.

---

### Task 1: LessonResource (list, create, edit, delete, filter, scoped reorder)

**Files:**
- Create (via generator): `app/Filament/Resources/Lessons/LessonResource.php`
- Create (via generator): `app/Filament/Resources/Lessons/Schemas/LessonForm.php`
- Create (via generator): `app/Filament/Resources/Lessons/Tables/LessonsTable.php`
- Create (via generator): `app/Filament/Resources/Lessons/Pages/{ListLessons,CreateLesson,EditLesson}.php`
- Modify (hand-edit after generation): `app/Filament/Resources/Lessons/Schemas/LessonForm.php`
- Modify (hand-edit after generation): `app/Filament/Resources/Lessons/Tables/LessonsTable.php`
- Test: `tests/Feature/Admin/LessonResourceTest.php`

**Interfaces:**
- Consumes: `App\Models\User::canAccessPanel()` (issue #15) — inherited automatically, no new gating code.
- Produces: routes `filament.admin.resources.lessons.{index,create,edit}` at `/admin/lessons`, `/admin/lessons/create`, `/admin/lessons/{record}/edit`. `App\Filament\Resources\Lessons\Schemas\LessonForm::parseDuration(?string $value): ?int` and `::formatDuration(?int $seconds): ?string` (static, public) — Task 2 does not need these, but they must exist with these exact names/signatures for Task 1's own tests.

- [ ] **Step 1: Run the Filament resource generator**

Run:
```bash
php artisan make:filament-resource Lesson --generate --no-interaction --panel=admin --record-title-attribute=title
```
Expected: exits 0, output `INFO Filament resource [App\Filament\Resources\Lessons\LessonResource] created successfully.` Creates the 6 files listed above (resource, form, table, 3 pages).

- [ ] **Step 2: Run `storage:link`**

Run:
```bash
php artisan storage:link
```
Expected: exits 0, creates `public/storage` as a symlink to `storage/app/public`. If `public/storage` already exists (it doesn't as of this plan, but re-runs of this step should be safe), the command reports it's already linked and exits 0 — either outcome is fine, only a hard error should stop you here.

- [ ] **Step 3: Replace the generated `LessonForm.php`**

Read `app/Filament/Resources/Lessons/Schemas/LessonForm.php` first — the generator produces this (based on the `lessons` table's real columns):

```php
<?php

namespace App\Filament\Resources\Lessons\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LessonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('course_id')
                    ->relationship('course', 'title')
                    ->required(),
                TextInput::make('number')
                    ->required()
                    ->numeric(),
                TextInput::make('title')
                    ->required(),
                TextInput::make('duration_seconds')
                    ->numeric(),
                TextInput::make('video_provider')
                    ->required(),
                TextInput::make('video_id')
                    ->required(),
                TextInput::make('thumbnail_path'),
                DatePicker::make('published_at')
                    ->required(),
                TextInput::make('position')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
```

If the actual generated file differs from this, keep the generator's version of any field this plan doesn't otherwise touch and apply the same edits below relative to the real file. Replace the full file with:

```php
<?php

namespace App\Filament\Resources\Lessons\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LessonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('course_id')
                    ->relationship('course', 'label')
                    ->required(),
                TextInput::make('number')
                    ->required()
                    ->numeric(),
                TextInput::make('title')
                    ->required(),
                TextInput::make('duration_seconds')
                    ->label('Duration (mm:ss or h:mm:ss)')
                    ->placeholder('e.g. 5:30 or 1:15:30')
                    ->regex('/^(?:\d{1,3}:[0-5]\d|\d{1,2}:[0-5]\d:[0-5]\d)$/')
                    ->formatStateUsing(fn (?int $state): ?string => self::formatDuration($state))
                    ->dehydrateStateUsing(fn (?string $state): ?int => self::parseDuration($state)),
                Select::make('video_provider')
                    ->options([
                        'vimeo' => 'Vimeo',
                        'youtube' => 'YouTube',
                    ])
                    ->required(),
                TextInput::make('video_id')
                    ->required(),
                FileUpload::make('thumbnail_path')
                    ->disk('public')
                    ->directory('lesson-thumbnails')
                    ->image(),
                DatePicker::make('published_at')
                    ->required(),
                TextInput::make('position')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function parseDuration(?string $value): ?int
    {
        if (blank($value)) {
            return null;
        }

        $parts = array_map('intval', explode(':', $value));

        if (count($parts) === 2) {
            [$minutes, $seconds] = $parts;

            return ($minutes * 60) + $seconds;
        }

        [$hours, $minutes, $seconds] = $parts;

        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }

    public static function formatDuration(?int $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $remainingSeconds)
            : sprintf('%d:%02d', $minutes, $remainingSeconds);
    }
}
```

What changed: `course_id` now keys off `label`; `duration_seconds` gained the mm:ss/h:mm:ss
UX (label, placeholder, regex validation, `formatStateUsing`/`dehydrateStateUsing`, plus the two
new static helper methods); `video_provider` became a `Select` with fixed options instead of a
free-text `TextInput`; `thumbnail_path` became a `FileUpload` instead of a `TextInput`.

- [ ] **Step 4: Replace the generated `LessonsTable.php`**

Read `app/Filament/Resources/Lessons/Tables/LessonsTable.php` first — the generator produces
this:

```php
<?php

namespace App\Filament\Resources\Lessons\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LessonsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('course.title')
                    ->searchable(),
                TextColumn::make('number')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('duration_seconds')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('video_provider')
                    ->searchable(),
                TextColumn::make('video_id')
                    ->searchable(),
                TextColumn::make('thumbnail_path')
                    ->searchable(),
                TextColumn::make('published_at')
                    ->date()
                    ->sortable(),
                TextColumn::make('position')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
```

If the actual generated file differs in the columns this plan doesn't otherwise touch, keep the
generator's version of those and apply the same edits below. Replace the full file with:

```php
<?php

namespace App\Filament\Resources\Lessons\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LessonsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('course.label')
                    ->searchable(),
                TextColumn::make('number')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('published_at')
                    ->date()
                    ->sortable(),
                TextColumn::make('position')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('position', 'desc')
            ->reorderable(
                'position',
                condition: fn ($livewire): bool => filled($livewire->tableFilters['course_id']['value'] ?? null),
                direction: 'desc',
            )
            ->filters([
                SelectFilter::make('course_id')
                    ->relationship('course', 'label'),
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

What changed: dropped `duration_seconds`, `video_provider`, `video_id`, `thumbnail_path`,
`created_at`, `updated_at` columns; `course.title` column became `course.label`; added
`->defaultSort('position', 'desc')`, the conditional `->reorderable(...)`, and the `course_id`
`SelectFilter`; added `DeleteAction::make()` to `recordActions`; added the `DeleteAction` and
`SelectFilter` imports.

- [ ] **Step 5: Confirm the resource routes exist**

Run:
```bash
php artisan route:list --path=admin/lessons
```
Expected: exits 0, lists 3 routes (`.index`, `.create`, `.edit`), same shape as `admin/courses`
from issue #16.

- [ ] **Step 6: Write the feature test**

Create `tests/Feature/Admin/LessonResourceTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Lessons\Pages\CreateLesson;
use App\Filament\Resources\Lessons\Pages\EditLesson;
use App\Filament\Resources\Lessons\Pages\ListLessons;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LessonResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function course(string $label = 'Módulo 1'): Course
    {
        return Course::create([
            'label' => $label,
            'title' => 'Fundamentos',
            'description' => null,
            'position' => 10,
        ]);
    }

    public function test_non_admin_cannot_access_the_lessons_list(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->get('/admin/lessons');

        $response->assertForbidden();
    }

    public function test_admin_can_see_an_existing_lesson_in_the_list(): void
    {
        $lesson = Lesson::create([
            'course_id' => $this->course()->id,
            'number' => 1,
            'title' => 'Aula existente',
            'video_provider' => 'youtube',
            'video_id' => 'abc123',
            'published_at' => '2026-01-01',
            'position' => 10,
        ]);

        $this->actingAs($this->admin());

        Livewire::test(ListLessons::class)
            ->assertCanSeeTableRecords([$lesson]);
    }

    public function test_admin_can_create_a_lesson(): void
    {
        $course = $this->course();

        $this->actingAs($this->admin());

        Livewire::test(CreateLesson::class)
            ->fillForm([
                'course_id' => $course->id,
                'number' => 1,
                'title' => 'Aula de teste',
                'duration_seconds' => '5:30',
                'video_provider' => 'youtube',
                'video_id' => 'abc123',
                'published_at' => '2026-01-01',
                'position' => 10,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('lessons', [
            'course_id' => $course->id,
            'title' => 'Aula de teste',
            'duration_seconds' => 330,
            'video_provider' => 'youtube',
        ]);
    }

    public function test_admin_can_edit_a_lesson_and_duration_round_trips_through_the_form(): void
    {
        $lesson = Lesson::create([
            'course_id' => $this->course()->id,
            'number' => 1,
            'title' => 'Título original',
            'duration_seconds' => 125,
            'video_provider' => 'vimeo',
            'video_id' => 'xyz',
            'published_at' => '2026-01-01',
            'position' => 10,
        ]);

        $this->actingAs($this->admin());

        Livewire::test(EditLesson::class, ['record' => $lesson->getKey()])
            ->assertFormSet([
                'duration_seconds' => '2:05',
            ])
            ->fillForm([
                'title' => 'Título atualizado',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('lessons', [
            'id' => $lesson->id,
            'title' => 'Título atualizado',
            'duration_seconds' => 125,
        ]);
    }

    public function test_admin_can_delete_a_lesson(): void
    {
        $lesson = Lesson::create([
            'course_id' => $this->course()->id,
            'number' => 1,
            'title' => 'A remover',
            'video_provider' => 'youtube',
            'video_id' => 'abc123',
            'published_at' => '2026-01-01',
            'position' => 10,
        ]);

        $this->actingAs($this->admin());

        Livewire::test(ListLessons::class)
            ->callTableAction(\Filament\Actions\DeleteAction::class, record: $lesson);

        $this->assertDatabaseMissing('lessons', ['id' => $lesson->id]);
    }

    public function test_reordering_without_a_course_filter_does_nothing(): void
    {
        $course = $this->course();
        $a = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'A',
            'video_provider' => 'youtube', 'video_id' => 'a', 'published_at' => '2026-01-01', 'position' => 10,
        ]);
        $b = Lesson::create([
            'course_id' => $course->id, 'number' => 2, 'title' => 'B',
            'video_provider' => 'youtube', 'video_id' => 'b', 'published_at' => '2026-01-01', 'position' => 20,
        ]);

        $this->actingAs($this->admin());

        Livewire::test(ListLessons::class)
            ->call('reorderTable', [$a->getKey(), $b->getKey()]);

        $this->assertSame(10, $a->fresh()->position);
        $this->assertSame(20, $b->fresh()->position);
    }

    public function test_reordering_with_a_course_filter_gives_the_top_row_the_highest_position(): void
    {
        $course = $this->course();
        $a = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'A',
            'video_provider' => 'youtube', 'video_id' => 'a', 'published_at' => '2026-01-01', 'position' => 10,
        ]);
        $b = Lesson::create([
            'course_id' => $course->id, 'number' => 2, 'title' => 'B',
            'video_provider' => 'youtube', 'video_id' => 'b', 'published_at' => '2026-01-01', 'position' => 20,
        ]);

        $this->actingAs($this->admin());

        Livewire::test(ListLessons::class)
            ->filterTable('course_id', $course)
            ->call('reorderTable', [$a->getKey(), $b->getKey()]);

        $this->assertGreaterThan($b->fresh()->position, $a->fresh()->position);
    }
}
```

- [ ] **Step 7: Run the new test file**

Run:
```bash
php artisan test --filter=LessonResourceTest
```
Expected: PASS — all 7 tests green. If the duration tests fail, check that `formatStateUsing`
runs on load (125 seconds → `"2:05"`) and `dehydrateStateUsing` runs on save (`"5:30"` → 330)
exactly as written in Step 3 — a common mistake is swapping which callback does which
conversion direction.

- [ ] **Step 8: Run the full test suite**

Run:
```bash
php artisan test
```
Expected: PASS — every test green, including all tests from issues #15 and #16.

- [ ] **Step 9: Commit**

```bash
git add app/Filament/Resources/Lessons tests/Feature/Admin/LessonResourceTest.php public/storage
git commit -m "Add Filament LessonResource with scoped reorder and mm:ss duration input"
```
(`public/storage` is a symlink created by Step 2 — if your git configuration doesn't track
symlinks the way you expect, run `git status` first and add whatever `storage:link` actually
produced; the important thing is `LessonResource` and the test file are committed.)

---

### Task 2: Materials relation manager

**Files:**
- Create (via generator): `app/Filament/Resources/Lessons/RelationManagers/MaterialsRelationManager.php`
- Modify (hand-edit after generation): `app/Filament/Resources/Lessons/RelationManagers/MaterialsRelationManager.php`
- Modify: `app/Filament/Resources/Lessons/LessonResource.php` (register the relation manager)
- Test: `tests/Feature/Admin/LessonMaterialsRelationManagerTest.php`

**Interfaces:**
- Consumes: `App\Filament\Resources\Lessons\LessonResource` and its `Pages\EditLesson` (Task 1) — the relation manager mounts under the lesson's edit page. Consumes `App\Models\Lesson`'s `materials()` relationship (`app/Models/Lesson.php`, already exists, unchanged).
- Produces: nothing consumed by another task in this plan — this is the last task.

- [ ] **Step 1: Run the Filament relation manager generator**

Run:
```bash
php artisan make:filament-relation-manager Lessons materials title --generate --no-interaction --panel=admin --resource-namespace="App\Filament\Resources\Lessons"
```
Expected: exits 0, output includes `INFO Filament relation manager [App\Filament\Resources\Lessons\RelationManagers\MaterialsRelationManager] created successfully.` and a reminder to register it in `LessonResource::getRelations()` — that's Step 3 below. Creates
`app/Filament/Resources/Lessons/RelationManagers/MaterialsRelationManager.php`.

- [ ] **Step 2: Replace the generated `MaterialsRelationManager.php`**

Read the generated file first — it will look like this:

```php
<?php

namespace App\Filament\Resources\Lessons\RelationManagers;

use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
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
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
                AssociateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DissociateAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
```

If the actual generated file differs in the `form()` method or the `title`/`file_url` columns,
keep the generator's version of those and apply the same edits below. Replace the full file
with:

```php
<?php

namespace App\Filament\Resources\Lessons\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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
}
```

What changed: removed `AssociateAction` from `headerActions`; removed `DissociateAction` from
`recordActions`; removed `DissociateBulkAction` from the `toolbarActions` bulk group; removed
the now-unused `AssociateAction`/`DissociateAction`/`DissociateBulkAction` imports; dropped the
`created_at`/`updated_at` columns (out of the issue's stated scope, same discipline as Task 1's
table and issue #16's `CoursesTable`).

- [ ] **Step 3: Register the relation manager on `LessonResource`**

Read `app/Filament/Resources/Lessons/LessonResource.php` (created in Task 1, currently has an
empty `getRelations()`). Add the import and register it:

Find:
```php
use App\Filament\Resources\Lessons\Pages\CreateLesson;
use App\Filament\Resources\Lessons\Pages\EditLesson;
use App\Filament\Resources\Lessons\Pages\ListLessons;
use App\Filament\Resources\Lessons\Schemas\LessonForm;
```

Replace with:
```php
use App\Filament\Resources\Lessons\Pages\CreateLesson;
use App\Filament\Resources\Lessons\Pages\EditLesson;
use App\Filament\Resources\Lessons\Pages\ListLessons;
use App\Filament\Resources\Lessons\RelationManagers\MaterialsRelationManager;
use App\Filament\Resources\Lessons\Schemas\LessonForm;
```

Find:
```php
    public static function getRelations(): array
    {
        return [
            //
        ];
    }
```

Replace with:
```php
    public static function getRelations(): array
    {
        return [
            MaterialsRelationManager::class,
        ];
    }
```

- [ ] **Step 4: Write the feature test**

Create `tests/Feature/Admin/LessonMaterialsRelationManagerTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Lessons\Pages\EditLesson;
use App\Filament\Resources\Lessons\RelationManagers\MaterialsRelationManager;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LessonMaterialsRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function lesson(): Lesson
    {
        $course = Course::create([
            'label' => 'Módulo 1',
            'title' => 'Fundamentos',
            'description' => null,
            'position' => 10,
        ]);

        return Lesson::create([
            'course_id' => $course->id,
            'number' => 1,
            'title' => 'Aula de teste',
            'video_provider' => 'youtube',
            'video_id' => 'abc123',
            'published_at' => '2026-01-01',
            'position' => 10,
        ]);
    }

    private function testRelationManager(Lesson $lesson): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(MaterialsRelationManager::class, [
            'ownerRecord' => $lesson,
            'pageClass' => EditLesson::class,
        ]);
    }

    public function test_admin_can_create_a_material(): void
    {
        $lesson = $this->lesson();

        $this->actingAs($this->admin());

        $this->testRelationManager($lesson)
            ->callAction(CreateAction::class, data: [
                'title' => 'Apostila',
                'file_url' => 'https://example.com/apostila.pdf',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('lesson_materials', [
            'lesson_id' => $lesson->id,
            'title' => 'Apostila',
            'file_url' => 'https://example.com/apostila.pdf',
        ]);
    }

    public function test_admin_can_edit_a_material(): void
    {
        $lesson = $this->lesson();
        $material = LessonMaterial::create([
            'lesson_id' => $lesson->id,
            'title' => 'Original',
            'file_url' => 'https://example.com/original.pdf',
        ]);

        $this->actingAs($this->admin());

        $this->testRelationManager($lesson)
            ->callTableAction(EditAction::class, record: $material, data: [
                'title' => 'Atualizado',
                'file_url' => 'https://example.com/original.pdf',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('lesson_materials', [
            'id' => $material->id,
            'title' => 'Atualizado',
        ]);
    }

    public function test_admin_can_delete_a_material(): void
    {
        $lesson = $this->lesson();
        $material = LessonMaterial::create([
            'lesson_id' => $lesson->id,
            'title' => 'A remover',
            'file_url' => 'https://example.com/a-remover.pdf',
        ]);

        $this->actingAs($this->admin());

        $this->testRelationManager($lesson)
            ->callTableAction(DeleteAction::class, record: $material);

        $this->assertDatabaseMissing('lesson_materials', ['id' => $material->id]);
    }

    public function test_relation_manager_has_no_associate_or_dissociate_actions(): void
    {
        $lesson = $this->lesson();

        $this->actingAs($this->admin());

        $this->testRelationManager($lesson)
            ->assertActionDoesNotExist('associate')
            ->assertTableActionDoesNotExist('dissociate');
    }
}
```

- [ ] **Step 5: Run the new test file**

Run:
```bash
php artisan test --filter=LessonMaterialsRelationManagerTest
```
Expected: PASS — all 4 tests green.

- [ ] **Step 6: Run the full test suite**

Run:
```bash
php artisan test
```
Expected: PASS — every test green, including all tests from issues #15, #16, and Task 1 of this
plan.

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/Lessons/RelationManagers app/Filament/Resources/Lessons/LessonResource.php tests/Feature/Admin/LessonMaterialsRelationManagerTest.php
git commit -m "Add materials relation manager to LessonResource, closing #17"
```
