# Agenda de sessões 1:1 (issue #20) — disponibilidade recorrente + reserva

Fecha a issue #20. Duas telas: o mentor define blocos de disponibilidade recorrentes por dia da
semana (`/membros/mentor/disponibilidade`, rota `mentor.disp` — já reservada no
`PersonaNavigation`), e o membro CLUB reserva uma sessão de 90 min dentro dos horários livres
(`/membros/agenda`, rota `membros.agenda` — idem). Referência visual: `resources/views/prototype/disp.blade.php`
e `resources/views/prototype/agenda.blade.php`, cujos dados mock não se conectam entre si — o app
real precisa gerar os horários reserváveis a partir da disponibilidade recorrente de verdade, não
tem os dois como mocks independentes.

## 1. Contexto

Confirmado com o usuário:

- **Disponibilidade recorrente semanal**, não datas pontuais — o mentor liga um bloco tipo "Terças
  09h–12h" uma vez, vale toda semana. O sistema gera os horários reserváveis específicos a partir
  disso, pros próximos dias.
- **Janela de 14 dias, antecedência mínima de 24h** — tanto pra ver quanto pra marcar um horário.
- **Mentor único.** `mentor_id` fica explícito nas tabelas (não hardcoded), mas toda a lógica de
  "qual mentor" resolve pro primeiro/único `User` com `tier = 'mentor'` — sem seletor de mentor na
  UI. Multi-mentor fica pra quando (se) existir necessidade real.
- **Sem notificação/lembrete nesta versão** — descoped pra issue #27 (depende desta).
- **Cancelamento pelo membro**, respeitando a mesma janela de 24h de antecedência.
- **Visibilidade do mentor via Filament** (lista simples de sessões marcadas) — o "radar do dia"
  chique fica pra issue #21 (Painel do mentor).
- **Um membro só pode ter uma sessão futura ativa por vez** (decisão do desenvolvedor, não
  perguntada de novo ao usuário): enquanto o membro tiver uma sessão marcada e não cancelada no
  futuro, a página `/membros/agenda` mostra ela (com botão Cancelar) no lugar do calendário de
  reserva — evita acumular várias sessões e simplifica a UI e as regras de conflito.
- **`App\Livewire\Membros\Disponibilidade`, não um namespace `Mentor\` separado** — segue o padrão
  já estabelecido por `App\Livewire\Membros\MentorPlaceholder` (rota `membros/mentor`, nome de rota
  `mentor.placeholder`): páginas do mentor vivem no mesmo namespace `Membros`, só o nome da rota tem
  prefixo `mentor.`.

## 2. Modelo de dados

Migration `create_mentor_availabilities_table`:

```
mentor_availabilities
  id
  mentor_id    FK -> users, cascadeOnDelete
  day_of_week  unsignedTinyInteger  -- 0 (domingo) .. 6 (sábado), igual Carbon::dayOfWeek
  start_time   time
  end_time     time
  timestamps
```

`MentorAvailability` (model): `$fillable` = `mentor_id, day_of_week, start_time, end_time`.
`$casts`: `start_time => 'datetime:H:i'`, `end_time => 'datetime:H:i'`. `mentor(): BelongsTo`.

Sem campo de "aberto/fechado" — a linha existir já significa que o bloco está aberto; removê-la
fecha o bloco. CRUD simples em vez de toggle.

Migration `create_mentor_sessions_table`:

```
mentor_sessions
  id
  mentor_id     FK -> users, cascadeOnDelete
  member_id     FK -> users, cascadeOnDelete
  scheduled_at  datetime
  cancelled_at  datetime nullable
  timestamps
```

`MentorSession` (model): `$fillable` = `mentor_id, member_id, scheduled_at, cancelled_at`.
`$casts`: `scheduled_at => 'datetime'`, `cancelled_at => 'datetime'`. `mentor(): BelongsTo` (via
`User::class`), `member(): BelongsTo` (via `User::class`). `isCancelled(): bool` —
`filled($this->cancelled_at)`. `isUpcoming(): bool` — `! $this->isCancelled() && $this->scheduled_at->isFuture()`.

Cancelar marca `cancelled_at`, nunca apaga a linha — mantém histórico pro Filament e pra um futuro
"Painel do mentor" (#21) poder mostrar sessões passadas.

Sem constraint de unicidade no banco pra `(mentor_id, scheduled_at)` — uma sessão cancelada precisa
liberar aquele horário pra outro membro reservar, e uma unique constraint simples colidiria com a
linha cancelada antiga. A proteção contra reserva duplicada é em nível de aplicação (seção 4).

## 3. Geração de horários — `App\Actions\DetermineAvailableSlots`

```php
class DetermineAvailableSlots
{
    private const SESSION_MINUTES = 90;
    private const BOOKING_WINDOW_DAYS = 14;
    private const MIN_NOTICE_HOURS = 24;

