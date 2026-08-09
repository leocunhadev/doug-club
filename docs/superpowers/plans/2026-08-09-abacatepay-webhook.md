# AbacatePay Webhook Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A webhook endpoint that lets AbacatePay create/reactivate `User` accounts on payment confirmation and revoke access on refund/cancellation, closing issue #12.

**Architecture:** A single invokable controller (`AbacatePayWebhookController`) validates a shared secret, logs every event to `payment_webhook_events` for idempotency/auditing, and dispatches to one of two small Actions (`ActivateUserFromPayment` / `RevokeUserAccess`) based on the event name. Access revocation is enforced at login (`LoginForm`) and for already-open sessions (`EnsureAccessIsActive` middleware), both keyed off a new `users.access_revoked_at` nullable timestamp.

**Tech Stack:** Laravel 13, Eloquent, PHPUnit (class-based `test_*` methods, not Pest).

## Global Constraints

- Webhook auth is the `webhookSecret` query-string parameter only (compared with `hash_equals`) — no HMAC signature verification in this scope (AbacatePay's docs are ambiguous about the key to use for that).
- `users.access_revoked_at` timestamp, nullable, `after('email_verified_at')`. `null` = active access; a timestamp = revoked at that moment.
- `payment_webhook_events` has `unique(['provider', 'external_id'])` — this is how duplicate webhook deliveries are made idempotent.
- New user created from a webhook gets `email_verified_at = now()` (payment already proves the email is real) and a random password, then receives Laravel's standard password-reset email via `Password::sendResetLink()`.
- DO.ing Club is a single product/plan — no product/plan → access-tier mapping.
- `payout.*`, `transfer.*`, and any unrecognized event type: log for audit, respond `200`, no side effect on any `User`.
- Activate-event list: `checkout.completed`, `transparent.completed`, `subscription.completed`, `subscription.renewed`, `subscription.trial_started`.
- Revoke-event list: `checkout.refunded`, `checkout.disputed`, `checkout.lost`, `transparent.refunded`, `transparent.disputed`, `transparent.lost`, `subscription.cancelled`.
- Run `php artisan test` (PHPUnit) to verify — this repo has no Pest DSL.

---

### Task 1: Access revocation enforcement

**Files:**
- Create: `database/migrations/2026_08_09_120000_add_access_revoked_at_to_users_table.php`
- Modify: `app/Models/User.php`
- Create: `app/Http/Middleware/EnsureAccessIsActive.php`
- Modify: `bootstrap/app.php`
- Modify: `app/Livewire/Forms/LoginForm.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Auth/AuthenticationTest.php` (extend)
- Test: `tests/Feature/Membros/AccessRevocationTest.php` (new)

**Interfaces:**
- Produces: `users.access_revoked_at` (nullable timestamp, `datetime` cast, in `User`'s fillable list). Middleware alias `'active'` resolving to `App\Http\Middleware\EnsureAccessIsActive`.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Auth/AuthenticationTest.php` (new test method in the class):

```php
    public function test_users_with_revoked_access_cannot_authenticate(): void
    {
        $user = User::factory()->create(['access_revoked_at' => now()]);

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password');

        $component->call('login');

        $component
            ->assertHasErrors('form.email')
            ->assertNoRedirect();

        $this->assertGuest();
    }
```

Create `tests/Feature/Membros/AccessRevocationTest.php`:

```php
<?php

namespace Tests\Feature\Membros;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessRevocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_session_is_logged_out_when_access_is_revoked_mid_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->get('/membros')->assertOk();

        $user->update(['access_revoked_at' => now()]);

        $this->get('/membros')->assertRedirect('/login');

        $this->assertGuest();
    }
}
```

- [ ] **Step 2: Run both to confirm they fail**

Run: `php artisan test --filter=test_users_with_revoked_access_cannot_authenticate`
Expected: FAIL (column `access_revoked_at` doesn't exist yet — query error)

Run: `php artisan test --filter=AccessRevocationTest`
Expected: FAIL (same reason)

- [ ] **Step 3: Create the migration**

`database/migrations/2026_08_09_120000_add_access_revoked_at_to_users_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('access_revoked_at')->nullable()->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('access_revoked_at');
        });
    }
};
```

- [ ] **Step 4: Update the `User` model**

In `app/Models/User.php`, change the `Fillable` attribute (line 16) from:

```php
#[Fillable(['name', 'email', 'password', 'is_admin'])]
```

to:

```php
#[Fillable(['name', 'email', 'password', 'is_admin', 'access_revoked_at', 'email_verified_at'])]
```

And add `access_revoked_at` to the `casts()` method:

```php
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'access_revoked_at' => 'datetime',
        ];
    }
```

- [ ] **Step 5: Create the middleware**

`app/Http/Middleware/EnsureAccessIsActive.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccessIsActive
{
    public function handle(Request $request, Closure $next): Response
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
}
```

- [ ] **Step 6: Register the middleware alias**

In `bootstrap/app.php`, replace the empty `withMiddleware` closure:

```php
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
```

with:

```php
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => \App\Http\Middleware\EnsureAccessIsActive::class,
        ]);
    })
```

- [ ] **Step 7: Check access in `LoginForm`**

In `app/Livewire/Forms/LoginForm.php`, replace:

```php
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only(['email', 'password']), $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'form.email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }
```

with:

```php
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only(['email', 'password']), $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'form.email' => trans('auth.failed'),
            ]);
        }

        if (Auth::user()->access_revoked_at !== null) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'form.email' => 'Sua assinatura está inativa. Entre em contato com o suporte.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }
```

- [ ] **Step 8: Add the `active` middleware to the membros routes**

In `routes/web.php`, add `'active'` to the middleware array of the three `membros*` routes:

```php
Route::get('membros', Dashboard::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('dashboard');

Route::get('membros/materiais/{material}/download', LessonMaterialDownloadController::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('membros.materials.download');

Route::get('membros/sobre', Sobre::class)
    ->middleware(['auth', 'verified', 'active'])
    ->name('membros.sobre');
```

(Leave the `profile` route untouched — out of scope per the design spec.)

- [ ] **Step 9: Run the tests to confirm they pass**

Run: `php artisan test --filter=AuthenticationTest`
Expected: PASS (all tests, including the new one)

Run: `php artisan test --filter=AccessRevocationTest`
Expected: PASS

- [ ] **Step 10: Commit**

```bash
git add database/migrations/2026_08_09_120000_add_access_revoked_at_to_users_table.php app/Models/User.php app/Http/Middleware/EnsureAccessIsActive.php bootstrap/app.php app/Livewire/Forms/LoginForm.php routes/web.php tests/Feature/Auth/AuthenticationTest.php tests/Feature/Membros/AccessRevocationTest.php
git commit -m "Add access revocation: users.access_revoked_at, login check, active middleware"
```

---

### Task 2: Webhook endpoint skeleton (secret check + idempotent event log)

**Files:**
- Create: `database/migrations/2026_08_09_120100_create_payment_webhook_events_table.php`
- Create: `app/Models/PaymentWebhookEvent.php`
- Modify: `config/services.php`
- Modify: `.env`
- Modify: `.env.example`
- Modify: `bootstrap/app.php`
- Create: `app/Http/Controllers/Webhooks/AbacatePayWebhookController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Webhooks/AbacatePayWebhookTest.php` (new)

**Interfaces:**
- Produces: `PaymentWebhookEvent` Eloquent model (`provider`, `external_id`, `event`, `payload` (array cast), `processed_at` (datetime cast, nullable)). Route `webhooks.abacatepay` (`POST /webhooks/abacatepay`). Config key `services.abacatepay.webhook_secret`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Webhooks/AbacatePayWebhookTest.php`:

```php
<?php

namespace Tests\Feature\Webhooks;

use App\Models\PaymentWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AbacatePayWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.abacatepay.webhook_secret' => 'test-secret']);
    }

    private function postWebhook(array $payload, ?string $secret = 'test-secret'): TestResponse
    {
        $url = '/webhooks/abacatepay'.($secret !== null ? '?webhookSecret='.$secret : '');

        return $this->postJson($url, $payload);
    }

    public function test_request_without_valid_secret_is_rejected(): void
    {
        $this->postWebhook(['id' => 'log_1', 'event' => 'checkout.completed', 'data' => []], secret: 'wrong-secret')
            ->assertForbidden();

        $this->assertDatabaseCount('payment_webhook_events', 0);
    }

    public function test_valid_request_logs_the_event(): void
    {
        $this->postWebhook(['id' => 'log_1', 'event' => 'checkout.completed', 'data' => []])
            ->assertOk();

        $this->assertDatabaseHas('payment_webhook_events', [
            'provider' => 'abacatepay',
            'external_id' => 'log_1',
            'event' => 'checkout.completed',
        ]);

        $this->assertNotNull(PaymentWebhookEvent::first()->processed_at);
    }

    public function test_duplicate_event_id_is_not_logged_twice(): void
    {
        $payload = ['id' => 'log_dup', 'event' => 'checkout.completed', 'data' => []];

        $this->postWebhook($payload)->assertOk();
        $this->postWebhook($payload)->assertOk();

        $this->assertDatabaseCount('payment_webhook_events', 1);
    }
}
```

- [ ] **Step 2: Run to confirm they fail**

Run: `php artisan test --filter=AbacatePayWebhookTest`
Expected: FAIL (route `webhooks/abacatepay` doesn't exist → 404s, not the expected statuses)

- [ ] **Step 3: Create the migration and model**

`database/migrations/2026_08_09_120100_create_payment_webhook_events_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('abacatepay');
            $table->string('external_id');
            $table->string('event');
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
    }
};
```

`app/Models/PaymentWebhookEvent.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentWebhookEvent extends Model
{
    protected $fillable = [
        'provider',
        'external_id',
        'event',
        'payload',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
```

- [ ] **Step 4: Add config and env entries**

In `config/services.php`, add before the closing `];`:

```php
    'abacatepay' => [
        'webhook_secret' => env('ABACATEPAY_WEBHOOK_SECRET'),
    ],
```

In `.env`, add a new line (any section):

```
ABACATEPAY_WEBHOOK_SECRET=
```

In `.env.example`, add the same line.

- [ ] **Step 5: Exempt the webhook route from CSRF**

In `bootstrap/app.php`, update the `withMiddleware` closure from Task 1 to also call `validateCsrfTokens`:

```php
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => \App\Http\Middleware\EnsureAccessIsActive::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);
    })
