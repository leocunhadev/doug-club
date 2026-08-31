<?php

namespace Tests\Feature\Membros;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonaNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_tier_shows_inicio_aulas_and_frameworks_as_unlocked_links(): void
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
    }

    public function test_start_tier_shows_club_only_tabs_as_locked_but_clickable_links(): void
    {
        $user = User::factory()->create(['tier' => 'start']);
        $this->actingAs($user);

        $html = $this->get('/membros')->assertOk()->getContent();

        foreach ([
            ['href' => 'http://localhost/membros/cofre', 'label' => 'Meu cofre'],
            ['href' => 'http://localhost/membros/agenda', 'label' => 'Minha sessão'],
            ['href' => 'http://localhost/membros/pessoas', 'label' => 'Pessoas'],
            ['href' => 'http://localhost/membros/encontros', 'label' => 'Encontros'],
        ] as $link) {
            $this->assertMatchesRegularExpression(
                '#<a[^>]*href="'.preg_quote($link['href'], '#').'"[^>]*>\s*'.preg_quote($link['label'], '#').'\s*🔒\s*</a>#su',
                $html,
            );
        }
    }

    public function test_club_tier_shows_inicio_aulas_cofre_agenda_pessoas_encontros_and_frameworks_as_links(): void
    {
        $user = User::factory()->create(['tier' => 'club']);
        $this->actingAs($user);

        $html = $this->get('/membros')->assertOk()->getContent();

        foreach ([
            ['href' => 'http://localhost/membros', 'label' => 'Início'],
            ['href' => 'http://localhost/membros/aulas', 'label' => 'Aulas'],
            ['href' => 'http://localhost/membros/cofre', 'label' => 'Meu cofre'],
            ['href' => 'http://localhost/membros/agenda', 'label' => 'Minha sessão'],
            ['href' => 'http://localhost/membros/pessoas', 'label' => 'Pessoas'],
            ['href' => 'http://localhost/membros/encontros', 'label' => 'Encontros'],
            ['href' => 'http://localhost/membros/frameworks', 'label' => 'Frameworks'],
        ] as $link) {
            $this->assertMatchesRegularExpression(
                '#<a[^>]*href="'.preg_quote($link['href'], '#').'"[^>]*>\s*'.preg_quote($link['label'], '#').'\s*</a>#s',
                $html,
            );
        }
    }

    public function test_mentor_tier_shows_disponibilidade_publicar_dossies_and_radar_as_links(): void
    {
        $user = User::factory()->create(['tier' => 'mentor']);
        $this->actingAs($user);

        $html = $this->get('/membros/mentor/radar')->assertOk()->getContent();

        foreach ([
            ['href' => 'http://localhost/membros/mentor/disponibilidade', 'label' => 'Disponibilidade'],
            ['href' => 'http://localhost/membros/mentor/conteudo', 'label' => 'Publicar'],
            ['href' => 'http://localhost/membros/mentor/dossies', 'label' => 'Dossiês'],
            ['href' => 'http://localhost/membros/mentor/radar', 'label' => 'Radar'],
        ] as $link) {
            $this->assertMatchesRegularExpression(
                '#<a[^>]*href="'.preg_quote($link['href'], '#').'"[^>]*>\s*'.preg_quote($link['label'], '#').'\s*</a>#s',
                $html,
            );
        }
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
