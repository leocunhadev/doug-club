# Fluxo de upgrade Start → CLUB (issue #24)

Fecha a issue #24. Recria `/prototype/upgrade` (referência: `resources/views/prototype/upgrade.blade.php`)
como uma página real de aplicação pro CLUB — sem checkout/pagamento automático — com aprovação
manual pelo mentor via Filament.

## 1. Contexto

Confirmado com o usuário:

- **Aplicação + aprovação manual**, não checkout automático. O texto do próprio protótipo já sinaliza
  isso ("O Douglas analisa pessoalmente e responde em até 48h"), consistente com "poucas cadeiras por
  ano". O webhook do AbacatePay (`AbacatePayWebhookController`) não é tocado nesta feature — ele cobre
  a ativação inicial (Start), não upgrade pra CLUB.
- **Aprovação via lista no Filament com botão "Aprovar"**, não uma tela genérica de edição de usuário
  (que não existe hoje — a única vez que o tier foi alterado nesta sessão foi manualmente no banco).
  A ação "Aprovar" muda o `tier` do usuário pra `club`, envia um e-mail e resolve (apaga) a aplicação.
  "Recusar" é o `DeleteAction` padrão — só remove o registro, sem mutação, sem estado de rejeição
  (mesmo padrão minimalista já usado no `BridgeRequest`: o registro existe enquanto pendente; resolvido
  = apagado).
- **Aprovação notifica por e-mail** — diferente do padrão "sem notificação" usado no Cofre/Pessoas,
  porque aqui a notificação é o próprio resultado que o membro está esperando (a aplicação promete uma
  resposta em até 48h). Notification nativa do Laravel (`toMail()`), síncrona, mesmo padrão simples já
  usado em `ActivateUserFromPayment` (`Password::sendResetLink`). `MAIL_MAILER=smtp` já está configurado
  no `.env` deste ambiente — é um envio real, não um mailer de log.

## 2. Modelo de dados

Migration `create_club_applications_table`:

```
club_applications
  id
  user_id      FK -> users, cascadeOnDelete
  timestamps
```

`ClubApplication` (model): `$fillable = ['user_id']`. `user(): BelongsTo` — `user_id` é o nome padrão
que o Eloquent já infere de `user()`, então `belongsTo(User::class)` sem FK explícita é suficiente
(diferente de `member_id`/`mentor_id`/`requester_id`/`target_id` em features anteriores, que exigiam FK
explícita).

Sem campo de status: um registro existente = aplicação pendente. Aprovar ou recusar sempre termina
apagando o registro.

## 3. Gate de acesso — `tier=start` exato

Hoje `EnsureTier` só sabe checar "pelo menos club" (`hasClubAccess()`, que inclui mentor) ou "é mentor"
(`isMentor()`) — não existe um caso "é exatamente start". Esta feature precisa disso: um membro CLUB ou
mentor não deve ver a página de aplicação pro CLUB.

Adiciona `isStart(): bool` ao `User` (mesmo padrão de `isMentor()`):

```php
public function isStart(): bool
{
    return $this->tier === 'start';
}
```

E um novo caso no `match` de `EnsureTier::handle()`:

```php
$allowed = match ($minTier) {
    'club' => $user?->hasClubAccess() ?? false,
    'mentor' => $user?->isMentor() ?? false,
    'start' => $user?->isStart() ?? false,
    default => false,
};
```

A rota usa `->middleware(['auth', 'verified', 'active', 'tier:start'])` — um membro CLUB/mentor que
tentar acessar é redirecionado pro dashboard com a mesma mensagem padrão já usada pros outros tiers
("Esse conteúdo está disponível no start.").

## 4. Notificação por e-mail

`App\Notifications\ClubApplicationApproved` (`Illuminate\Notifications\Notification`, sem
`ShouldQueue` — síncrona, mesmo padrão de `Password::sendResetLink`):

```php
class ClubApplicationApproved extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Você foi aprovado pro CLUB!')
            ->greeting("Oi, {$notifiable->name}!")
            ->line('O Douglas analisou sua aplicação e você já faz parte do CLUB.')
            ->line('Sessões 1:1, cofre de documentos, encontros ao vivo e a rede de pessoas do CLUB já estão liberados pra você.')
            ->action('Entrar no CLUB', route('dashboard'))
            ->line('Bem-vindo!');
    }
}
```

## 5. Página `/membros/upgrade`

```php
Route::get('membros/upgrade', Upgrade::class)
    ->middleware(['auth', 'verified', 'active', 'tier:start'])
    ->name('membros.upgrade');
```

### `App\Livewire\Membros\Upgrade`

```php
class Upgrade extends Component
{
    use ComputesUserInitials;

    #[Computed]
    public function hasApplied(): bool
    {
        return ClubApplication::query()
            ->where('user_id', Auth::id())
            ->exists();
    }

    public function apply(): void
    {
        if ($this->hasApplied) {
            return;
        }

        ClubApplication::create(['user_id' => Auth::id()]);

        unset($this->hasApplied);
    }

    public function render()
    {
        return view('livewire.membros.upgrade');
    }
}
```

