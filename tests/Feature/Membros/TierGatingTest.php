<?php

namespace Tests\Feature\Membros;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TierGatingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/mentor/radar')->assertRedirect('/login');
    }

    public function test_start_tier_cannot_access_the_mentor_radar(): void
    {
        $user = User::factory()->create(['tier' => 'start']);
        $this->actingAs($user);

        $this->get('/mentor/radar')
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status');

        $this->get(route('dashboard'))
            ->assertSee('Esse conteúdo está disponível no mentor.', false);
    }

    public function test_club_tier_cannot_access_the_mentor_radar(): void
    {
        $user = User::factory()->create(['tier' => 'club']);
        $this->actingAs($user);

        $this->get('/mentor/radar')->assertRedirect(route('dashboard'));
    }

    public function test_mentor_tier_can_access_the_mentor_radar(): void
    {
        $user = User::factory()->create(['tier' => 'mentor']);
        $this->actingAs($user);

        $this->get('/mentor/radar')
            ->assertOk()
            ->assertSee('Radar do dia');
    }

    public function test_start_tier_hitting_a_club_only_route_lands_on_the_upgrade_pitch(): void
    {
        $user = User::factory()->create(['tier' => 'start']);
        $this->actingAs($user);

        $this->get('/pessoas')->assertRedirect(route('membros.upgrade'));

        $this->get(route('membros.upgrade'))
            ->assertOk()
            ->assertSee('Aplicar para o CLUB');
    }

    public function test_club_only_redirect_target_still_requires_the_start_tier_itself(): void
    {
        // A mentor never fails the club check (hasClubAccess() covers both
        // tiers), so this path is unreachable for them in practice — but
        // confirm the redirect target itself doesn't create a loop for any
        // tier that somehow reaches it.
        $user = User::factory()->create(['tier' => 'start']);
        $this->actingAs($user);

        $response = $this->get('/cofre');

        $response->assertRedirect(route('membros.upgrade'));
        $this->get($response->headers->get('Location'))->assertOk();
    }
}
