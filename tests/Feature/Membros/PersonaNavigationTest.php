<?php

namespace Tests\Feature\Membros;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonaNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_tier_shows_inicio_as_a_link_and_the_rest_locked(): void
    {
        $user = User::factory()->create(['tier' => 'start']);
        $this->actingAs($user);

        $html = $this->get('/membros')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<a[^>]*href="http://localhost/membros"[^>]*>\s*Início\s*</a>#s',
            $html,
        );

        foreach (['Aulas', 'Frameworks', 'Sessão 1:1'] as $label) {
            $this->assertMatchesRegularExpression(
                '#<span[^>]*title="Em breve"[^>]*>\s*'.preg_quote($label, '#').'#s',
                $html,
            );
        }
    }

    public function test_club_tier_shows_inicio_as_a_link_and_six_tabs_locked(): void
    {
        $user = User::factory()->create(['tier' => 'club']);
        $this->actingAs($user);

        $html = $this->get('/membros')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<a[^>]*href="http://localhost/membros"[^>]*>\s*Início\s*</a>#s',
            $html,
        );

        foreach (['Aulas', 'Meu cofre', 'Minha sessão', 'Pessoas', 'Encontros', 'Frameworks'] as $label) {
            $this->assertMatchesRegularExpression(
                '#<span[^>]*title="Em breve"[^>]*>\s*'.preg_quote($label, '#').'#s',
                $html,
            );
        }
    }

    public function test_mentor_tier_shows_all_four_tabs_locked(): void
    {
        $user = User::factory()->create(['tier' => 'mentor']);
        $this->actingAs($user);

        $html = $this->get('/membros/mentor')->assertOk()->getContent();

        foreach (['Radar', 'Dossiês', 'Publicar', 'Disponibilidade'] as $label) {
            $this->assertMatchesRegularExpression(
                '#<span[^>]*title="Em breve"[^>]*>\s*'.preg_quote($label, '#').'#s',
                $html,
            );
        }

        $this->assertStringNotContainsString('<a href="http://localhost/mentor', $html);
    }

    public function test_header_logout_button_posts_to_the_logout_route(): void
    {
        $this->actingAs(User::factory()->create());

        $html = $this->get('/membros')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<form[^>]*action="http://localhost/logout"[^>]*method="POST"#s',
            $html,
        );
    }
}
