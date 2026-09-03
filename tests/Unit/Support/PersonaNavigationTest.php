<?php

namespace Tests\Unit\Support;

use App\Support\PersonaNavigation;
use PHPUnit\Framework\TestCase;

class PersonaNavigationTest extends TestCase
{
    public function test_start_tier_has_seven_tabs_with_club_only_ones_locked(): void
    {
        $tabs = (new PersonaNavigation)->tabs('start');

        $this->assertCount(7, $tabs);
        $this->assertSame(
            ['Início', 'Aulas', 'Meu cofre', 'Minha sessão (1:1)', 'Pessoas', 'Encontros', 'Frameworks'],
            array_column($tabs, 'label'),
        );
        $this->assertSame([true, true, false, false, false, false, true], array_column($tabs, 'available'));
    }

    public function test_club_tier_has_seven_available_tabs(): void
    {
        $tabs = (new PersonaNavigation)->tabs('club');

        $this->assertCount(7, $tabs);
        $this->assertSame(
            ['Início', 'Aulas', 'Meu cofre', 'Minha sessão (1:1)', 'Pessoas', 'Encontros', 'Frameworks'],
            array_column($tabs, 'label'),
        );
        $this->assertSame([true, true, true, true, true, true, true], array_column($tabs, 'available'));
    }

    public function test_mentor_tier_has_four_available_tabs(): void
    {
        $tabs = (new PersonaNavigation)->tabs('mentor');

        $this->assertCount(4, $tabs);
        $this->assertSame(['Radar', 'Dossiês', 'Publicar', 'Disponibilidade'], array_column($tabs, 'label'));
        $this->assertSame([true, true, true, true], array_column($tabs, 'available'));
    }

    public function test_unknown_tier_returns_no_tabs(): void
    {
        $this->assertSame([], (new PersonaNavigation)->tabs('unknown'));
    }
}
