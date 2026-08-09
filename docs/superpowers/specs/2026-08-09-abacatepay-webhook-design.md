# Spec — Webhook AbacatePay: criar/liberar acesso de User (issue #12)

## Contexto

Não há self-registration pública (`docs/lms-spec.md` seções 2 e 4): usuários são criados por
seed/admin. A issue #12 pede que a criação/liberação de acesso aconteça automaticamente
quando uma compra é confirmada em uma plataforma de pagamento externa — a plataforma
escolhida é a [AbacatePay](https://abacatepay.com) (API v2), um provedor de pagamentos
brasileiro (PIX/cartão) com webhooks nativos para checkout e assinatura.

O DO.ing Club é uma oferta única (mentoria/clube) — não há múltiplos planos/produtos a
mapear para níveis de acesso diferentes.

## Referência: documentação AbacatePay (pesquisada em 2026-08-09)

- Página geral de webhooks: https://docs.abacatepay.com/pages/webhooks
- Referência de eventos: https://docs.abacatepay.com/pages/webhooks/reference
- Criação de link de pagamento (`externalId`/`metadata` suportados): https://docs.abacatepay.com/pages/payment-links/create

**Payload base de todo webhook:**
```json
{
  "id": "log_abc123xyz",
  "event": "checkout.completed",
  "apiVersion": 2,
  "devMode": false,
  "data": { "...": "varia por evento" }
}
```

`data.customer` é um objeto com `email`/`name` (ou `null` se não houver cliente vinculado
ao evento). A documentação **não expõe o schema completo de `data`** por tipo de evento e
recomenda explicitamente não validar o payload inteiro contra um schema rígido, para não
quebrar com mudanças futuras da API. Este spec assume `data.customer.email` e
`data.customer.name` como os únicos campos de `data` que o código depende — tudo o mais é
tratado como opaco e apenas armazenado bruto para auditoria (ver seção "Auditoria").

**Segurança do webhook:** a AbacatePay oferece um secret na query string
(`?webhookSecret=...`, configurado ao cadastrar o endpoint no painel deles) e,
adicionalmente, uma assinatura HMAC-SHA256 no header `X-Webhook-Signature`. A doc é
ambígua sobre qual chave usar para validar essa assinatura ("chave pública da
AbacatePay" — terminologia atípica para HMAC, que normalmente usa segredo compartilhado).
Por essa ambiguidade, **este spec implementa apenas a validação do secret na query
string** (mecanismo claro e sem ambiguidade) e deixa a verificação HMAC como reforço
futuro, fora de escopo.

**Eventos suportados** (tabela completa da doc):

| Evento | Significado |
|---|---|
| `checkout.completed` | Pagamento de um checkout confirmado |
| `checkout.refunded` | Reembolso concluído |
| `checkout.disputed` | Disputa/chargeback aberta |
| `checkout.lost` | Disputa perdida |
| `transparent.completed` / `.refunded` / `.disputed` / `.lost` | Mesmos eventos, para checkout "transparente" (embutido no site do vendedor em vez de página hospedada pela AbacatePay) |
| `subscription.completed` | Assinatura criada e ativada |
| `subscription.cancelled` | Assinatura cancelada |
| `subscription.renewed` | Cobrança recorrente paga |
| `subscription.trial_started` | Trial iniciado |
| `payout.completed` / `.failed` | Saque da conta AbacatePay (não relacionado a alunos) |
| `transfer.completed` / `.failed` | Transferência (não relacionado a alunos) |

## Objetivo

1. Ao receber confirmação de pagamento/assinatura/trial, criar o `User` (se não existir)
   com acesso liberado, ou reativar um `User` existente cujo acesso tinha sido revogado.
2. Ao receber reembolso/disputa perdida/cancelamento de assinatura, revogar o acesso do
   `User` correspondente (bloqueia login, sem deletar a conta).
3. Um `User` novo recebe um e-mail para definir a própria senha (reaproveita o fluxo de
   reset de senha do Breeze) e já nasce com e-mail verificado (o pagamento já confirma que
   o e-mail é real).

## Design

### 1. Dados

**Migration `add_access_revoked_at_to_users_table`:**
```php
Schema::table('users', function (Blueprint $table) {
    $table->timestamp('access_revoked_at')->nullable()->after('email_verified_at');
});
```
`null` = acesso ativo. Preenchido = acesso revogado (momento do reembolso/cancelamento).
Timestamp em vez de boolean para manter um histórico simples, no mesmo espírito de
`email_verified_at`.

**Migration `create_payment_webhook_events_table`:**
```php
Schema::create('payment_webhook_events', function (Blueprint $table) {
    $table->id();
    $table->string('provider')->default('abacatepay');
    $table->string('external_id');       // o "id" do payload (ex: log_abc123xyz)
    $table->string('event');             // ex: checkout.completed
    $table->json('payload');             // payload bruto completo, pra debug/auditoria
    $table->timestamp('processed_at')->nullable();
    $table->timestamps();

    $table->unique(['provider', 'external_id']);
});
```
A constraint `unique(provider, external_id)` dá idempotência: se a AbacatePay reentregar o
mesmo evento (comportamento comum em webhooks — "at least once delivery"), a segunda
tentativa de insert falha e o handler simplesmente responde 200 sem reprocessar.

Model `PaymentWebhookEvent` (Eloquent simples, sem relações).

### 2. Endpoint

`routes/web.php`:
```php
Route::post('webhooks/abacatepay', AbacatePayWebhookController::class)
    ->name('webhooks.abacatepay');
```

`bootstrap/app.php` — excluir da verificação CSRF (é uma chamada servidor-a-servidor, sem
cookie de sessão):
```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->validateCsrfTokens(except: ['webhooks/*']);
    $middleware->alias(['active' => \App\Http\Middleware\EnsureAccessIsActive::class]);
})
```

`config/services.php` — novo bloco:
```php
'abacatepay' => [
    'webhook_secret' => env('ABACATEPAY_WEBHOOK_SECRET'),
],
```

`AbacatePayWebhookController` (`app/Http/Controllers/Webhooks/AbacatePayWebhookController.php`):
1. Compara `request()->query('webhookSecret')` com `config('services.abacatepay.webhook_secret')`
   usando `hash_equals()`. Se não bater ou o config estiver vazio, retorna `403` sem tocar
   no banco.
2. Lê `id`, `event`, `data` do corpo JSON. Se `id` ou `event` estiverem ausentes, `422`.
3. Tenta criar o registro em `payment_webhook_events` (`provider = 'abacatepay'`,
   `external_id = id`, `event`, `payload = $request->all()`). Se der conflito de
   unicidade (evento já visto), responde `200` imediatamente — idempotência.
4. Extrai `data.customer.email` (e `data.customer.name` se presente). Se `email` estiver
   ausente/nulo, marca o registro como processado (sem side-effect) e responde `200` —
   não há para quem liberar/revogar acesso.
5. Despacha para `ActivateUserFromPayment` ou `RevokeUserAccess` conforme a tabela de
   eventos abaixo. Eventos de `payout.*`/`transfer.*` (e qualquer evento desconhecido) só
   marcam o registro como processado e respondem `200`.
6. Marca `processed_at = now()` no registro de auditoria e responde `200`.

**Mapeamento evento → ação:**

| Ação | Eventos |
|---|---|
| `ActivateUserFromPayment` | `checkout.completed`, `transparent.completed`, `subscription.completed`, `subscription.renewed`, `subscription.trial_started` |
| `RevokeUserAccess` | `checkout.refunded`, `checkout.disputed`, `checkout.lost`, `transparent.refunded`, `transparent.disputed`, `transparent.lost`, `subscription.cancelled` |
| (nenhuma — só loga e responde 200) | qualquer outro evento (`payout.*`, `transfer.*`, eventos futuros desconhecidos) |

### 3. `ActivateUserFromPayment` (`app/Actions/ActivateUserFromPayment.php`)

```php
public function handle(string $email, ?string $name): User
{
    $user = User::where('email', $email)->first();

    if (! $user) {
        $user = User::create([
            'name' => $name ?: Str::before($email, '@'),
            'email' => $email,
            'password' => Str::password(32),
            'email_verified_at' => now(),
        ]);

        Password::sendResetLink(['email' => $email]);

        return $user;
    }

    if ($user->access_revoked_at !== null) {
        $user->update(['access_revoked_at' => null]);
    }

    return $user;
}
```

Usuário já existente e já ativo (recompra/renovação normal): nenhuma escrita extra, sem
reenviar e-mail de senha (só o cadastro novo dispara isso).

### 4. `RevokeUserAccess` (`app/Actions/RevokeUserAccess.php`)

```php
public function handle(string $email): void
{
    User::where('email', $email)
        ->whereNull('access_revoked_at')
        ->update(['access_revoked_at' => now()]);
}
```

Usuário inexistente ou já revogado: no-op (a query simplesmente não afeta linhas).

### 5. Bloqueio de acesso

**`LoginForm::authenticate()`** (`app/Livewire/Forms/LoginForm.php`) — depois do
`Auth::attempt()` bem-sucedido:
```php
if (Auth::user()->access_revoked_at !== null) {
    Auth::logout();
    RateLimiter::hit($this->throttleKey());

    throw ValidationException::withMessages([
        'form.email' => 'Sua assinatura está inativa. Entre em contato com o suporte.',
    ]);
}
```

**`EnsureAccessIsActive`** (`app/Http/Middleware/EnsureAccessIsActive.php`) — cobre sessão
já aberta que teve o acesso revogado no meio do caminho:
```php
public function handle(Request $request, Closure $next)
{
    if ($request->user()?->access_revoked_at !== null) {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('status', 'Sua assinatura está inativa. Entre em contato com o suporte.');
    }

    return $next($request);
}
```

Aplicado em `routes/web.php` junto de `auth`/`verified` nas rotas de `/membros*`:
```php
Route::get('membros', Dashboard::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('dashboard');
```
(mesma alteração nas outras rotas de `membros/*`).

### 6. Auditoria

`payment_webhook_events` guarda o payload bruto de todo evento recebido, processado ou
não — dá visibilidade via `tinker`/DB direto para diagnosticar divergências entre o schema
assumido aqui e o que a AbacatePay realmente envia em produção, sem precisar reprocessar
nada manualmente. Não vira tela no Filament nesta v1 (fora de escopo).

## Testes

- **Endpoint:**
  - `webhookSecret` ausente/errado → `403`, nada gravado.
  - `checkout.completed` com `customer.email` novo → cria `User` com `access_revoked_at`
    null, `email_verified_at` preenchido, dispara `Password::sendResetLink` (fake do
    notification/mail).
  - Mesmo evento (`id` repetido) enviado duas vezes → só um `User` criado, segunda
    chamada responde `200` sem duplicar.
  - `checkout.refunded` para e-mail de um `User` ativo → `access_revoked_at` preenchido.
  - `checkout.refunded` para e-mail sem `User` correspondente → `200`, nenhuma escrita.
  - `data.customer` nulo → `200`, nenhum `User` tocado, evento marcado como processado.
  - Evento desconhecido (`payout.completed`) → `200`, nenhum `User` tocado.
- **`LoginForm`:** usuário com `access_revoked_at` preenchido falha o login com a mensagem
  correta e é deslogado.
- **`EnsureAccessIsActive`:** usuário autenticado cujo `access_revoked_at` é preenchido
  *depois* do login é redirecionado para `/login` na próxima request a `/membros`.

## Fora do escopo

- Verificação HMAC do header `X-Webhook-Signature` (ambiguidade na doc sobre a chave).
- Suporte a múltiplos produtos/planos AbacatePay mapeando para níveis de acesso diferentes.
- Tela no Filament para visualizar `payment_webhook_events` (consulta via tinker/DB por
  enquanto).
- Reenvio manual de e-mail de "definir senha" pelo admin (fora desta issue).
