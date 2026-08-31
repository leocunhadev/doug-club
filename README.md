# DO.ing Club

Plataforma de membros da mentoria de Douglas Oliveira. Laravel 13 + Livewire 3/Volt no front, Filament 4 no admin, SQLite em dev.

## Stack

- PHP 8.3, Laravel 13, Livewire 3.6 + Volt
- Filament 4 (painel administrativo)
- Tailwind CSS 3
- FPDI/FPDF (marca d'água em PDF)
- SQLite (dev)

## Planos e papéis

Cada usuário tem um `tier`: `start` (plano de entrada), `club` (plano pago, acesso completo) ou `mentor` (Douglas). Um flag `is_admin` separado dá acesso ao painel Filament. O acesso pode ser revogado (`access_revoked_at`) sem apagar a conta — usado no cancelamento/estorno de pagamento.

## Funcionalidades para membros (`start` / `club`)

- **Login e conta** — autenticação via Laravel Breeze (Livewire), reset de senha por e-mail, verificação de e-mail, confirmação de senha, tela de login com identidade visual própria.
- **Dashboard (`/`)** — página inicial personalizada por tier: continuar assistindo, nota do mentor ("onde paramos"), atalhos, CTA de próxima sessão 1:1 (club) ou de upgrade (start).
- **Biblioteca de aulas (`/aulas`)** — grid com busca e filtro por categoria (Encontros, Convidados, Frameworks), player em destaque com progresso salvo, barra "assistindo agora", página de materiais por aula com download.
- **Frameworks (`/frameworks`)** — catálogo de frameworks em PDF vinculados a uma aula, com bloqueio por tier e download rastreado (`FrameworkDownload`).
- **Cofre (`/cofre`, só `club`)** — documentos privados por membro (upload ou link externo), PDFs baixados recebem marca d'água com nome e e-mail do titular, arquivos servidos por um controller autenticado (sem exposição direta em disco público).
- **Agenda de sessões 1:1 (`/agenda`, só `club`)** — visualização dos horários disponíveis do mentor, seleção de dia/horário com etapa de confirmação antes de reservar.
- **Encontros ao vivo (`/encontros`, só `club`)** — calendário de eventos com link de acesso, gravação disponibilizada depois na biblioteca, avaliação por NPS.
- **Pessoas do CLUB (`/pessoas`, só `club`)** — diretório de membros com tags do que cada um ensina/quer aprender, pedido de "ponte" (introdução) entre mentorados (`BridgeRequest`).
- **Upgrade (`/upgrade`, só `start`)** — apresentação do plano CLUB e envio de candidatura (`ClubApplication`).
- **Sobre (`/sobre`)** — página institucional da mentoria.
- **Perfil (`/profile`)** — edição de dados da conta (Breeze).

## Funcionalidades para o mentor (`tier: mentor`)

- **Radar do dia (`/mentor/radar`)** — painel com briefings dos próximos encontros/sessões, sugestão de abertura de conversa, KPIs de engajamento e sugestões de "pontes" entre mentorados.
- **Dossiês (`/mentor/dossies`)** — histórico ("fio da mentoria") por mentorado, anotações privadas do mentor e envio de documentos direto para o Cofre do mentorado.
- **Disponibilidade (`/mentor/disponibilidade`)** — cadastro de blocos recorrentes de horário para sessões 1:1, com opção de desativar um bloco sem excluí-lo.
- **Publicar conteúdo (`/mentor/conteudo`)** — publicação de aulas e encontros sem precisar do painel admin.

## Painel administrativo (Filament, `is_admin`)

CRUD completo para:
- Cursos e aulas (`Courses`, `Lessons`), com materiais como relation manager
- Encontros ao vivo (`Encontros`)
- Frameworks em PDF (`Frameworks`)
- Sessões de mentoria (`MentorSessions`)
- Documentos do Cofre (`VaultDocuments`)
- Pedidos de ponte entre membros (`BridgeRequests`)
- Candidaturas ao CLUB (`ClubApplications`)

## Pagamentos

Ativação e revogação de acesso automatizadas via webhook do AbacatePay (`/webhooks/abacatepay`, validado por assinatura): checkout/assinatura concluída ativa o usuário no tier correto; reembolso, disputa ou cancelamento revoga o acesso.

## Progresso e engajamento

- Progresso de aula por segundo assistido, com marcação automática de "concluída" (`LessonProgress`)
- NPS unificado pós-aula e pós-encontro (`LessonFeedback`, `EncontroFeedback`)
- Notificações por e-mail (novo documento no Cofre, sessão 1:1 marcada)

## Desenvolvimento

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
php artisan migrate --seed

composer dev   # servidor + queue + logs + vite, tudo junto
```

Rodar a suíte de testes:

```bash
php artisan test
```