```

- [ ] **Step 6: Create the controller**

`app/Http/Controllers/Webhooks/AbacatePayWebhookController.php`:

```php
<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\PaymentWebhookEvent;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AbacatePayWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $expectedSecret = config('services.abacatepay.webhook_secret');

        if (! $expectedSecret || ! hash_equals($expectedSecret, (string) $request->query('webhookSecret'))) {
            abort(403);
        }

        $externalId = $request->input('id');
        $event = $request->input('event');

        if (! $externalId || ! $event) {
            abort(422);
        }

        $alreadyLogged = PaymentWebhookEvent::query()
            ->where('provider', 'abacatepay')
            ->where('external_id', $externalId)
            ->exists();

        if ($alreadyLogged) {
            return response()->json(['received' => true]);
        }

        $webhookEvent = PaymentWebhookEvent::create([
            'provider' => 'abacatepay',
            'external_id' => $externalId,
            'event' => $event,
            'payload' => $request->all(),
        ]);

        $webhookEvent->update(['processed_at' => now()]);

        return response()->json(['received' => true]);
    }
}
```

- [ ] **Step 7: Add the route**

In `routes/web.php`, add the import `use App\Http\Controllers\Webhooks\AbacatePayWebhookController;` and, before `require __DIR__.'/auth.php';`:

```php
Route::post('webhooks/abacatepay', AbacatePayWebhookController::class)
    ->name('webhooks.abacatepay');
