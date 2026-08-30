# Notificações de sessão 1:1 (issue #27) — Design

## Objetivo

Fechar a issue #27: avisar mentor e membro por e-mail quando uma sessão 1:1 é marcada, avisar o mentor quando o membro cancela, e lembrar o membro cerca de 1h antes do horário da sessão.

## Estado atual (contexto)

- `App\Actions\BookMentorSession::handle(User $mentor, User $member, Carbon $scheduledAt): ?MentorSession` cria a sessão (ou retorna `null` se o horário já foi preenchido). Chamada de `App\Livewire\Membros\Agenda::bookSlot()`.
- `App\Actions\CancelMentorSession::handle(MentorSession $session): void` marca `cancelled_at`. Chamada de `App\Livewire\Membros\Agenda::cancelSession()`, que só permite cancelar com 24h+ de antecedência e só o próprio membro dono da sessão.
- `App\Models\MentorSession` tem `mentor_id`, `member_id`, `scheduled_at`, `cancelled_at` (cast `datetime`), e os helpers `isCancelled()`/`isUpcoming()`.
- O projeto assume mentor único (`User::where('tier', 'mentor')->first()`), sem tabela de vínculo mentor↔mentorado — mesmo padrão usado em Cofre/Pessoas/Dossiês/Radar.
- Já existe uma notificação real por e-mail no projeto: `App\Notifications\ClubApplicationApproved` (síncrona, `via(): ['mail']`), disparada de dentro de um `Action::make()` do Filament (`ClubApplicationsTable.php`). `MAIL_MAILER=smtp` já está configurado em produção (`.env`); `phpunit.xml` fixa `MAIL_MAILER=array` e `QUEUE_CONNECTION=sync` como rede de segurança para testes.
- `QUEUE_CONNECTION=database` já está configurado em `.env`, e a tabela `jobs` já existe (migration `0001_01_01_000002_create_jobs_table.php`), mas nenhum worker de fila (`queue:work`) roda em produção hoje — precisa ser configurado no VPS como parte deste trabalho (runbook abaixo).
- Não existe cancelamento pelo lado do mentor passando por `CancelMentorSession` — o Filament (`MentorSessionsTable.php`) só tem um `DeleteAction` (exclusão direta, sem notificação). Fora do escopo desta issue.
- Não existe reagendamento de sessão hoje — só marcar e cancelar.

## Escopo

**Dentro do escopo:**
1. E-mail de confirmação para o membro quando a sessão é marcada.
2. E-mail de confirmação para o mentor quando a sessão é marcada.
3. E-mail para o mentor quando o membro cancela.
4. E-mail de lembrete para o membro, ~1h antes do horário da sessão.
5. Configuração de um worker de fila (`queue:work`) em produção, via Supervisor, com o runbook documentado para execução manual no VPS.

**Fora do escopo:**
- Notificar o membro quando ele mesmo cancela (ele já vê a confirmação na tela — decisão do usuário).
- Notificar o mentor com lembrete próprio (decisão do usuário).
- Cancelamento pelo lado do mentor via Filament (usa `DeleteAction` direto, sem passar por `CancelMentorSession` — fica de fora).
- Reagendamento de sessão (funcionalidade não existe hoje).
- SMS ou push notification — só e-mail.

## Arquitetura

### Notificações (4 classes novas)

Seguindo o padrão de `ClubApplicationApproved` (uma classe por tipo de notificação, `via(): ['mail']`), mas todas com `implements ShouldQueue` (diferente de `ClubApplicationApproved`, que é síncrona) — já que este recurso já exige um worker de fila rodando para o lembrete, faz sentido usá-lo também para não bloquear a requisição web do Livewire com uma chamada SMTP síncrona.

- **`App\Notifications\MentorSessionBookedNotification`** (para o membro)
  - Assunto: "Sua sessão foi confirmada"
  - Corpo: saudação com o nome do membro, data/hora formatada (`scheduled_at->format('d/m/Y \à\s H:i')`), nome do mentor, link pra `route('membros.agenda')` como CTA.
