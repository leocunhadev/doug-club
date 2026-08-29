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
        $this->get('/membros/mentor')->assertRedirect('/login');
    }

    public function test_start_tier_cannot_access_the_mentor_placeholder(): void
    {
        $user = User::factory()->create(['tier' => 'start']);
        $this->actingAs($user);

        $this->get('/membros/mentor')
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status');
    }

    public function test_club_tier_cannot_access_the_mentor_placeholder(): void
    {
        $user = User::factory()->create(['tier' => 'club']);
        $this->actingAs($user);

        $this->get('/membros/mentor')->assertRedirect(route('dashboard'));
    }

    public function test_mentor_tier_can_access_the_mentor_placeholder(): void
    {
        $user = User::factory()->create(['tier' => 'mentor']);
        $this->actingAs($user);

        $this->get('/membros/mentor')
            ->assertOk()
            ->assertSee('Seu painel de mentor está sendo construído');
    }
}
