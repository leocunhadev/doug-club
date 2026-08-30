# Radar: Pontes sugeridas (match ensinar/aprender) — Design

## Objetivo

Fechar a issue #41: implementar a seção "Pontes sugeridas" do protótipo do Radar — o mentor vê sugestões de conexão entre membros club com base no cruzamento "o que um quer aprender" × "o que outro pode ensinar", e pode disparar a apresentação com um clique.

## Escopo

**Dentro do escopo:** só o card de match ensinar/aprender (o card "Ricardo/Marina" do protótipo).

**Fora do escopo:** o segundo tipo de card do protótipo ("Start engajado pronto pro CLUB", baseado em aulas assistidas + downloads de framework) foi desmembrado para a issue #50 — depende de uma infraestrutura de rastreamento de download que não existe hoje, um subsistema genuinamente independente deste.

## Estado atual (contexto)

- `App\Models\User` tem `teach_tags`/`learn_tags` (cast `array`, preenchidos como texto livre separado por vírgula na página de conta — `resources/views/livewire/profile/update-profile-information-form.blade.php:246`, visível só para `tier === 'club'`).
- `App\Models\BridgeRequest` (`requester_id`, `target_id`, índice único no par — issue #34) representa "o requester quer se conectar com o target". Hoje só é criado pelo próprio membro em `Pessoas::requestBridge()`. Não existe nenhum mecanismo de notificação quando um `BridgeRequest` é criado — `Pessoas::requestedTargetIds()` só mostra pedidos que o USUÁRIO AUTENTICADO fez, nunca os que ele recebeu.
- `App\Livewire\Membros\Radar` já tem `#[Computed]` para KPIs do dia (`todaySessions`, `averageNpsScore`, `overdueMembers`) e métodos não-computed (`lastNoteFor`, `activeCommitmentFor`) — mesmo padrão que este trabalho vai seguir.
- Notificações reais por e-mail (`ShouldQueue`, `via(): ['mail']`) já existem no projeto desde a issue #27 (`App\Notifications\MentorSessionBookedNotification` etc.) — mesmo padrão será reaproveitado aqui.
- CSS `.avatar`/`.avatar.o` (círculo com iniciais) já existe em `resources/css/app.css:272-324`. `.match`/`.duo` (o card de sugestão + avatares sobrepostos) ainda não foi portado do protótipo (`resources/views/prototype/partials/_styles.blade.php:204-208`).
- `$user->initials` é um accessor real no model `User` (`app/Models/User.php:81-90`) — usado pelos avatares em todo o app.

## Arquitetura

### Algoritmo de match

`Radar::suggestedBridges(): Collection` (novo `#[Computed]`):

1. Carrega todos os `User` com `tier === 'club'` (`select id, name, teach_tags, learn_tags` — dataset pequeno, dezenas de membros, sem necessidade de query SQL com funções JSON).
2. Monta um índice de pares já conectados a partir de `BridgeRequest::all()` (ambas as direções), para exclusão.
3. Para cada par ordenado (aprendiz, professor) com aprendiz ≠ professor e o par ainda não conectado: verifica se alguma tag de `learn_tags` do aprendiz bate (comparação exata, case-insensitive via `mb_strtolower`) com alguma tag de `teach_tags` do professor. Sem fuzzy matching — limitação conhecida, documentada aqui, não nesta v1.
4. Cada match encontrado carrega `learner`, `teacher` e a `tag` que bateu (a primeira encontrada, se houver mais de uma).
5. Retorna os 3 primeiros matches (`->take(3)`) — não há critério de "melhor match", é só um teto de densidade visual, consistente com os 2 cards de exemplo do protótipo.

```php
#[Computed]
public function suggestedBridges(): Collection
{
    $members = User::query()
        ->where('tier', 'club')
        ->get(['id', 'name', 'teach_tags', 'learn_tags']);

    $connectedPairs = BridgeRequest::query()
        ->get(['requester_id', 'target_id'])
        ->flatMap(fn (BridgeRequest $br) => ["{$br->requester_id}-{$br->target_id}", "{$br->target_id}-{$br->requester_id}"])
        ->flip();

    $matches = collect();

    foreach ($members as $learner) {
        foreach ($members as $teacher) {
            if ($learner->id === $teacher->id) {
                continue;
            }

            if (isset($connectedPairs["{$learner->id}-{$teacher->id}"])) {
                continue;
            }

            $matchedTag = collect($learner->learn_tags ?? [])
                ->first(fn (string $tag) => collect($teacher->teach_tags ?? [])
                    ->contains(fn (string $t) => mb_strtolower($t) === mb_strtolower($tag)));

            if ($matchedTag !== null) {
                $matches->push([
                    'learner' => $learner,
                    'teacher' => $teacher,
                    'tag' => $matchedTag,
                ]);
            }
        }
    }

    return $matches->take(3);
}
```

### Ação "Fazer a ponte"

`Radar::makeBridge(int $learnerId, int $teacherId, string $tag): void`:

1. Valida que os dois IDs correspondem a usuários `tier === 'club'` existentes (defesa contra payload adulterado — o parâmetro `$tag` é usado só para o texto da notificação, sem risco de segurança real, mas os IDs precisam ser revalidados no servidor antes de criar qualquer registro).
2. Se já existe um `BridgeRequest` entre os dois (qualquer direção), não faz nada (idempotente — cobre o caso de clique duplo).
3. Cria `BridgeRequest::create(['requester_id' => $learnerId, 'target_id' => $teacherId])`.
4. Notifica os dois membros por e-mail (`ShouldQueue`, mesmo padrão da issue #27), com o texto adaptado ao papel de cada um.
5. `unset($this->suggestedBridges);` para o par sumir da lista imediatamente.

### Notificação

Uma única classe `App\Notifications\BridgeSuggestedNotification`, parametrizada pelo papel — em vez de duas classes quase-idênticas (padrão diferente do usado na issue #27, onde os papéis tinham conteúdo bem distinto; aqui o conteúdo é o mesmo texto com pronomes trocados, então uma classe com um parâmetro é mais direto):

```php
class BridgeSuggestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $otherMember,
        public string $tag,
        public bool $iAmTheLearner,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $line = $this->iAmTheLearner
            ? "O Douglas te apresentou a {$this->otherMember->name}, que pode te ajudar com {$this->tag}."
            : "O Douglas te apresentou a {$this->otherMember->name}, que quer aprender sobre {$this->tag} — e você pode ajudar.";

        return (new MailMessage)
            ->subject('Uma ponte foi feita pra você')
            ->greeting("Oi, {$notifiable->name}!")
            ->line($line)
            ->action('Ver pessoas do CLUB', route('membros.pessoas'));
    }
}
```

Disparo em `makeBridge()`:

```php
$learner->notify(new BridgeSuggestedNotification($teacher, $tag, iAmTheLearner: true));
$teacher->notify(new BridgeSuggestedNotification($learner, $tag, iAmTheLearner: false));
```

### View

`resources/views/livewire/membros/radar.blade.php` ganha uma nova seção entre os KPIs e "Antes das sessões de hoje":

```blade
<h3 class="text-[17px] font-semibold mb-3">Pontes sugeridas</h3>
@forelse ($this->suggestedBridges as $match)
    <div class="match rounded-[18px] border border-sand bg-card shadow-[0_1px_2px_rgba(11,11,12,.05),0_10px_28px_rgba(11,11,12,.07)]">
        <div class="duo">
            <div class="avatar">{{ $match['learner']->initials }}</div>
            <div class="avatar o">{{ $match['teacher']->initials }}</div>
        </div>
        <div class="d">
            <b>{{ $match['learner']->name }}</b> quer aprender <em>{{ $match['tag'] }}</em> e <b>{{ $match['teacher']->name }}</b> pode ensinar isso.
        </div>
        <button type="button" wire:click="makeBridge({{ $match['learner']->id }}, {{ $match['teacher']->id }}, '{{ $match['tag'] }}')"
                class="px-3.5 py-1.5 rounded-full text-sm font-semibold bg-black text-white hover:bg-brand">
            Fazer a ponte
        </button>
    </div>
@empty
    <p class="text-stone mb-6">Nenhuma ponte sugerida no momento.</p>
@endforelse
```

`resources/css/app.css` ganha `.match`/`.duo` portados do protótipo (`_styles.blade.php:204-208`), adaptados para usar `theme('colors.orange')`/tokens já existentes no lugar dos valores CSS var brutos do protótipo, seguindo o padrão já usado no resto de `app.css`.

## Casos de borda

- **Membro sem nenhuma tag preenchida**: `learn_tags`/`teach_tags` ficam `null`/`[]` — o `collect(... ?? [])` trata isso como vazio, nunca gera match nem erro.
- **Tag com espaços/capitalização diferente** ("Precificação " vs "precificação"): a comparação usa `mb_strtolower` mas não faz `trim()` explícito — como o parsing de tags em `update-profile-information-form.blade.php` já faz `trim()` em cada tag ao salvar (parte do fluxo existente da issue #23), o dado já chega sem espaços extras; não é necessário tratar de novo aqui.
- **Match mútuo** (A aprende o que B ensina, E B aprende o que A ensina, tags diferentes): aparecem como duas sugestões separadas — comportamento correto, são duas pontes distintas.
- **Clique duplo em "Fazer a ponte"**: o passo 2 do `makeBridge()` (checagem de par já conectado) torna a ação idempotente.
- **IDs adulterados no payload do Livewire** (ex: um dos dois não é mais `tier=club`, ou não existe): validado no passo 1 do `makeBridge()`, a ação vira no-op silencioso.

## Testes

- `Radar::suggestedBridges()`: match encontrado quando as tags batem (case-insensitive); nenhum match quando não batem; par excluído quando já existe `BridgeRequest` (qualquer direção); membro sem tags nunca aparece; limite de 3 respeitado quando há mais de 3 matches possíveis.
- `Radar::makeBridge()`: cria o `BridgeRequest` na direção certa; dispara as duas notificações (`Notification::fake()`, checando o destinatário e `iAmTheLearner` de cada uma); não faz nada se o par já está conectado; não faz nada se um dos IDs não é `tier=club`.
- `App\Notifications\BridgeSuggestedNotification`: `toMail()` gera o texto certo para cada valor de `iAmTheLearner`, mesmo padrão de teste das notificações da issue #27.
- View: a seção "Pontes sugeridas" aparece com o texto do match quando há sugestões; mostra o estado vazio quando não há.
