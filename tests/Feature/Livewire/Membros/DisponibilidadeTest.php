<?php

namespace Tests\Feature\Livewire\Membros;

use App\Livewire\Membros\Disponibilidade;
use App\Models\MentorAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DisponibilidadeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/membros/mentor/disponibilidade')->assertRedirect('/login');
    }

    public function test_club_member_is_redirected_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'club']));

        $this->get('/membros/mentor/disponibilidade')->assertRedirect('/membros');
    }

    public function test_mentor_can_add_a_block(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $this->actingAs($mentor);

        Livewire::test(Disponibilidade::class)
            ->set('dayOfWeek', '2')
            ->set('startTime', '09:00')
            ->set('endTime', '12:00')
            ->call('addBlock');

        $this->assertDatabaseHas('mentor_availabilities', [
            'mentor_id' => $mentor->id, 'day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '12:00',
        ]);
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $this->actingAs($mentor);

        Livewire::test(Disponibilidade::class)
            ->set('dayOfWeek', '2')
            ->set('startTime', '12:00')
            ->set('endTime', '09:00')
            ->call('addBlock')
            ->assertHasErrors('endTime');

        $this->assertDatabaseMissing('mentor_availabilities', ['mentor_id' => $mentor->id]);
    }

    public function test_overlapping_block_on_the_same_day_is_rejected(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        MentorAvailability::create([
            'mentor_id' => $mentor->id, 'day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '12:00',
        ]);

        $this->actingAs($mentor);

        Livewire::test(Disponibilidade::class)
            ->set('dayOfWeek', '2')
            ->set('startTime', '11:00')
            ->set('endTime', '13:00')
            ->call('addBlock')
            ->assertHasErrors('startTime');

        $this->assertSame(1, MentorAvailability::where('mentor_id', $mentor->id)->count());
    }

    public function test_non_overlapping_block_on_the_same_day_is_accepted(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        MentorAvailability::create([
            'mentor_id' => $mentor->id, 'day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '12:00',
        ]);

        $this->actingAs($mentor);

        Livewire::test(Disponibilidade::class)
            ->set('dayOfWeek', '2')
            ->set('startTime', '14:00')
            ->set('endTime', '16:00')
            ->call('addBlock')
            ->assertHasNoErrors();

        $this->assertSame(2, MentorAvailability::where('mentor_id', $mentor->id)->count());
    }

    public function test_overlapping_block_on_a_different_day_is_accepted(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        MentorAvailability::create([
            'mentor_id' => $mentor->id, 'day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '12:00',
        ]);

        $this->actingAs($mentor);

        Livewire::test(Disponibilidade::class)
            ->set('dayOfWeek', '3')
            ->set('startTime', '09:00')
            ->set('endTime', '12:00')
            ->call('addBlock')
            ->assertHasNoErrors();

        $this->assertSame(2, MentorAvailability::where('mentor_id', $mentor->id)->count());
    }

    public function test_mentor_can_remove_their_own_block(): void
    {
        $mentor = User::factory()->create(['tier' => 'mentor']);
        $block = MentorAvailability::create([
            'mentor_id' => $mentor->id, 'day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '12:00',
        ]);

        $this->actingAs($mentor);

        Livewire::test(Disponibilidade::class)->call('removeBlock', $block->id);

        $this->assertDatabaseMissing('mentor_availabilities', ['id' => $block->id]);
    }

    public function test_mentor_cannot_remove_another_mentors_block(): void
    {
        $owner = User::factory()->create(['tier' => 'mentor']);
        $otherMentor = User::factory()->create(['tier' => 'mentor']);
        $block = MentorAvailability::create([
            'mentor_id' => $owner->id, 'day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '12:00',
        ]);

        $this->actingAs($otherMentor);

        Livewire::test(Disponibilidade::class)->call('removeBlock', $block->id);

        $this->assertDatabaseHas('mentor_availabilities', ['id' => $block->id]);
    }
}
