<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MentorSeeder extends Seeder
{
    /**
     * Roda só manualmente em produção: `php artisan db:seed --class=MentorSeeder`.
     * A senha é pedida interativamente para nunca passar por .env, git ou histórico do shell.
     */
    public function run(): void
    {
        $password = $this->command->secret('Senha do mentor');

        if (! $password) {
            $this->command->error('Senha não informada — abortando.');

            return;
        }

        User::updateOrCreate(
            ['email' => 'contato@leocunhadev.com.br'],
            [
                'name' => 'Léo Cunha',
                'password' => Hash::make($password),
                'tier' => 'mentor',
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('Usuário mentor criado/atualizado com sucesso.');
    }
}
