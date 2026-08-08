<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Courses\Pages\CreateCourse;
use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Filament\Resources\Courses\Pages\ListCourses;
use App\Models\Course;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CourseResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_non_admin_cannot_access_the_courses_list(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->get('/admin/courses');

        $response->assertForbidden();
    }

    public function test_admin_can_see_an_existing_course_in_the_list(): void
    {
        $course = Course::create([
            'label' => 'Módulo 1',
            'title' => 'Fundamentos',
            'description' => null,
            'position' => 10,
        ]);

        $this->actingAs($this->admin());

        Livewire::test(ListCourses::class)
            ->assertCanSeeTableRecords([$course]);
    }

    public function test_admin_can_create_a_course(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateCourse::class)
            ->fillForm([
                'label' => 'Módulo 2',
                'title' => 'Modelos de Negócio',
                'description' => 'Segundo módulo do curso.',
                'position' => 20,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('courses', [
            'label' => 'Módulo 2',
            'title' => 'Modelos de Negócio',
            'position' => 20,
        ]);
    }

    public function test_admin_can_edit_a_course(): void
    {
        $course = Course::create([
            'label' => 'Módulo 3',
            'title' => 'Título original',
            'description' => null,
            'position' => 30,
        ]);

        $this->actingAs($this->admin());

        Livewire::test(EditCourse::class, ['record' => $course->getKey()])
            ->fillForm([
                'title' => 'Título atualizado',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'title' => 'Título atualizado',
        ]);
    }

    public function test_admin_can_delete_a_course(): void
    {
        $course = Course::create([
            'label' => 'Módulo 4',
            'title' => 'A remover',
            'description' => null,
            'position' => 40,
        ]);

        $this->actingAs($this->admin());

        Livewire::test(ListCourses::class)
            ->callTableAction(DeleteAction::class, record: $course);

        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
    }

    public function test_admin_can_edit_a_course_with_an_empty_title(): void
    {
        $course = Course::create([
            'label' => 'Boas Vindas',
            'title' => '',
            'description' => null,
            'position' => 50,
        ]);

        $this->actingAs($this->admin());

        Livewire::test(EditCourse::class, ['record' => $course->getKey()])
            ->fillForm([
                'position' => 55,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'title' => '',
            'position' => 55,
        ]);
    }

    public function test_reordering_gives_the_top_row_the_highest_position(): void
    {
        $low = Course::create(['label' => 'A', 'title' => 'A', 'description' => null, 'position' => 10]);
        $high = Course::create(['label' => 'B', 'title' => 'B', 'description' => null, 'position' => 20]);

        $this->actingAs($this->admin());

        // Reorder so $low's id comes first in the new order (i.e. dragged to the top).
        Livewire::test(ListCourses::class)
            ->call('reorderTable', [$low->getKey(), $high->getKey()]);

        $this->assertGreaterThan($high->fresh()->position, $low->fresh()->position);
    }
}
