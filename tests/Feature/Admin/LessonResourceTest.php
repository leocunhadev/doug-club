<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Lessons\Pages\CreateLesson;
use App\Filament\Resources\Lessons\Pages\EditLesson;
use App\Filament\Resources\Lessons\Pages\ListLessons;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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

    public function test_admin_can_upload_a_thumbnail_and_it_resolves_to_a_public_storage_url(): void
    {
        Storage::fake('public');

        $course = $this->course();

        $this->actingAs($this->admin());

        Livewire::test(CreateLesson::class)
            ->fillForm([
                'course_id' => $course->id,
                'number' => 1,
                'title' => 'Aula com thumbnail',
                'video_provider' => 'youtube',
                'video_id' => 'abc123',
                'thumbnail_path' => UploadedFile::fake()->image('thumb.jpg'),
                'published_at' => '2026-01-01',
                'position' => 10,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $lesson = Lesson::where('title', 'Aula com thumbnail')->firstOrFail();

        Storage::disk('public')->assertExists($lesson->thumbnail_path);
        $this->assertSame(
            Storage::disk('public')->url($lesson->thumbnail_path),
            $lesson->thumbnail_url,
        );
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
            ->callTableAction(DeleteAction::class, record: $lesson);

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

    public function test_creating_a_vimeo_lesson_autofills_and_locks_duration_on_a_valid_video_id(): void
    {
        Http::fake([
            'vimeo.com/api/oembed.json*' => Http::response(['duration' => 125], 200),
        ]);

        $course = $this->course();

        $this->actingAs($this->admin());

        Livewire::test(CreateLesson::class)
            ->fillForm([
                'course_id' => $course->id,
                'number' => 1,
                'title' => 'Aula vimeo',
                'video_provider' => 'vimeo',
                'published_at' => '2026-01-01',
                'position' => 10,
            ])
            ->set('data.video_id', 'xyz')
            ->assertFormSet(['duration_seconds' => '2:05'])
            ->assertFormFieldDisabled('duration_seconds')
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('lessons', [
            'title' => 'Aula vimeo',
            'duration_seconds' => 125,
        ]);
    }

    public function test_creating_a_vimeo_lesson_leaves_duration_editable_when_the_vimeo_lookup_fails(): void
    {
        Http::fake([
            'vimeo.com/api/oembed.json*' => Http::response(['error' => 'not found'], 404),
        ]);

        $course = $this->course();

        $this->actingAs($this->admin());

        Livewire::test(CreateLesson::class)
            ->fillForm([
                'course_id' => $course->id,
                'number' => 1,
                'title' => 'Aula vimeo invalida',
                'video_provider' => 'vimeo',
                'published_at' => '2026-01-01',
                'position' => 10,
            ])
            ->set('data.video_id', 'does-not-exist')
            ->assertFormFieldEnabled('duration_seconds')
            ->assertNotified('Não foi possível obter a duração do Vimeo automaticamente')
            ->fillForm(['duration_seconds' => '3:00'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('lessons', [
            'title' => 'Aula vimeo invalida',
            'duration_seconds' => 180,
        ]);
    }

    public function test_creating_a_youtube_lesson_never_calls_vimeo_and_keeps_duration_editable(): void
    {
        Http::fake();

        $course = $this->course();

        $this->actingAs($this->admin());

        Livewire::test(CreateLesson::class)
            ->fillForm([
                'course_id' => $course->id,
                'number' => 1,
                'title' => 'Aula youtube',
                'video_provider' => 'youtube',
                'published_at' => '2026-01-01',
                'position' => 10,
            ])
            ->set('data.video_id', 'abc123')
            ->assertFormFieldEnabled('duration_seconds')
            ->fillForm(['duration_seconds' => '4:15'])
            ->call('create')
            ->assertHasNoFormErrors();

        Http::assertNothingSent();

        $this->assertDatabaseHas('lessons', [
            'title' => 'Aula youtube',
            'duration_seconds' => 255,
        ]);
    }

    public function test_editing_an_existing_vimeo_lesson_opens_with_duration_already_locked(): void
    {
        Http::fake();

        $lesson = Lesson::create([
            'course_id' => $this->course()->id,
            'number' => 1,
            'title' => 'Aula vimeo existente',
            'duration_seconds' => 125,
            'video_provider' => 'vimeo',
            'video_id' => 'xyz',
            'published_at' => '2026-01-01',
            'position' => 10,
        ]);

        $this->actingAs($this->admin());

        Livewire::test(EditLesson::class, ['record' => $lesson->getKey()])
            ->assertFormSet(['duration_seconds' => '2:05'])
            ->assertFormFieldDisabled('duration_seconds');

        Http::assertNothingSent();
    }
}
