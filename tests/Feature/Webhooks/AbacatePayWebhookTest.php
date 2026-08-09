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
