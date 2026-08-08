<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Lessons\Pages\EditLesson;
use App\Filament\Resources\Lessons\RelationManagers\MaterialsRelationManager;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
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

    private function testRelationManager(Lesson $lesson): Testable
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
            ->callTableAction('create', data: [
                'title' => 'Apostila',
                'file_url' => 'https://example.com/apostila.pdf',
            ])
            ->assertHasNoTableActionErrors();

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