- **`App\Notifications\MentorSessionBookedForMentorNotification`** (para o mentor)
  - Assunto: "Nova sessão marcada"
  - Corpo: nome do membro que marcou, data/hora. Sem CTA (o mentor já acessa o painel Filament pelo fluxo normal de admin, não precisa de um link direto aqui).
- **`App\Notifications\MentorSessionCancelledForMentorNotification`** (para o mentor)
  - Assunto: "Sessão cancelada"
  - Corpo: nome do membro que cancelou, data/hora que era, aviso de que o horário abriu de novo.
- **`App\Notifications\MentorSessionReminderNotification`** (para o membro)
  - Assunto: "Sua sessão é daqui a pouco"
  - Corpo: horário da sessão, nome do mentor, link pra `route('membros.agenda')` como CTA.

Cada classe recebe o `MentorSession` no construtor (ex: `new MentorSessionBookedNotification($session)`) e usa suas relações (`$session->mentor`, `$session->member`) pra montar o conteúdo — sem precisar buscar nada externo.

### Pontos de disparo

Seguindo o padrão de `ActivateUserFromPayment::handle()`, que já dispara efeitos colaterais (`Password::sendResetLink`) direto dentro da action — as notificações entram direto em `BookMentorSession::handle()` e `CancelMentorSession::handle()`, não no Livewire component. Isso garante que qualquer chamador futuro dessas actions (não só o `Agenda` component) dispare as mesmas notificações.

`BookMentorSession::handle()` (dentro da transação, depois do `MentorSession::create()`):
```php
$session->member->notify(new MentorSessionBookedNotification($session));
$session->mentor->notify(new MentorSessionBookedForMentorNotification($session));

SendSessionReminderJob::dispatch($session)->delay($scheduledAt->copy()->subHour());

return $session;
```

`CancelMentorSession::handle()`:
```php
public function handle(MentorSession $session): void
{
    $session->update(['cancelled_at' => now()]);

    $session->mentor->notify(new MentorSessionCancelledForMentorNotification($session));
}
```

### Mecanismo do lembrete

`App\Jobs\SendSessionReminderJob` — um `ShouldQueue` job simples que recebe o `MentorSession` no construtor:

```php
class SendSessionReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public MentorSession $session) {}

    public function handle(): void
    {
        $this->session->refresh();

        if ($this->session->isCancelled() || $this->session->scheduled_at->isPast()) {
            return;
        }

        $this->session->member->notify(new MentorSessionReminderNotification($this->session));
    }
}
```

`$this->session->refresh()` garante que o job veja o estado mais recente da sessão no banco (não o snapshot serializado no momento do `dispatch()`), já que `SerializesModels` recarrega o model pelo ID ao processar — mas o `refresh()` explícito documenta a intenção e protege contra qualquer cache de relação obsoleta.

O `dispatch(...)->delay($scheduledAt->copy()->subHour())` é chamado uma única vez, no momento do agendamento (não há reagendamento no sistema hoje, então não há necessidade de cancelar/reagendar o job depois de despachado — o guard `isCancelled()`/`isPast()` dentro do job cobre o caso de a sessão ser cancelada depois).

**Casos de borda cobertos pelo guard:**
- Sessão cancelada entre o agendamento do job e sua execução → guard pula o envio.
- Sessão marcada com menos de 1h de antecedência (ex: às 14:30 para as 15:00) → `subHour()` calcula um instante no passado; o Laravel despacha jobs com `delay` no passado imediatamente (sem erro), então o lembrete sai na hora, o que é o comportamento correto (a sessão já está dentro da janela de "daqui a pouco").
- Worker de fila fica fora do ar por horas e só processa o job depois do horário da sessão já ter passado → guard `scheduled_at->isPast()` pula o envio (não faz sentido lembrar de algo que já aconteceu).

