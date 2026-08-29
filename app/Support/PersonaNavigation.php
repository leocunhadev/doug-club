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
                ['label' => 'Aulas', 'route' => 'membros.aulas', 'available' => false],
                ['label' => 'Frameworks', 'route' => 'membros.frameworks', 'available' => false],
                ['label' => 'Sessão 1:1', 'route' => 'membros.upgrade', 'available' => false],
            ],
            'club' => [
                ['label' => 'Início', 'route' => 'dashboard', 'available' => true],
                ['label' => 'Aulas', 'route' => 'membros.aulas', 'available' => false],
                ['label' => 'Meu cofre', 'route' => 'membros.cofre', 'available' => false],
                ['label' => 'Minha sessão', 'route' => 'membros.agenda', 'available' => false],
                ['label' => 'Pessoas', 'route' => 'membros.pessoas', 'available' => false],
                ['label' => 'Encontros', 'route' => 'membros.encontros', 'available' => false],
                ['label' => 'Frameworks', 'route' => 'membros.frameworks', 'available' => false],
            ],
            'mentor' => [
                ['label' => 'Radar', 'route' => 'mentor.radar', 'available' => false],
                ['label' => 'Dossiês', 'route' => 'mentor.dossies', 'available' => false],
                ['label' => 'Publicar', 'route' => 'mentor.conteudo', 'available' => false],
                ['label' => 'Disponibilidade', 'route' => 'mentor.disp', 'available' => false],
            ],
            default => [],
        };
    }
}