O `unset($this->hasApplied)` depois do `create()` é obrigatório pelo mesmo motivo já descoberto e
verificado na implementação do Pessoas (#23): o cache do `#[Computed]` sobrevive ao ciclo
hydrate→call→render de uma única requisição Livewire, então sem invalidar manualmente o botão
continuaria mostrando "Aplicar" no mesmo request que acabou de criar a aplicação.

### View

1. `x-membros.header`
2. Card `.upg` (fundo preto, portado do protótipo — ver seção 6): eyebrow "Isso vive no CLUB", título
   "O Start te dá o conteúdo.<br>O CLUB te dá o Douglas.", parágrafo e lista de 5 itens (copiados do
   protótipo, ainda verdadeiros: sessão 1:1 mensal, fio da mentoria, cofre, pontes curadas, encontros ao
   vivo com participação).
3. Botão: se `$this->hasApplied`, `<button disabled>` "Aplicação enviada — o Douglas responde em até
   48h." (estilo apagado, `disabled:opacity-40 disabled:cursor-not-allowed`, mesmo padrão de
   `nps-modal.blade.php`/`person-card.blade.php`); senão `<button wire:click="apply">` "Aplicar para o
   CLUB" (estilo `rounded-full bg-brand text-white`, cor laranja porque o card de fundo é preto —
   `bg-black` ficaria invisível sobre o próprio card).

## 6. Admin (Filament) — `ClubApplicationResource`

Lista as aplicações pendentes com duas ações por linha:

```php
class ClubApplicationResource extends Resource
{
    protected static ?string $model = ClubApplication::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;
    protected static ?string $recordTitleAttribute = 'id';

    public static function table(Table $table): Table
    {
        return ClubApplicationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListClubApplications::route('/')];
    }
}
```

Table (`ClubApplicationsTable`): colunas `user.name` ("Quem aplicou"), `user.email` ("E-mail"),
`created_at` ("Quando", `dateTime('d/m/Y H:i')`, sortable). `defaultSort` por `created_at` desc.
`recordActions`:
- `Action::make('approve')->label('Aprovar')->color('success')->requiresConfirmation()->action(function (ClubApplication $record) { $record->user->update(['tier' => 'club']); $record->user->notify(new ClubApplicationApproved); $record->delete(); })`
- `DeleteAction::make()->label('Recusar')` — decisão deliberada de reaproveitar o `DeleteAction` padrão
  em vez de uma ação customizada, já que "recusar" não precisa de nenhuma lógica além de remover o
  registro (mesmo padrão de "resolver apagando" já usado no `BridgeRequestResource`).

Sem form de criar/editar — resource só de listagem, mesmo padrão de `MentorSessionResource`/
`BridgeRequestResource`.

## 7. CSS

Porta as classes do protótipo pro padrão já estabelecido no projeto:

```css
.upg { background: theme('colors.black'); border: none; color: #fff; padding: clamp(28px,5vw,48px);
  position: relative; overflow: hidden; }
.upg::after { content: "CLUB"; position: absolute; right: -20px; bottom: -38px;
  font-family: 'Syne', sans-serif; font-weight: 800; font-size: 160px; color: transparent;
  -webkit-text-stroke: 1px rgba(255,81,0,.35); pointer-events: none; }
.upg .eyebrow { color: theme('colors.brand'); }
.upg h2 { font-size: clamp(26px,4.4vw,40px); line-height: 1.05; margin: 10px 0 14px; max-width: 560px; }
.upg p { color: #B9B4AB; max-width: 520px; }
.upg ul { list-style: none; margin: 20px 0 26px; display: flex; flex-direction: column; gap: 11px;
  max-width: 520px; }
.upg li { display: flex; gap: 12px; font-size: 14.5px; }
.upg li::before { content: ""; width: 8px; height: 8px; border-radius: 50%;
  background: theme('colors.brand'); flex-shrink: 0; margin-top: 7px; }
```

O wrapper do card aplica o cantos/sombra do padrão já estabelecido diretamente em Tailwind
(`rounded-[18px] shadow-[0_1px_2px_rgba(11,11,12,.05),0_10px_28px_rgba(11,11,12,.07)]`) junto da classe
`.upg`, que cuida do fundo preto/padding/pseudo-elemento — mesma composição já usada no `.person` do
Pessoas (Tailwind pro shell do card, classe customizada pro que não tem equivalente Tailwind direto).
Só `.upg .eyebrow` é usado nesta feature — sem o `.eyebrow` genérico do protótipo (sem `.upg` na
frente), que nenhuma tela real usa ainda; YAGNI.

## 8. Navegação

`PersonaNavigation::tabs()`: `Sessão 1:1` (`route: 'membros.upgrade'`, na lista `start`) vira
`available: true`.

## 9. Testes

- `Tests\Unit\ClubApplicationTest`: `user()` resolve; registro apagado quando o usuário é apagado
  (`cascadeOnDelete`).
- `Tests\Unit\Support\PersonaNavigationTest`: mesma atualização de `available:false→true`, agora pra
  `Sessão 1:1`.
- `Tests\Feature\Membros\UpgradeTest`: guest redireciona; membro `club` é negado (redirect pro
  dashboard); membro `mentor` é negado; membro `start` vê a página; `apply()` cria um
  `ClubApplication`; aplicar duas vezes não duplica o registro; depois de aplicar o botão mostra
  "Aplicação enviada".
- `Tests\Feature\Membros\PersonaNavigationTest`: mesma atualização de `available:false→true`, agora pra
  `Sessão 1:1`.
- `Tests\Feature\Admin\ClubApplicationResourceTest`: non-admin 403; lista mostra nome/e-mail de quem
  aplicou; "Aprovar" muda o `tier` do usuário pra `club`, dispara `ClubApplicationApproved`
  (`Notification::fake()` + `assertSentTo`) e apaga o registro; "Recusar" (`DeleteAction`) só apaga o
  registro, sem mudar o `tier` do usuário.

## 10. Fora de escopo

- Checkout/pagamento automático via AbacatePay — aplicação manual apenas, nesta versão.
- Estado de "recusado" — recusar é apagar o registro, sem histórico.
- Reenvio de e-mail ou notificação se a aplicação demorar mais que 48h.
- Limite de vagas ("poucas cadeiras por ano") — sem contagem/trava automática, é uma decisão manual do
  Douglas ao aprovar.
