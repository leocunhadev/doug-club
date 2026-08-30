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

    public function test_ver_aula_shows_locked_state_when_the_linked_lesson_is_unavailable_to_the_viewer(): void
    {
        $course = Course::create(['label' => 'Curso', 'title' => 'Teste', 'position' => 10]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Aula CLUB',
            'video_provider' => 'youtube', 'video_id' => 'abc', 'published_at' => '2026-01-01', 'position' => 1,
            'tier' => 'club',
        ]);
        Framework::create([
            'code' => '4S', 'title' => 'Com aula CLUB', 'description' => 'Teste',
            'lesson_id' => $lesson->id, 'position' => 10,
        ]);

        $this->actingAs(User::factory()->create(['tier' => 'start']));

        $html = Livewire::test(Frameworks::class)->html();

        $this->assertStringNotContainsString(route('membros.aulas', ['lesson' => $lesson->id]), $html);
        $this->assertStringContainsString('Exclusivo CLUB', $html);
    }
}
