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

    public function test_downloading_a_material_whose_file_is_missing_from_disk_returns_404(): void
    {
        Storage::fake('public');

        $material = $this->material(['file_path' => 'lesson-materials/does-not-exist.pdf']);

        $this->actingAs(User::factory()->create());

        $response = $this->get(route('membros.materials.download', $material));

        $response->assertNotFound();
    }

    public function test_downloading_a_material_with_a_slash_in_the_title_does_not_error(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->create('apostila.pdf', 10, 'application/pdf')
            ->store('lesson-materials', 'public');

        $material = $this->material(['title' => 'Apostila 1/2', 'file_path' => $path]);

        $this->actingAs(User::factory()->create());

        $response = $this->get(route('membros.materials.download', $material));

        $response->assertOk();
        $response->assertDownload('Apostila 1-2.pdf');
    }
}
