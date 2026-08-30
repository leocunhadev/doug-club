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
            'tier' => 'start',
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
