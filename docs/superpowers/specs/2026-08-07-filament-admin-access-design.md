# Instalar Filament + gate de acesso admin — Issue #15

(Sub-issue 1/3 da divisão da issue #11 "Painel admin para cadastrar cursos/aulas/materiais".)

## Contexto

A issue #11 pede um painel admin para cadastrar cursos, aulas e materiais, sugerindo Filament
como candidato natural por já ser um stack Laravel (`docs/lms-spec.md` seção 9). É grande demais
para uma única entrega, então foi dividida em 3 sub-issues:

1. **#15 (esta)** — instalar Filament e implementar o controle de acesso ao painel.
2. #16 — `CourseResource` (CRUD de cursos/módulos).
3. #17 — `LessonResource` (CRUD de aulas) + materiais como relation manager.

Hoje o model `User` (`app/Models/User.php`) não tem nenhum campo de role/admin — só
`name`, `email`, `password`. O `DatabaseSeeder` cria um único usuário de teste
(`test@example.com`).

## Escopo

Instalar o Filament, criar um jeito de marcar um usuário como admin, e garantir que só esse
usuário acesse o painel em `/admin`. Nenhum resource (Course/Lesson/Material) é criado aqui —
isso fica para as sub-issues #16 e #17.

## Decisões

- **Controle de acesso:** coluna `is_admin` (boolean, default `false`) na tabela `users`, checada
  em `User::canAccessPanel()` (interface `Filament\Models\Contracts\FilamentUser`). Rejeitada a
  alternativa de allowlist de e-mails (menos flexível) e a de `spatie/laravel-permission` (roles
  completos são mais infraestrutura do que o caso atual — só admin vs. membro — exige).
- **Usuário admin de dev:** usuário novo e separado, `admin@example.com` / senha `123456789`
  (mesmo padrão de senha simples já usado pelo `test@example.com`, ambiente de dev/SQLite local),
  criado em `DatabaseSeeder`. Não reaproveita o `test@example.com`, para manter o cenário de dev
  parecido com produção (admin ≠ membro comum).
- **Painel:** path padrão do Filament (`/admin`), instalado via
  `php artisan filament:install --panels`, que gera `App\Providers\Filament\AdminPanelProvider`
  e o registra em `bootstrap/providers.php` automaticamente.

## Mudanças

### Dependências
- `composer require filament/filament` (versão mais recente compatível com Laravel ^13.8 /
  Livewire ^3.6.4 / PHP ^8.3, já presentes no projeto).
- `php artisan filament:install --panels` — gera `AdminPanelProvider`, publica assets.

### Banco de dados
- Nova migration: adiciona `is_admin` (boolean, `default(false)`) à tabela `users`.

### `app/Models/User.php`
- Implementa `Filament\Models\Contracts\FilamentUser`.
- Adiciona `canAccessPanel(Panel $panel): bool` retornando `$this->is_admin`.
- Adiciona `is_admin` a `casts()` como `boolean`.

### `database/seeders/DatabaseSeeder.php`
- Novo `User::factory()->create([...])` para `admin@example.com`, `is_admin => true`,
  senha `123456789` — antes ou depois do `test@example.com` existente, sem alterar o usuário
  atual.

## Testes

Feature test novo em `tests/Feature/Admin/PanelAccessTest.php`:
- Visitante não autenticado acessando `/admin` é redirecionado para `/admin/login` (ou recebe
  302/401, conforme comportamento padrão do Filament).
- Usuário autenticado com `is_admin = false` acessando `/admin` recebe `403`.
- Usuário autenticado com `is_admin = true` acessando `/admin` recebe `200`.

Suíte completa (`php artisan test`) deve continuar verde.