```

(No `auth`/`verified`/`active` middleware — this is a server-to-server call with no Laravel session.)

- [ ] **Step 8: Run the tests to confirm they pass**

Run: `php artisan test --filter=AbacatePayWebhookTest`
Expected: PASS (all 3 tests)

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_08_09_120100_create_payment_webhook_events_table.php app/Models/PaymentWebhookEvent.php config/services.php .env .env.example bootstrap/app.php app/Http/Controllers/Webhooks/AbacatePayWebhookController.php routes/web.php tests/Feature/Webhooks/AbacatePayWebhookTest.php
git commit -m "Add AbacatePay webhook endpoint with secret check and idempotent event log"
```

---

### Task 3: Activate user on payment confirmation

**Files:**
- Create: `app/Actions/ActivateUserFromPayment.php`
- Modify: `app/Http/Controllers/Webhooks/AbacatePayWebhookController.php`
- Test: `tests/Feature/Webhooks/AbacatePayWebhookTest.php` (extend)

**Interfaces:**
- Produces: `ActivateUserFromPayment::handle(string $email, ?string $name): User` — creates the user (random password, `email_verified_at = now()`, sends `Password::sendResetLink()`) if none exists for `$email`, otherwise clears `access_revoked_at` if it was set.
- Consumes: `PaymentWebhookEvent` logging from Task 2 (this task hooks into the same controller, after the log-and-dedupe step).

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Webhooks/AbacatePayWebhookTest.php` — update the `use` imports at the top of the file to add:

```php
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
```

Add these test methods to the class:

```php
    public function test_checkout_completed_creates_a_new_user_and_sends_password_reset_link(): void
    {
        Notification::fake();

        $this->postWebhook([
            'id' => 'log_new_user',
            'event' => 'checkout.completed',
            'data' => ['customer' => ['email' => 'nova@example.com', 'name' => 'Nova Aluna']],
        ])->assertOk();

        $user = User::where('email', 'nova@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('Nova Aluna', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->access_revoked_at);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_subscription_renewed_reactivates_a_previously_revoked_user(): void
    {
        $user = User::factory()->create(['email' => 'volta@example.com', 'access_revoked_at' => now()]);

        $this->postWebhook([
            'id' => 'log_reactivate',
            'event' => 'subscription.renewed',
            'data' => ['customer' => ['email' => 'volta@example.com']],
        ])->assertOk();

        $this->assertNull($user->fresh()->access_revoked_at);
    }

    public function test_event_without_customer_email_is_logged_but_has_no_side_effect(): void
    {
        $this->postWebhook([
            'id' => 'log_no_customer',
            'event' => 'checkout.completed',
            'data' => ['customer' => null],
        ])->assertOk();

        $this->assertDatabaseCount('users', 0);
    }
```

- [ ] **Step 2: Run to confirm they fail**

Run: `php artisan test --filter=AbacatePayWebhookTest`
Expected: FAIL on the 3 new tests (no user is ever created — the controller doesn't call any action yet); the 3 tests from Task 2 still PASS

- [ ] **Step 3: Create the action**

`app/Actions/ActivateUserFromPayment.php`:

```php
<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ActivateUserFromPayment
{
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
}
```

- [ ] **Step 4: Wire it into the controller**

In `app/Http/Controllers/Webhooks/AbacatePayWebhookController.php`, add the import and the activate-event list, and dispatch after the event is logged:

```php
<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\ActivateUserFromPayment;
use App\Http\Controllers\Controller;
use App\Models\PaymentWebhookEvent;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AbacatePayWebhookController extends Controller
{
    private const ACTIVATE_EVENTS = [
        'checkout.completed',
        'transparent.completed',
        'subscription.completed',
        'subscription.renewed',
        'subscription.trial_started',
    ];

    public function __invoke(Request $request, ActivateUserFromPayment $activateUserFromPayment): Response
    {
        $expectedSecret = config('services.abacatepay.webhook_secret');

        if (! $expectedSecret || ! hash_equals($expectedSecret, (string) $request->query('webhookSecret'))) {
            abort(403);
        }

        $externalId = $request->input('id');
        $event = $request->input('event');

        if (! $externalId || ! $event) {
            abort(422);
        }

        $alreadyLogged = PaymentWebhookEvent::query()
            ->where('provider', 'abacatepay')
            ->where('external_id', $externalId)
            ->exists();

        if ($alreadyLogged) {
            return response()->json(['received' => true]);
        }

        $webhookEvent = PaymentWebhookEvent::create([
            'provider' => 'abacatepay',
            'external_id' => $externalId,
            'event' => $event,
            'payload' => $request->all(),
        ]);

        $email = $request->input('data.customer.email');

        if ($email && in_array($event, self::ACTIVATE_EVENTS, true)) {
            $activateUserFromPayment->handle($email, $request->input('data.customer.name'));
        }

        $webhookEvent->update(['processed_at' => now()]);

        return response()->json(['received' => true]);
    }
}
```

- [ ] **Step 5: Run the tests to confirm they pass**

Run: `php artisan test --filter=AbacatePayWebhookTest`
Expected: PASS (all 6 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Actions/ActivateUserFromPayment.php app/Http/Controllers/Webhooks/AbacatePayWebhookController.php tests/Feature/Webhooks/AbacatePayWebhookTest.php
git commit -m "Activate/create User on AbacatePay payment confirmation events"
```

---

### Task 4: Revoke access on refund/cancellation

**Files:**
- Create: `app/Actions/RevokeUserAccess.php`
- Modify: `app/Http/Controllers/Webhooks/AbacatePayWebhookController.php`
- Test: `tests/Feature/Webhooks/AbacatePayWebhookTest.php` (extend)

**Interfaces:**
- Produces: `RevokeUserAccess::handle(string $email): void` — sets `access_revoked_at = now()` on the matching `User` if one exists and isn't already revoked; no-op otherwise.
- Consumes: `AbacatePayWebhookController`'s dispatch block from Task 3 (adds the revoke-event branch next to the activate-event branch).

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Webhooks/AbacatePayWebhookTest.php`:

```php
    public function test_checkout_refunded_revokes_an_active_users_access(): void
    {
        $user = User::factory()->create(['email' => 'reembolsado@example.com']);

        $this->postWebhook([
            'id' => 'log_refund',
            'event' => 'checkout.refunded',
            'data' => ['customer' => ['email' => 'reembolsado@example.com']],
        ])->assertOk();

        $this->assertNotNull($user->fresh()->access_revoked_at);
    }

    public function test_refund_for_unknown_email_is_a_no_op(): void
    {
        $this->postWebhook([
            'id' => 'log_refund_unknown',
            'event' => 'checkout.refunded',
            'data' => ['customer' => ['email' => 'ninguem@example.com']],
        ])->assertOk();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_unknown_event_type_has_no_side_effect(): void
    {
        $user = User::factory()->create(['email' => 'saque@example.com']);

        $this->postWebhook([
            'id' => 'log_payout',
            'event' => 'payout.completed',
            'data' => ['customer' => ['email' => 'saque@example.com']],
        ])->assertOk();

        $this->assertNull($user->fresh()->access_revoked_at);
    }
```

- [ ] **Step 2: Run to confirm they fail**

Run: `php artisan test --filter=AbacatePayWebhookTest`
Expected: FAIL on `test_checkout_refunded_revokes_an_active_users_access` (nothing revokes access yet); the other two new tests PASS already (nothing happens for either case, which is what they assert) — that's fine, they're guarding against a regression in Step 4, not proving new behavior.

- [ ] **Step 3: Create the action**

`app/Actions/RevokeUserAccess.php`:

```php
<?php

namespace App\Actions;

use App\Models\User;

class RevokeUserAccess
{
    public function handle(string $email): void
    {
        User::where('email', $email)
            ->whereNull('access_revoked_at')
            ->update(['access_revoked_at' => now()]);
    }
}
```

- [ ] **Step 4: Wire it into the controller**

In `app/Http/Controllers/Webhooks/AbacatePayWebhookController.php`, add the import, the revoke-event list, and extend the dispatch:

```php
<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\ActivateUserFromPayment;
use App\Actions\RevokeUserAccess;
use App\Http\Controllers\Controller;
use App\Models\PaymentWebhookEvent;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AbacatePayWebhookController extends Controller
{
    private const ACTIVATE_EVENTS = [
        'checkout.completed',
        'transparent.completed',
        'subscription.completed',
        'subscription.renewed',
        'subscription.trial_started',
    ];

    private const REVOKE_EVENTS = [
        'checkout.refunded',
        'checkout.disputed',
        'checkout.lost',
        'transparent.refunded',
        'transparent.disputed',
        'transparent.lost',
        'subscription.cancelled',
    ];

    public function __invoke(
        Request $request,
        ActivateUserFromPayment $activateUserFromPayment,
        RevokeUserAccess $revokeUserAccess,
    ): Response {
        $expectedSecret = config('services.abacatepay.webhook_secret');

        if (! $expectedSecret || ! hash_equals($expectedSecret, (string) $request->query('webhookSecret'))) {
            abort(403);
        }

        $externalId = $request->input('id');
        $event = $request->input('event');

        if (! $externalId || ! $event) {
            abort(422);
        }

        $alreadyLogged = PaymentWebhookEvent::query()
            ->where('provider', 'abacatepay')
            ->where('external_id', $externalId)
            ->exists();

        if ($alreadyLogged) {
            return response()->json(['received' => true]);
        }

        $webhookEvent = PaymentWebhookEvent::create([
            'provider' => 'abacatepay',
            'external_id' => $externalId,
            'event' => $event,
            'payload' => $request->all(),
        ]);

        $email = $request->input('data.customer.email');

        if ($email) {
            if (in_array($event, self::ACTIVATE_EVENTS, true)) {
                $activateUserFromPayment->handle($email, $request->input('data.customer.name'));
            } elseif (in_array($event, self::REVOKE_EVENTS, true)) {
                $revokeUserAccess->handle($email);
            }
        }

        $webhookEvent->update(['processed_at' => now()]);

        return response()->json(['received' => true]);
    }
}
```

- [ ] **Step 5: Run the tests to confirm they pass**

Run: `php artisan test --filter=AbacatePayWebhookTest`
Expected: PASS (all 9 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Actions/RevokeUserAccess.php app/Http/Controllers/Webhooks/AbacatePayWebhookController.php tests/Feature/Webhooks/AbacatePayWebhookTest.php
git commit -m "Revoke User access on AbacatePay refund/dispute/cancellation events"
```

---

### Task 5: Full-suite verification

**Files:** none (verification only)

- [ ] **Step 1: Run the full automated test suite**

Run: `php artisan test`
Expected: PASS, 0 failures (should be 87 pre-existing + 2 from Task 1 + 9 from Tasks 2-4 = 98 tests; exact count isn't load-bearing, 0 failures is)

- [ ] **Step 2: Confirm spec coverage**

Check off each item from `docs/superpowers/specs/2026-08-09-abacatepay-webhook-design.md` against what was built:
- `users.access_revoked_at` + login/middleware enforcement → Task 1.
- `payment_webhook_events` audit log + idempotency → Task 2.
- Activate on payment confirmation (with password-reset email + auto-verified email) → Task 3.
- Revoke on refund/dispute/cancellation → Task 4.
- `payout.*`/`transfer.*`/unknown events are no-ops → Task 4's `test_unknown_event_type_has_no_side_effect`.

If any of these has no corresponding passing test, stop and add it before considering the plan done.

- [ ] **Step 3: No commit for this task** — it's verification only.