    /** @return \Illuminate\Support\Collection<int, \Carbon\Carbon> */
    public function handle(User $mentor): Collection
    {
        $availabilities = MentorAvailability::query()->where('mentor_id', $mentor->id)->get();

        $bookedSlots = MentorSession::query()
            ->where('mentor_id', $mentor->id)
            ->whereNull('cancelled_at')
            ->where('scheduled_at', '>=', now())
            ->pluck('scheduled_at')
            ->map(fn ($dt) => $dt->format('Y-m-d H:i:s'))
            ->all();

        $earliestBookable = now()->addHours(self::MIN_NOTICE_HOURS);
        $slots = collect();

        for ($day = 0; $day < self::BOOKING_WINDOW_DAYS; $day++) {
            $date = today()->addDays($day);

            foreach ($availabilities->where('day_of_week', $date->dayOfWeek) as $availability) {
                $slotStart = $date->copy()->setTimeFromTimeString($availability->start_time->format('H:i'));
                $blockEnd = $date->copy()->setTimeFromTimeString($availability->end_time->format('H:i'));

                while ($slotStart->copy()->addMinutes(self::SESSION_MINUTES)->lte($blockEnd)) {
                    if ($slotStart->gte($earliestBookable)
                        && ! in_array($slotStart->format('Y-m-d H:i:s'), $bookedSlots, true)) {
                        $slots->push($slotStart->copy());
                    }

                    $slotStart->addMinutes(self::SESSION_MINUTES);
                }
            }
        }

        return $slots->sortBy(fn ($slot) => $slot->timestamp)->values();
    }
}
```

Um bloco "09h–12h" vira dois horários de 90 min (09:00 e 10:30) — a última fatia só entra se couber
inteira antes do fim do bloco (`lte($blockEnd)`), nunca corta uma sessão na metade.

## 4. Reserva — `App\Actions\BookMentorSession`

```php
class BookMentorSession
{
    public function handle(User $mentor, User $member, Carbon $scheduledAt): ?MentorSession
    {
        return DB::transaction(function () use ($mentor, $member, $scheduledAt) {
            $alreadyBooked = MentorSession::query()
                ->where('mentor_id', $mentor->id)
                ->where('scheduled_at', $scheduledAt)
                ->whereNull('cancelled_at')
                ->exists();

            if ($alreadyBooked) {
                return null;
            }

            return MentorSession::create([
                'mentor_id' => $mentor->id,
                'member_id' => $member->id,
                'scheduled_at' => $scheduledAt,
            ]);
        });
    }
}
```

Retorna `null` em vez de lançar exceção quando o horário já foi preenchido — o chamador
(`Agenda::confirmarReserva()`) trata isso mostrando uma mensagem e recarregando a lista de horários
disponíveis, sem quebrar a página. A transação reduz — mas não elimina totalmente — o risco de duas
pessoas reservarem o mesmo horário ao mesmo tempo; aceitável dado o volume baixo esperado (mentor
único, poucas reservas simultâneas).

## 5. Cancelamento — `App\Actions\CancelMentorSession`

```php
class CancelMentorSession
{
    public function handle(MentorSession $session): void
    {
        $session->update(['cancelled_at' => now()]);
    }
}
```

A regra de "só cancela com 24h de antecedência e só o dono cancela" vive em
`Agenda::cancelarSessao()`, não na action — mesmo padrão já usado em `Encontros::submitEncontroNpsScore()`
(a action faz a mudança de estado, o componente Livewire checa a autorização/regra de negócio antes
de chamar).

## 6. Rotas

```php
Route::get('membros/agenda', Agenda::class)
    ->middleware(['auth', 'verified', 'active', 'tier:club'])
    ->name('membros.agenda');

Route::get('membros/mentor/disponibilidade', Disponibilidade::class)
    ->middleware(['auth', 'verified', 'active', 'tier:mentor'])
    ->name('mentor.disp');
