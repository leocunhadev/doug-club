<?php

namespace Tests\Unit\Support;

use App\Support\PersonaNavigation;
use PHPUnit\Framework\TestCase;

class PersonaNavigationTest extends TestCase
{
    public function test_start_tier_has_three_available_tabs_and_one_locked_tab(): void
    {
        $tabs = (new PersonaNavigation)->tabs('start');

        $this->assertCount(4, $tabs);
        $this->assertSame(['Início', 'Aulas', 'Frameworks', 'Sessão 1:1'], array_column($tabs, 'label'));
        $this->assertSame([true, true, true, false], array_column($tabs, 'available'));
    }

    public function test_club_tier_has_five_available_tabs_and_two_locked_tabs(): void
    {
        $tabs = (new PersonaNavigation)->tabs('club');

        $this->assertCount(7, $tabs);
        $this->assertSame(
            ['Início', 'Aulas', 'Meu cofre', 'Minha sessão', 'Pessoas', 'Encontros', 'Frameworks'],
            array_column($tabs, 'label'),
        );
        $this->assertSame([true, true, false, true, false, true, true], array_column($tabs, 'available'));
    }

    public function test_mentor_tier_has_three_available_tabs_and_two_locked_tabs(): void
    {
        $tabs = (new PersonaNavigation)->tabs('mentor');

        $this->assertCount(5, $tabs);
        $this->assertSame(['Painel', 'Radar', 'Dossiês', 'Publicar', 'Disponibilidade'], array_column($tabs, 'label'));
        $this->assertSame([true, false, false, true, true], array_column($tabs, 'available'));
    }

    public function test_unknown_tier_returns_no_tabs(): void
    {
        $this->assertSame([], (new PersonaNavigation)->tabs('unknown'));
    }
}
