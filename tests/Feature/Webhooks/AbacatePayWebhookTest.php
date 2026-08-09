<?php

namespace Tests\Feature\Webhooks;

use App\Models\PaymentWebhookEvent;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
}