### Sem mudança de schema

Nenhuma coluna nova é necessária — o mecanismo é baseado em job adiado (não em polling periódico), então não há necessidade de uma coluna `reminder_sent_at` para evitar duplicidade.

## Infra: worker de fila em produção (VPS com SSH)

Este trabalho inclui a configuração real do worker no VPS de produção — Claude Code não tem acesso a esse servidor, então esta seção é um runbook para o usuário executar manualmente, documentado dentro do repositório (`docs/deploy/queue-worker.md`, criado como parte do plano) para referência futura.

Passos (resumo, o plano detalha o arquivo completo):
1. Instalar o Supervisor no VPS (`apt install supervisor` ou equivalente da distro).
2. Criar `/etc/supervisor/conf.d/doing-club-worker.conf` com um programa rodando `php artisan queue:work --sleep=3 --tries=3 --max-time=3600` a partir do diretório do projeto, com `autostart=true`, `autorestart=true`, `stopasgroup=true`, `killasgroup=true`, `user=<usuário do deploy>`, `numprocs=1`, redirecionando log pra um arquivo dedicado.
3. `supervisorctl reread && supervisorctl update && supervisorctl start doing-club-worker:*`.
4. Verificar com `supervisorctl status`.

Nenhum cron de `schedule:run` é necessário para este recurso — o `->delay()` do job já é resolvido pela própria fila (`available_at` na tabela `jobs`), sem precisar de um "tick" periódico externo.

## Testes

- `phpunit.xml` já fixa `MAIL_MAILER=array` e `QUEUE_CONNECTION=sync` — nenhuma mudança de configuração de teste é necessária.
- Testes de `BookMentorSession`/`CancelMentorSession` usam `Notification::fake()` e `Queue::fake()` (ou `Bus::fake()`) para verificar que a notificação certa foi enviada ao destinatário certo, e que `SendSessionReminderJob` foi despachado com o delay esperado (`Queue::assertPushed(SendSessionReminderJob::class, fn ($job) => ...)`, comparando o `scheduled_at` do job com o delay calculado) — sem esperar de verdade.
- Teste dedicado de `SendSessionReminderJob::handle()`: roda o job diretamente (sem fila) contra uma sessão cancelada (não deve notificar), uma sessão cujo `scheduled_at` já passou (não deve notificar), e uma sessão válida (deve notificar o membro).
- Testes das 4 classes de `Notification`: cada uma renderizando o `MailMessage` com os dados esperados (assunto, nome, data formatada) — mesmo padrão de teste que `ClubApplicationApproved` já tem hoje (verificar o arquivo de teste existente pra seguir o mesmo formato).
- `AgendaTest.php` (Livewire) ganha `Notification::fake()`/`Queue::fake()` nos testes existentes de `bookSlot`/`cancelSession` para confirmar que o fluxo ponta a ponta dispara as notificações certas, sem precisar reimplementar a lógica de conteúdo (isso já está coberto pelos testes unitários das notifications).

## Riscos e decisões

- **Enviar de forma assíncrona (queued) muda o comportamento de erro**: se o SMTP falhar, o erro não aparece mais na resposta HTTP do Livewire — aparece no log do worker, e o Laravel tenta de novo automaticamente (`--tries=3` no Supervisor). Isso é aceitável e é o comportamento padrão esperado de e-mail assíncrono; não adicionamos alerta adicional de falha nesta issue (fora de escopo).
- **Dependência de infra nova em produção** (worker de fila via Supervisor) é o maior risco deste trabalho — sem o worker rodando, nenhuma notificação queued é enviada (elas ficam acumuladas na tabela `jobs`, nunca processadas). O plano vai incluir um teste manual de fumaça pós-deploy (documentado no runbook) para confirmar que o worker está de fato processando jobs antes de considerar a issue fechada.
