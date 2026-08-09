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
