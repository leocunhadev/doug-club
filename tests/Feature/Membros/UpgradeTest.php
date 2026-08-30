<?php

namespace Tests\Feature\Membros;

use App\Livewire\Membros\Upgrade;
use App\Models\ClubApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/membros/upgrade')->assertRedirect(route('login'));
    }

    public function test_club_tier_member_is_denied(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'club']));

        $this->get('/membros/upgrade')->assertRedirect(route('dashboard'));
    }

    public function test_mentor_tier_member_is_denied(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'mentor']));

        $this->get('/membros/upgrade')->assertRedirect(route('dashboard'));
    }

    public function test_start_tier_member_sees_the_page(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'start']));

        $this->get('/membros/upgrade')
            ->assertOk()
            ->assertSee('Aplicar para o CLUB');
    }

    public function test_apply_creates_a_club_application(): void
    {
        $user = User::factory()->create(['tier' => 'start']);
        $this->actingAs($user);

        Livewire::test(Upgrade::class)->call('apply');

        $this->assertDatabaseHas('club_applications', ['user_id' => $user->id]);
    }

    public function test_applying_twice_does_not_duplicate_the_record(): void
    {
        $user = User::factory()->create(['tier' => 'start']);
        $this->actingAs($user);

        Livewire::test(Upgrade::class)
            ->call('apply')
            ->call('apply');

        $this->assertSame(1, ClubApplication::where('user_id', $user->id)->count());
    }

    public function test_button_shows_application_sent_after_applying(): void
    {
        $user = User::factory()->create(['tier' => 'start']);
        $this->actingAs($user);

        Livewire::test(Upgrade::class)
            ->call('apply')
            ->assertSee('Aplicação enviada');
    }
}
