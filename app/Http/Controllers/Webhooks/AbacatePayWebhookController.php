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
