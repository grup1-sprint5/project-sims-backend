<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\WalletTopupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function __construct(private readonly WalletTopupService $walletTopupService)
    {
    }

    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('Stripe-Signature', '');
        $secret = (string) config('services.stripe.webhook_secret');

        if ($secret === '' || !$this->isValidSignature($payload, $signature, $secret)) {
            return response()->json(['message' => 'Invalid Stripe signature.'], 400);
        }

        $event = json_decode($payload, true);
        if (!is_array($event)) {
            return response()->json(['message' => 'Invalid payload.'], 400);
        }

        $type = $event['type'] ?? null;
        $object = $event['data']['object'] ?? [];
        $paymentType = (string) ($object['metadata']['payment_type'] ?? 'reservation');

        if (!in_array($type, ['checkout.session.completed', 'checkout.session.expired', 'checkout.session.async_payment_failed'], true)) {
            return response()->json(['received' => true]);
        }

        if ($paymentType === 'wallet_topup') {
            return $this->handleWalletTopupEvent($type, $object);
        }

        $reservationId = (int) ($object['metadata']['reservation_id'] ?? $object['client_reference_id'] ?? 0);
        $tenantId = (string) ($object['metadata']['tenant_id'] ?? '');

        if ($reservationId <= 0 || $tenantId === '') {
            Log::warning('Stripe webhook missing reservation or tenant metadata.', [
                'event_type' => $type,
                'session_id' => $object['id'] ?? null,
            ]);
            return response()->json(['received' => true]);
        }

        $tenant = Tenant::query()->where('id', $tenantId)->first();
        if (!$tenant) {
            Log::warning('Stripe webhook tenant not found.', ['tenant_id' => $tenantId]);
            return response()->json(['received' => true]);
        }

        tenancy()->initialize($tenant);

        $reservation = Reservation::query()->find($reservationId);
        if (!$reservation) {
            tenancy()->end();
            return response()->json(['received' => true]);
        }

        if ($type === 'checkout.session.completed') {
            $reservation->update([
                'payment_provider' => 'stripe',
                'payment_status' => 'paid',
                'stripe_checkout_session_id' => $object['id'] ?? $reservation->stripe_checkout_session_id,
                'stripe_payment_intent_id' => $object['payment_intent'] ?? $reservation->stripe_payment_intent_id,
                'paid_at' => now(),
            ]);
        }

        if (in_array($type, ['checkout.session.expired', 'checkout.session.async_payment_failed'], true)) {
            $reservation->update([
                'payment_provider' => 'stripe',
                'payment_status' => 'failed',
                'stripe_checkout_session_id' => $object['id'] ?? $reservation->stripe_checkout_session_id,
                'stripe_payment_intent_id' => $object['payment_intent'] ?? $reservation->stripe_payment_intent_id,
            ]);
        }

        tenancy()->end();

        return response()->json(['received' => true]);
    }

    private function handleWalletTopupEvent(string $type, array $object)
    {
        if ($type !== 'checkout.session.completed') {
            return response()->json(['received' => true]);
        }

        $tenantId = (string) ($object['metadata']['tenant_id'] ?? '');
        $userId = (int) ($object['metadata']['user_id'] ?? $object['client_reference_id'] ?? 0);
        $amountCents = (int) ($object['amount_total'] ?? ($object['metadata']['amount_cents'] ?? 0));

        if ($tenantId === '' || $userId <= 0 || $amountCents <= 0) {
            Log::warning('Stripe wallet top-up webhook missing metadata.', [
                'session_id' => $object['id'] ?? null,
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'amount_cents' => $amountCents,
            ]);
            return response()->json(['received' => true]);
        }

        $tenant = Tenant::query()->where('id', $tenantId)->first();
        if (!$tenant) {
            Log::warning('Stripe wallet top-up tenant not found.', ['tenant_id' => $tenantId]);
            return response()->json(['received' => true]);
        }

        tenancy()->initialize($tenant);

        $user = User::query()->find($userId);
        if (!$user) {
            tenancy()->end();
            Log::warning('Stripe wallet top-up user not found.', ['user_id' => $userId]);
            return response()->json(['received' => true]);
        }

        $this->walletTopupService->applyTopup(
            $user,
            (string) ($object['id'] ?? ''),
            is_string($object['payment_intent'] ?? null) ? (string) $object['payment_intent'] : null,
            $amountCents,
            (string) ($object['currency'] ?? 'eur')
        );

        tenancy()->end();

        return response()->json(['received' => true]);
    }

    private function isValidSignature(string $payload, string $signatureHeader, string $secret): bool
    {
        if ($signatureHeader === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $chunk) {
            [$k, $v] = array_pad(explode('=', trim($chunk), 2), 2, null);
            if ($k && $v) {
                $parts[$k][] = $v;
            }
        }

        $timestamp = isset($parts['t'][0]) ? (int) $parts['t'][0] : null;
        $signatures = $parts['v1'] ?? [];

        if (!$timestamp || $signatures === []) {
            return false;
        }

        if (abs(time() - $timestamp) > 300) {
            return false;
        }

        $signedPayload = $timestamp . '.' . $payload;
        $expected = hash_hmac('sha256', $signedPayload, $secret);

        foreach ($signatures as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }
}
