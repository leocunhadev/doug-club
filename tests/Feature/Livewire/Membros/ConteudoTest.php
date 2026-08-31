<?php

namespace Tests\Feature\Livewire\Membros;

use App\Livewire\Membros\Conteudo;
use App\Models\Course;
use App\Models\Encontro;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ConteudoTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/mentor/conteudo')->assertRedirect('/login');
    }

    public function test_club_member_is_redirected_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'club']));

        $this->get('/mentor/conteudo')->assertRedirect('/');
    }

    public function test_mentor_can_access_the_page(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));

        $this->get('/mentor/conteudo')->assertOk();
    }

    public function test_publishing_a_lesson_creates_it_with_smart_defaults(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));

        Livewire::test(Conteudo::class)
            ->set('lessonTitle', 'Como precificar sem medo')
            ->set('lessonVideoUrl', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
            ->set('lessonTier', 'club')
            ->call('publishLesson')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('lessons', [
            'title' => 'Como precificar sem medo',
            'video_provider' => 'youtube',
            'video_id' => 'dQw4w9WgXcQ',
            'tier' => 'club',
            'category' => 'Encontros',
            'position' => 0,
        ]);

        $course = Course::query()->where('label', 'Publicações rápidas')->firstOrFail();
        $this->assertDatabaseHas('lessons', ['course_id' => $course->id]);
    }

    public function test_publishing_a_lesson_reuses_the_same_quick_publish_course(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));

        Livewire::test(Conteudo::class)
            ->set('lessonTitle', 'Aula um')
            ->set('lessonVideoUrl', 'https://vimeo.com/76979871')
            ->set('lessonTier', 'start')
            ->call('publishLesson');

        Livewire::test(Conteudo::class)
            ->set('lessonTitle', 'Aula dois')
            ->set('lessonVideoUrl', 'https://vimeo.com/76979872')
            ->set('lessonTier', 'start')
            ->call('publishLesson');

        $this->assertSame(1, Course::query()->where('label', 'Publicações rápidas')->count());

        $numbers = Lesson::query()->orderBy('number')->pluck('number')->all();
        $this->assertSame([1, 2], $numbers);
    }

    public function test_publishing_a_lesson_with_an_unrecognized_video_url_shows_an_error(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));

        Livewire::test(Conteudo::class)
            ->set('lessonTitle', 'Aula qualquer')
            ->set('lessonVideoUrl', 'https://example.com/not-a-real-video')
            ->set('lessonTier', 'start')
            ->call('publishLesson')
            ->assertHasErrors('lessonVideoUrl');

        $this->assertDatabaseMissing('lessons', ['title' => 'Aula qualquer']);
    }

    public function test_publishing_an_encontro_creates_it(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));

        $scheduledAt = now()->addDays(5)->setTime(19, 0);

        Livewire::test(Conteudo::class)
            ->set('encontroTema', 'Precificação sem medo')
            ->set('encontroQuem', 'Convidada: Marina Prado')
            ->set('encontroScheduledAt', $scheduledAt->format('Y-m-d\TH:i'))
            ->call('publishEncontro')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('encontros', [
            'tema' => 'Precificação sem medo',
            'quem' => 'Convidada: Marina Prado',
        ]);
    }

    public function test_publishing_an_encontro_in_the_past_shows_an_error(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));

        Livewire::test(Conteudo::class)
            ->set('encontroTema', 'Tema qualquer')
            ->set('encontroQuem', 'Com Douglas')
            ->set('encontroScheduledAt', now()->subDay()->format('Y-m-d\TH:i'))
            ->call('publishEncontro')
            ->assertHasErrors('encontroScheduledAt');

        $this->assertDatabaseMissing('encontros', ['tema' => 'Tema qualquer']);
    }
}
