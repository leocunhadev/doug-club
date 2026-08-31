<?php

namespace App\Support;

class PersonaNavigation
{
    /**
     * @return array<int, array{label: string, route: string, available: bool}>
     */
    public function tabs(string $tier): array
    {
        return match ($tier) {
            'start' => [
                ['label' => 'Início', 'route' => 'dashboard', 'available' => true],
                ['label' => 'Aulas', 'route' => 'membros.aulas', 'available' => true],
                ['label' => 'Meu cofre', 'route' => 'membros.cofre', 'available' => false],
                ['label' => 'Minha sessão (1:1)', 'route' => 'membros.agenda', 'available' => false],
                ['label' => 'Pessoas', 'route' => 'membros.pessoas', 'available' => false],
                ['label' => 'Encontros', 'route' => 'membros.encontros', 'available' => false],
                ['label' => 'Frameworks', 'route' => 'membros.frameworks', 'available' => true],
            ],
            'club' => [
                ['label' => 'Início', 'route' => 'dashboard', 'available' => true],
                ['label' => 'Aulas', 'route' => 'membros.aulas', 'available' => true],
                ['label' => 'Meu cofre', 'route' => 'membros.cofre', 'available' => true],
                ['label' => 'Minha sessão (1:1)', 'route' => 'membros.agenda', 'available' => true],
                ['label' => 'Pessoas', 'route' => 'membros.pessoas', 'available' => true],
                ['label' => 'Encontros', 'route' => 'membros.encontros', 'available' => true],
                ['label' => 'Frameworks', 'route' => 'membros.frameworks', 'available' => true],
            ],
            'mentor' => [
                ['label' => 'Radar', 'route' => 'mentor.radar', 'available' => true],
                ['label' => 'Dossiês', 'route' => 'mentor.dossies', 'available' => true],
                ['label' => 'Publicar', 'route' => 'mentor.conteudo', 'available' => true],
                ['label' => 'Disponibilidade', 'route' => 'mentor.disp', 'available' => true],
            ],
            default => [],
        };
    }
}
