# Radar do dia (issue #33)

Fecha a issue #33 (fatia B da divisão do antigo #21, desbloqueada pela #32). Recria
`/prototype/radar` (referência: `resources/views/prototype/radar.blade.php`) como o painel diário
real do mentor — 3 KPIs e um briefing pré-sessão — sem "Pontes sugeridas" (território da #23,
explicitamente fora de escopo).

## 1. Contexto

Confirmado com o usuário:

- **NPS médio é literal média simples**, não o cálculo de NPS de verdade (promotor − detrator) — a
  própria issue chama de "média", e o protótipo mostra um decimal ("9,2"), não um score de -100 a 100.
  Combina `LessonFeedback.score` + `EncontroFeedback.score` num único número.
- **Janela de 30 dias** pro NPS médio (só avaliações recentes) e **limite de 30 dias** pro alerta de
  "sem sessão" — a mesma cadência mensal já assumida pra Sessão 1:1 desde a Agenda (#20).
- **Mentorado sem nenhuma sessão também conta pro alerta**, usando a data de criação da conta como
  referência (em vez de não ter "intervalo" nenhum pra medir).
- **Sem link/interação nos cards de KPI.** O protótipo sugere "toque nele" no alerta, mas ligar isso
  à página de Dossiês (#32) exigiria mais trabalho (a `Dossies::$selectedMemberId` foi travada com
  `#[Locked]` justamente pra não aceitar seleção via parâmetro externo) — fica pra uma issue futura se
  fizer falta.
- **"Painel" (`MentorPlaceholder`) não muda.** Virar um redirecionamento pro Radar seria uma limpeza
  natural agora que todas as peças do painel do mentor existem, mas não é isso que a #33 pede — fica
  fora de escopo aqui.

## 2. Sem novos models

Esta feature não cria nenhuma tabela — é puro cálculo e exibição sobre dados que já existem:
`MentorSession` (#20), `LessonFeedback`/`EncontroFeedback` (#19/#25), `MentorNote`/`MentorCommitment`
(#32), `User`.

## 3. Página `/membros/mentor/radar`

```php
Route::get('membros/mentor/radar', Radar::class)
    ->middleware(['auth', 'verified', 'active', 'tier:mentor'])
    ->name('mentor.radar');
```

### `App\Livewire\Membros\Radar`

```php
class Radar extends Component
{
    use ComputesUserInitials;

    private const NO_SESSION_ALERT_THRESHOLD_DAYS = 30;

    private const NPS_WINDOW_DAYS = 30;

    #[Computed]
    public function todaySessions(): Collection
    {
        return MentorSession::query()
            ->whereDate('scheduled_at', now()->toDateString())
            ->whereNull('cancelled_at')
            ->orderBy('scheduled_at')
            ->with('member')
            ->get();
    }

    #[Computed]
    public function averageNpsScore(): ?float
    {
        $since = now()->subDays(self::NPS_WINDOW_DAYS);

        $scores = LessonFeedback::query()->where('created_at', '>=', $since)->pluck('score')
            ->merge(EncontroFeedback::query()->where('created_at', '>=', $since)->pluck('score'));

        return $scores->isEmpty() ? null : round((float) $scores->avg(), 1);
    }

    #[Computed]
    public function overdueMembers(): Collection
    {
        return User::query()
            ->where('tier', 'club')
            ->get()
            ->map(function (User $member) {
                $lastSession = MentorSession::query()
                    ->where('member_id', $member->id)
                    ->whereNull('cancelled_at')
                    ->where('scheduled_at', '<', now())
                    ->orderByDesc('scheduled_at')
                    ->first();

                $reference = $lastSession?->scheduled_at ?? $member->created_at;

                return [
                    'member' => $member,
                    'days_since' => (int) $reference->diffInDays(now()),
                ];
            })
            ->filter(fn (array $entry) => $entry['days_since'] > self::NO_SESSION_ALERT_THRESHOLD_DAYS)
            ->sortByDesc('days_since')
            ->values();
    }

    public function lastNoteFor(int $memberId): ?MentorNote
    {
        return MentorNote::query()
            ->where('member_id', $memberId)
            ->latest()
            ->first();
    }

    public function activeCommitmentFor(int $memberId): ?MentorCommitment
    {
        return MentorCommitment::query()
            ->where('member_id', $memberId)
            ->first();
    }

    public function render()
    {
        return view('livewire.membros.radar');
    }
}
```

Notas de implementação:

- `lastNoteFor()`/`activeCommitmentFor()` **não** são `#[Computed]` — recebem um `$memberId` como
  parâmetro, e o cache de `#[Computed]` do Livewire não é por-argumento (cachearia errado se
  chamado com IDs diferentes na mesma renderização). São métodos simples, chamados direto da view
  pra cada sessão de hoje — o volume é sempre pequeno (sessões de um único dia), então não há
  preocupação de N+1 real aqui.
- `overdueMembers()` usa `->get()->map(...)` (carrega todos os membros CLUB em memória e calcula em
  PHP) em vez de uma query agregada — o volume de membros CLUB é pequeno (mesma premissa já usada em
  `Pessoas::members()`/`Dossies::members()`, que também carregam a lista inteira sem paginação).
- `averageNpsScore()` retorna `null` quando não há avaliação no período — a view mostra `"—"` nesse
  caso, nunca um número calculado sobre zero avaliações.
- Sem `unset()` de nenhum `#[Computed]` — este componente não tem nenhuma ação que muta estado (é
  puramente leitura), então o bug de cache-stale já encontrado em Pessoas/Upgrade (ler um computed
  *antes* de mutar o que ele depende) não se aplica: não há mutação nenhuma aqui.

### View

1. `x-membros.header`
2. Cabeçalho: "Radar do dia" + subtítulo (copiado do protótipo): "O que precisa da sua atenção hoje.
   Esta é a sua secretária."
3. Grid de 3 KPIs (`.kpis`, ver seção 5):
   - **Sessões hoje**: número = `$this->todaySessions->count()`. Subtítulo: se vazio, "Nenhuma sessão
     hoje."; senão, `"sessões 1:1 hoje: "` seguido de `"{{ $session->member->name }} às
     {{ $session->scheduled_at->format('H\h') }}"` de cada sessão, separadas por vírgula.
   - **NPS médio**: número = `$this->averageNpsScore` formatado com vírgula decimal
     (`number_format($this->averageNpsScore, 1, ',', '.')`, ex.: "9,2") ou `"—"` se `null`. Subtítulo
     fixo: "NPS médio das últimas sessões e aulas (últimos 30 dias)."
   - **Alerta** (`.kpi.alerta`, ver seção 5): número = `$this->overdueMembers->count()`. Subtítulo:
     vazio → "Nenhum mentorado atrasado."; exatamente 1 → `"alerta: {{ nome }} está há {{ dias }} dias
     sem sessão."`; mais de 1 → `"alerta: {{ contagem }} mentorados sem sessão há mais de 30 dias."`.
4. "Antes das sessões de hoje" (`<h3>`): para cada `$this->todaySessions`, um card
   (Tailwind `rounded-[18px] border border-sand bg-card shadow-[...] border-l-4 border-brand p-[22px]`
   — o acento laranja à esquerda vem de utilitário Tailwind puro, `border-l-4 border-brand`, sem CSS
   nova) mostrando:
   - `<p class="eyebrow laranja">{{ nome }} · {{ horário completo, "H\hi" }}</p>` (reaproveita
     `.eyebrow.laranja`, já portado em #32).
   - Um parágrafo: se existe `lastNoteFor($session->member_id)`, `"Última sessão: {{ nota->body }}"`;
     senão, `"Nenhuma nota registrada ainda."`. Se existe `activeCommitmentFor($session->member_id)`
     com `text` preenchido, acrescenta `" Compromisso: {{ texto }}."`.
   - Estado vazio (sem sessões hoje): "Nenhuma sessão hoje." — mesma frase do KPI, mas como parágrafo
     solto no lugar da lista de cards.

## 4. Navegação

`PersonaNavigation::tabs()`: `Radar` (`route: 'mentor.radar'`, na lista `mentor`) vira
`available: true`.

## 5. CSS

Porta as classes do protótipo pro padrão já estabelecido no projeto:

```css
.kpis { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 20px; }
@media (max-width: 680px) { .kpis { grid-template-columns: 1fr; } }
.kpi { padding: 20px 22px; }
.kpi b { font-family: 'Syne', sans-serif; font-size: 32px; display: block; line-height: 1; }
.kpi small { color: theme('colors.stone'); }
.kpi.alerta { border-color: #FFD2BC; background: theme('colors.brand-soft'); }
.kpi.alerta b { color: theme('colors.brand'); }
```

O wrapper de cada card de KPI aplica o shell Tailwind já padrão (`rounded-[18px] border border-sand
bg-card shadow-[...]`) junto das classes `.kpi`/`.kpi.alerta` — `.kpi.alerta` tem duas classes
(especificidade maior que os utilitários `border-sand`/`bg-card`, que têm uma só), então a cor de
fundo/borda laranja do estado de alerta vence corretamente sobre o shell padrão, mesmo padrão já
comprovado em `.upg` sobrepondo o shell claro do card no Upgrade (#24).

## 6. Testes

- `Tests\Feature\Membros\RadarTest`:
  - guest redireciona; membro `club` é negado.
  - conta só sessões de hoje não canceladas (uma de ontem e uma cancelada hoje não entram na
    contagem).
  - KPI de sessões mostra nome e horário de cada sessão de hoje.
  - NPS médio combina `LessonFeedback` e `EncontroFeedback` dos últimos 30 dias (nota de 31 dias atrás
    não entra no cálculo).
  - NPS médio mostra "—" quando não há nenhuma avaliação no período.
  - mentorado com última sessão há mais de 30 dias entra no alerta; mentorado com sessão há 29 dias
    não entra (testes separados, provando o limite dos dois lados).
  - mentorado sem nenhuma sessão, criado há mais de 30 dias, entra no alerta; criado há menos de 30
    dias não entra (testes separados).
  - alerta com exatamente 1 mentorado nomeia a pessoa; alerta com mais de 1 mostra só a contagem.
  - briefing mostra a nota mais recente e o compromisso ativo de quem tem sessão hoje.
  - briefing mostra os placeholders ("Nenhuma nota registrada ainda.") quando não existem ainda.
  - sem nenhuma sessão hoje, mostra "Nenhuma sessão hoje." tanto no KPI quanto na seção de briefing.
- `Tests\Unit\Support\PersonaNavigationTest` / `Tests\Feature\Membros\PersonaNavigationTest`: mesma
  atualização de `available:false→true`, agora pra `Radar` (esta é a última aba travada do tier
  `mentor` — depois desta, todas as 5 abas do mentor ficam disponíveis).

## 7. Fora de escopo

- "Pontes sugeridas" (matching entre membros) — território da #23 (Pessoas do CLUB), confirmado pela
  própria issue.
- Alerta imediato por nota baixa ("Notas até 6 disparam alerta imediato para você", texto do
  protótipo) — exigiria um sistema de notificação (e-mail, por exemplo) disparado no momento da
  avaliação, não uma leitura passiva na página do Radar. Fica pra uma issue futura se o usuário
  quiser.
- Cards de KPI clicáveis/linkados pra Dossiês — nenhuma interação, só leitura.
- "Painel" (`MentorPlaceholder`) virar redirecionamento pro Radar — limpeza natural, mas não pedida
  por esta issue.