```

## 7. Página do membro — `App\Livewire\Membros\Agenda`

```php
class Agenda extends Component
{
    use ComputesUserInitials;

    #[Computed]
    public function mentor(): ?User
    {
        return User::query()->where('tier', 'mentor')->first();
    }

    #[Computed]
    public function upcomingSession(): ?MentorSession
    {
        return MentorSession::query()
            ->where('member_id', Auth::id())
            ->whereNull('cancelled_at')
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at')
            ->first();
    }

    #[Computed]
    public function availableSlots(): Collection
    {
        if (! $this->mentor || $this->upcomingSession) {
            return collect();
        }

        return (new DetermineAvailableSlots)->handle($this->mentor);
    }

    public ?string $selectedDate = null; // 'Y-m-d'

    public function selectDate(string $date): void
    {
        $this->selectedDate = $date;
    }

    public function bookSlot(string $slot, BookMentorSession $action): void
    {
        if (! $this->mentor || $this->upcomingSession) {
            return;
        }

        $scheduledAt = Carbon::parse($slot);

        if (! $this->availableSlots->contains(fn ($s) => $s->equalTo($scheduledAt))) {
            return;
        }

        $session = $action->handle($this->mentor, Auth::user(), $scheduledAt);

        if (! $session) {
            session()->flash('agenda-error', 'Esse horário acabou de ser preenchido. Escolha outro.');
        }

        unset($this->availableSlots, $this->upcomingSession);
    }

    public function cancelSession(CancelMentorSession $action): void
    {
        $session = $this->upcomingSession;

        if (! $session || $session->member_id !== Auth::id()) {
            return;
        }

        if ($session->scheduled_at->lt(now()->addHours(24))) {
            return;
        }

        $action->handle($session);

        unset($this->upcomingSession, $this->availableSlots);
    }

    public function render()
    {
        return view('livewire.membros.agenda');
    }
}
```

`bookSlot()` recebe a data/hora como string ISO (`'Y-m-d\TH:i:s'`) vinda do `wire:click` do botão do
horário — validada contra `$this->availableSlots` antes de chamar a action, pra não confiar cegamente
num valor arbitrário vindo do cliente via wire protocol.

### View

1. `x-membros.header`
2. Se `$this->upcomingSession`: card mostrando data/hora formatada, "Sessão 1:1 · 90 minutos", e um
   botão "Cancelar sessão" — desabilitado (com texto explicando por quê) se
   `scheduled_at < now()->addHours(24)`.
3. Senão, se `$this->mentor` existir: fluxo de 2 passos igual ao protótipo —
   - Linha de dias (próximos 14 dias): cada dia com pelo menos um horário em `$this->availableSlots`
     é clicável (`wire:click="selectDate('Y-m-d')"`); dias sem nenhum horário aparecem desabilitados,
     igual ao `.day.off` do protótipo.
   - Lista de horários do `$selectedDate` escolhido (filtra `$this->availableSlots` por
     `->format('Y-m-d') === $selectedDate`), cada um um botão `wire:click="bookSlot('...')"`.
   - Mensagem de erro (`session('agenda-error')`) se o último clique perdeu a corrida pro horário.
4. Senão (nenhum `User` com `tier=mentor` cadastrado ainda): mensagem "Nenhum mentor disponível no
   momento." — estado vazio defensivo, não deveria acontecer em produção mas evita erro se o cadastro
   do mentor for removido/alterado.

## 8. Página do mentor — `App\Livewire\Membros\Disponibilidade`

```php
class Disponibilidade extends Component
{
    use ComputesUserInitials;

    public string $dayOfWeek = '1';
    public string $startTime = '';
    public string $endTime = '';

    #[Computed]
    public function blocks(): Collection
    {
        return MentorAvailability::query()
            ->where('mentor_id', Auth::id())
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
    }

    public function addBlock(): void
    {
        $this->validate([
            'dayOfWeek' => ['required', 'integer', 'between:0,6'],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime' => ['required', 'date_format:H:i', 'after:startTime'],
        ]);

        MentorAvailability::create([
            'mentor_id' => Auth::id(),
            'day_of_week' => $this->dayOfWeek,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
        ]);

        $this->reset('startTime', 'endTime');
        unset($this->blocks);
    }

