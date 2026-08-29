<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Encontros\Pages\CreateEncontro;
use App\Filament\Resources\Encontros\Pages\EditEncontro;
use App\Filament\Resources\Encontros\Pages\ListEncontros;
use App\Models\Course;
use App\Models\Encontro;
use App\Models\Lesson;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EncontroResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function encontro(): Encontro
    {
        return Encontro::create([
            'tema' => 'O comercial é gente', 'quem' => 'Com Douglas',
            'scheduled_at' => now()->addDay(),
        ]);
    }

    public function test_non_admin_cannot_access_the_encontros_list(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->get('/admin/encontros')->assertForbidden();
    }

    public function test_admin_can_see_an_existing_encontro_in_the_list(): void
    {
        $encontro = $this->encontro();

        $this->actingAs($this->admin());

        Livewire::test(ListEncontros::class)
            ->assertCanSeeTableRecords([$encontro]);
    }

    public function test_admin_can_create_an_encontro(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateEncontro::class)
            ->fillForm([
                'tema' => 'Precificação sem medo',
                'quem' => 'Convidada: Marina Prado',
                'scheduled_at' => '2026-09-15 19:00:00',
                'access_url' => 'https://zoom.us/j/123',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('encontros', [
            'tema' => 'Precificação sem medo',
            'quem' => 'Convidada: Marina Prado',
        ]);
    }

    public function test_admin_can_link_a_recording_when_creating_an_encontro(): void
    {
        $course = Course::create(['label' => 'Curso', 'title' => 'Teste', 'position' => 10]);
        $lesson = Lesson::create([
            'course_id' => $course->id, 'number' => 1, 'title' => 'Gravação',
            'video_provider' => 'vimeo', 'video_id' => '123', 'published_at' => '2026-01-01', 'position' => 1,
        ]);

        $this->actingAs($this->admin());

        Livewire::test(CreateEncontro::class)
            ->fillForm([
                'tema' => 'O comercial é gente',
                'quem' => 'Com Douglas',
                'scheduled_at' => '2026-06-17 19:00:00',
                'recording_lesson_id' => $lesson->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('encontros', [
            'tema' => 'O comercial é gente',
            'recording_lesson_id' => $lesson->id,
        ]);
    }

    public function test_admin_can_edit_an_encontro(): void
    {
        $encontro = $this->encontro();

        $this->actingAs($this->admin());

        Livewire::test(EditEncontro::class, ['record' => $encontro->getKey()])
            ->fillForm(['tema' => 'Tema atualizado'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('encontros', [
            'id' => $encontro->id,
            'tema' => 'Tema atualizado',
        ]);
    }

    public function test_admin_can_delete_an_encontro(): void
    {
        $encontro = $this->encontro();

        $this->actingAs($this->admin());

        Livewire::test(ListEncontros::class)
            ->callTableAction(DeleteAction::class, record: $encontro);

        $this->assertDatabaseMissing('encontros', ['id' => $encontro->id]);
    }
}
