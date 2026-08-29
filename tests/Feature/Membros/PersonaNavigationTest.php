<?php

namespace Tests\Feature\Membros;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonaNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_tier_shows_inicio_aulas_and_frameworks_as_links_and_the_rest_locked(): void
    {
        $user = User::factory()->create(['tier' => 'start']);
        $this->actingAs($user);

        $html = $this->get('/membros')->assertOk()->getContent();

        foreach ([
            ['href' => 'http://localhost/membros', 'label' => 'Início'],
            ['href' => 'http://localhost/membros/aulas', 'label' => 'Aulas'],
            ['href' => 'http://localhost/membros/frameworks', 'label' => 'Frameworks'],
        ] as $link) {
            $this->assertMatchesRegularExpression(
                '#<a[^>]*href="'.preg_quote($link['href'], '#').'"[^>]*>\s*'.preg_quote($link['label'], '#').'\s*</a>#s',
                $html,
            );
        }

        $this->assertMatchesRegularExpression(
            '#<span[^>]*title="Em breve"[^>]*>\s*Sessão 1:1#s',
            $html,
        );
    }

    public function test_club_tier_shows_inicio_aulas_encontros_and_frameworks_as_links_and_three_tabs_locked(): void
    {
        $user = User::factory()->create(['tier' => 'club']);
        $this->actingAs($user);

        $html = $this->get('/membros')->assertOk()->getContent();

        foreach ([
            ['href' => 'http://localhost/membros', 'label' => 'Início'],
            ['href' => 'http://localhost/membros/aulas', 'label' => 'Aulas'],
            ['href' => 'http://localhost/membros/encontros', 'label' => 'Encontros'],
            ['href' => 'http://localhost/membros/frameworks', 'label' => 'Frameworks'],
        ] as $link) {
            $this->assertMatchesRegularExpression(
                '#<a[^>]*href="'.preg_quote($link['href'], '#').'"[^>]*>\s*'.preg_quote($link['label'], '#').'\s*</a>#s',
                $html,
            );
        }

        foreach (['Meu cofre', 'Minha sessão', 'Pessoas'] as $label) {
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
