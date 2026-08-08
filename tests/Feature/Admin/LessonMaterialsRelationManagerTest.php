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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_editing_an_upload_only_material_preserves_the_file_path(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->create('apostila.pdf', 10, 'application/pdf')
            ->store('lesson-materials', 'public');

        $lesson = $this->lesson();
        $material = LessonMaterial::create([
            'lesson_id' => $lesson->id,
            'title' => 'Original',
            'file_path' => $path,
        ]);

        $this->actingAs($this->admin());

        $this->testRelationManager($lesson)
            ->callTableAction(EditAction::class, record: $material, data: [
                'title' => 'Atualizado',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('lesson_materials', [
            'id' => $material->id,
            'title' => 'Atualizado',
            'file_path' => $path,
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

    public function test_relation_manager_has_no_associate_or_dissociate_actions(): void
    {
        $lesson = $this->lesson();

        $this->actingAs($this->admin());

        $this->testRelationManager($lesson)
            ->assertTableActionDoesNotExist('associate')
            ->assertTableActionDoesNotExist('dissociate');
    }
}
