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
