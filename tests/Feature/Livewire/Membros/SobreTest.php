<?php

namespace Tests\Feature\Livewire\Membros;

use App\Livewire\Membros\Sobre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SobreTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/membros/sobre')->assertRedirect('/login');
    }

    public function test_page_renders_the_douglas_oliveira_bio(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Sobre::class)
            ->assertSee('Douglas Oliveira')
            ->assertSee('500')
            ->assertSee('10.000')
            ->assertSee('A visão do dono do negócio');
    }

    public function test_header_renders_with_user_initials(): void
    {
        $user = User::factory()->create(['name' => 'Ana Souza']);
        $this->actingAs($user);

        Livewire::test(Sobre::class)->assertSee('AS');
    }

    public function test_footer_renders_below_the_bio(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Sobre::class)->assertSee('Tudo é gente. Até o software.');
    }
}