    public function removeBlock(int $blockId): void
    {
        MentorAvailability::query()
            ->where('id', $blockId)
            ->where('mentor_id', Auth::id())
            ->delete();

        unset($this->blocks);
    }

    public function render()
    {
        return view('livewire.membros.disponibilidade');
    }
}
```

`removeBlock()` escopa por `mentor_id = Auth::id()` na própria query, não só no `wire:click` —
garante que um mentor nunca apaga bloco de outro mentor mesmo que o multi-mentor apareça no futuro
sem essa checagem ser revisada.

### View

Formulário simples (select de dia da semana Domingo–Sábado, dois campos de hora, botão "Adicionar")
+ lista dos blocos existentes agrupados por dia, cada um com um botão "Remover".

## 9. Admin (Filament) — `MentorSessionResource`

`App\Filament\Resources\MentorSessions\MentorSessionResource` — resource somente pra visualização
(sem form de criar/editar; sessões nascem só pelo fluxo de reserva do membro):

- **Table**: colunas `member.name`, `scheduled_at` (dateTime, sortable), status calculado
  ("Cancelada" se `cancelled_at` setado, senão "Confirmada"), `cancelled_at` (placeholder '—').
  `defaultSort('scheduled_at', 'desc')`. Sem `CreateAction` no header. `DeleteAction` disponível
  (admin pode remover um registro incorreto), sem `EditAction` (não há nada de negócio a editar numa
  sessão já marcada — cancelar é uma ação de domínio, não uma edição de campo).
- Como só existe um mentor, a listagem não precisa de filtro por mentor.

## 10. Navegação

`PersonaNavigation::tabs()`: `Minha sessão` (`route: 'membros.agenda'`, na lista `club`) e
`Disponibilidade` (`route: 'mentor.disp'`, na lista `mentor`) viram `available: true`.

## 11. Testes

- `Tests\Unit\DetermineAvailableSlotsTest`: gera os horários certos a partir de um bloco (dois
  slots de 90 min num bloco de 3h); respeita a antecedência mínima de 24h; exclui horário já
  reservado; exclui horário fora da janela de 14 dias; não gera slot parcial quando o bloco não
  cabe um múltiplo exato de 90 min.
- `Tests\Unit\BookMentorSessionTest`: cria a sessão; retorna `null` sem criar duplicata quando o
  horário já está reservado (não cancelado); permite reservar de novo um horário cuja sessão anterior
  foi cancelada.
- `Tests\Unit\CancelMentorSessionTest`: marca `cancelled_at`.
- `Tests\Feature\Livewire\Membros\AgendaTest`: guest redireciona; membro `start` é redirecionado
  (tier:club bloqueia); membro `club` sem sessão futura vê o calendário; membro `club` com sessão
  futura vê o card da sessão em vez do calendário; reservar um horário válido cria a sessão e depois
  esconde o calendário; reservar um horário fora de `availableSlots` (adulterado) é ignorado, não
  cria nada; cancelar com mais de 24h de antecedência funciona; cancelar com menos de 24h é ignorado
  (sessão continua ativa); cancelar a sessão de outro membro é ignorado.
- `Tests\Feature\Livewire\Membros\DisponibilidadeTest`: guest redireciona; membro `club`/`start` é
  redirecionado (tier:mentor bloqueia); mentor adiciona um bloco; mentor remove um bloco próprio;
  mentor não consegue remover bloco de outro mentor (id inexistente pro escopo, no-op).
- `Tests\Unit\Support\PersonaNavigationTest` / `Tests\Feature\Membros\PersonaNavigationTest`: mesma
  atualização de `available:false→true`, agora pra `Minha sessão` (club) e `Disponibilidade` (mentor).
- `Tests\Feature\Admin\MentorSessionResourceTest`: non-admin 403; lista sessões existentes; admin
  consegue deletar uma sessão.

## 12. Fora de escopo

- Notificação/lembrete — issue #27.
- Múltiplos mentores / seleção de mentor pelo membro.
- Reagendamento direto (fluxo é só cancelar + marcar de novo).
- Mais de uma sessão futura ativa por membro simultaneamente.
- Lock pessimista real contra reserva concorrente do mesmo horário — a proteção via transação +
  checagem é suficiente pro volume esperado, não elimina 100% a corrida.
- "Radar do dia" / dossiês do mentor — issue #21 (Painel do mentor).
