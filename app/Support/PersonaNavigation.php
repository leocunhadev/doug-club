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
                ['label' => 'Frameworks', 'route' => 'membros.frameworks', 'available' => true],
                ['label' => 'Sessão 1:1', 'route' => 'membros.upgrade', 'available' => false],
            ],
            'club' => [
                ['label' => 'Início', 'route' => 'dashboard', 'available' => true],
                ['label' => 'Aulas', 'route' => 'membros.aulas', 'available' => true],
                ['label' => 'Meu cofre', 'route' => 'membros.cofre', 'available' => true],
                ['label' => 'Minha sessão', 'route' => 'membros.agenda', 'available' => true],
                ['label' => 'Pessoas', 'route' => 'membros.pessoas', 'available' => false],
                ['label' => 'Encontros', 'route' => 'membros.encontros', 'available' => true],
                ['label' => 'Frameworks', 'route' => 'membros.frameworks', 'available' => true],
            ],
            'mentor' => [
                ['label' => 'Painel', 'route' => 'mentor.placeholder', 'available' => true],
                ['label' => 'Radar', 'route' => 'mentor.radar', 'available' => false],
                ['label' => 'Dossiês', 'route' => 'mentor.dossies', 'available' => false],
                ['label' => 'Publicar', 'route' => 'mentor.conteudo', 'available' => true],
                ['label' => 'Disponibilidade', 'route' => 'mentor.disp', 'available' => true],
            ],
            default => [],
        };
    }
}
