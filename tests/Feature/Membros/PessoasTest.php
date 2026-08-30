<?php

namespace Tests\Feature\Membros;

use App\Livewire\Membros\Pessoas;
use App\Models\BridgeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PessoasTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/membros/pessoas')->assertRedirect(route('login'));
    }

    public function test_non_club_tier_member_is_denied(): void
    {
        $this->actingAs(User::factory()->create(['tier' => 'start']));

        $this->get('/membros/pessoas')->assertRedirect(route('dashboard'));
    }

    public function test_lists_other_club_members_but_not_the_logged_in_user(): void
    {
        $me = User::factory()->create(['tier' => 'club', 'name' => 'Eu Mesmo']);
        $other = User::factory()->create(['tier' => 'club', 'name' => 'Outro Membro']);
        User::factory()->create(['tier' => 'mentor', 'name' => 'O Mentor']);
        User::factory()->create(['tier' => 'start', 'name' => 'Membro Start']);

        $this->actingAs($me);

        Livewire::test(Pessoas::class)
            ->assertSee('Outro Membro')
            ->assertDontSee('Eu Mesmo')
            ->assertDontSee('O Mentor')
            ->assertDontSee('Membro Start');
    }

    public function test_member_with_empty_profile_shows_placeholders(): void
    {
        User::factory()->create(['tier' => 'club', 'name' => 'Perfil Vazio']);

        $this->actingAs(User::factory()->create(['tier' => 'club']));

        Livewire::test(Pessoas::class)
            ->assertSee('Ainda não contou nada sobre si.')
            ->assertSee('Ainda não preencheu.');
    }

    public function test_request_bridge_creates_a_bridge_request(): void
    {
        $me = User::factory()->create(['tier' => 'club']);
        $target = User::factory()->create(['tier' => 'club']);

        $this->actingAs($me);

        Livewire::test(Pessoas::class)->call('requestBridge', $target->id);

        $this->assertDatabaseHas('bridge_requests', [
            'requester_id' => $me->id,
            'target_id' => $target->id,
        ]);
    }

    public function test_requesting_a_bridge_twice_does_not_duplicate_the_record(): void
    {
        $me = User::factory()->create(['tier' => 'club']);
        $target = User::factory()->create(['tier' => 'club']);

        $this->actingAs($me);

        Livewire::test(Pessoas::class)
            ->call('requestBridge', $target->id)
            ->call('requestBridge', $target->id);

        $this->assertSame(
            1,
            BridgeRequest::where('requester_id', $me->id)->where('target_id', $target->id)->count(),
        );
    }

    public function test_button_shows_pedido_enviado_after_requesting(): void
    {
        $me = User::factory()->create(['tier' => 'club']);
        $target = User::factory()->create(['tier' => 'club']);

        $this->actingAs($me);

        Livewire::test(Pessoas::class)
            ->call('requestBridge', $target->id)
            ->assertSee('Pedido enviado');
    }

    public function test_request_bridge_with_own_id_does_nothing(): void
    {
        $me = User::factory()->create(['tier' => 'club']);

        $this->actingAs($me);

        Livewire::test(Pessoas::class)->call('requestBridge', $me->id);

        $this->assertDatabaseMissing('bridge_requests', ['requester_id' => $me->id]);
    }

    public function test_request_bridge_with_a_non_club_target_does_nothing(): void
    {
        $me = User::factory()->create(['tier' => 'club']);
        $startUser = User::factory()->create(['tier' => 'start']);

        $this->actingAs($me);

        Livewire::test(Pessoas::class)->call('requestBridge', $startUser->id);

        $this->assertDatabaseMissing('bridge_requests', ['requester_id' => $me->id]);
    }
}
