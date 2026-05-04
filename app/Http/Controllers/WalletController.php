<?php

namespace App\Http\Controllers;

use App\Services\WalletTopupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WalletController extends Controller
{
    public function balance(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'wallet_balance' => (float) ($user->wallet_balance ?? 0),
        ]);
    }

    public function createStripeCheckoutSession(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1|max:9999',
            'success_url' => 'nullable|url',
            'cancel_url' => 'nullable|url',
        ]);

        $secret = (string) config('services.stripe.secret');
        if ($secret === '') {
            return response()->json(['message' => 'Stripe is not configured.'], 500);
        }

        $amountCents = (int) round(((float) $validated['amount']) * 100);
        if ($amountCents <= 0) {
            return response()->json(['message' => 'Invalid top-up amount.'], 422);
        }

        $tenantId = function_exists('tenancy') && tenancy()->initialized
            ? (string) tenancy()->tenant()->id
            : '';
        $successUrl = $validated['success_url'] ?? config('services.stripe.success_url');
        $cancelUrl = $validated['cancel_url'] ?? config('services.stripe.cancel_url');

        if (!$successUrl || !$cancelUrl) {
            return response()->json(['message' => 'Missing success/cancel URL for Stripe checkout.'], 500);
        }

        if ($tenantId === '') {
            return response()->json(['message' => 'Tenant context missing.'], 500);
        }

        // Ensure Stripe sends back the checkout session id on redirect.
        $successUrl = $this->appendQueryParams((string) $successUrl, [
            'wallet' => 'success',
            'session_id' => '{CHECKOUT_SESSION_ID}',
        ]);

        $sessionResponse = Http::asForm()
            ->withBasicAuth($secret, '')
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'client_reference_id' => (string) $user->id,
                'metadata[payment_type]' => 'wallet_topup',
                'metadata[user_id]' => (string) $user->id,
                'metadata[tenant_id]' => (string) $tenantId,
                'metadata[amount_cents]' => (string) $amountCents,
                'line_items[0][price_data][currency]' => 'eur',
                'line_items[0][price_data][unit_amount]' => $amountCents,
                'line_items[0][price_data][product_data][name]' => 'Wallet top-up',
                'line_items[0][quantity]' => 1,
            ]);

        if ($sessionResponse->failed()) {
            return response()->json([
                'message' => 'Stripe checkout session could not be created.',
                'stripe_error' => $sessionResponse->json(),
            ], 502);
        }

        $payload = $sessionResponse->json();

        return response()->json([
            'message' => 'Stripe checkout session created.',
            'session_id' => $payload['id'] ?? null,
            'checkout_url' => $payload['url'] ?? null,
            'publishable_key' => config('services.stripe.key'),
        ]);
    }

    public function confirmStripeCheckoutSession(Request $request, WalletTopupService $walletTopupService)
    {
        $user = $request->user();

        $validated = $request->validate([
            'session_id' => ['required', 'string', 'max:255'],
        ]);

        $secret = (string) config('services.stripe.secret');
        if ($secret === '') {
            return response()->json(['message' => 'Stripe is not configured.'], 500);
        }

        $sessionId = trim((string) $validated['session_id']);
        $stripeResponse = Http::withBasicAuth($secret, '')
            ->get('https://api.stripe.com/v1/checkout/sessions/' . rawurlencode($sessionId), [
                'expand' => ['payment_intent'],
            ]);

        if ($stripeResponse->failed()) {
            return response()->json([
                'message' => 'Could not verify Stripe session.',
                'stripe_error' => $stripeResponse->json(),
            ], 502);
        }

        $session = $stripeResponse->json();
        $metadata = $session['metadata'] ?? [];
        $paymentType = (string) ($metadata['payment_type'] ?? '');
        $paid = (string) ($session['payment_status'] ?? '') === 'paid';
        $complete = (string) ($session['status'] ?? '') === 'complete';

        if (!$paid || !$complete || $paymentType !== 'wallet_topup') {
            return response()->json(['message' => 'Stripe session is not a completed wallet top-up.'], 422);
        }

        $sessionUserId = (int) ($metadata['user_id'] ?? $session['client_reference_id'] ?? 0);
        $sessionTenantId = (string) ($metadata['tenant_id'] ?? '');

        $currentTenantId = function_exists('tenancy') && tenancy()->initialized
            ? (string) tenancy()->tenant()->id
            : '';

        if ($sessionUserId !== (int) $user->id || $sessionTenantId !== $currentTenantId) {
            return response()->json(['message' => 'Stripe session does not belong to the current user.'], 403);
        }

        $amountCents = (int) ($session['amount_total'] ?? ($metadata['amount_cents'] ?? 0));
        if ($amountCents <= 0) {
            return response()->json(['message' => 'Invalid amount in Stripe session.'], 422);
        }

        $processed = $walletTopupService->applyTopup(
            $user,
            (string) ($session['id'] ?? $sessionId),
            isset($session['payment_intent']) && is_array($session['payment_intent'])
                ? (string) ($session['payment_intent']['id'] ?? null)
                : (is_string($session['payment_intent'] ?? null) ? (string) $session['payment_intent'] : null),
            $amountCents,
            (string) ($session['currency'] ?? 'eur')
        );

        $user->refresh();

        return response()->json([
            'message' => $processed ? 'Wallet top-up confirmed.' : 'Wallet top-up already processed.',
            'processed' => $processed,
            'wallet_balance' => (float) ($user->wallet_balance ?? 0),
        ]);
    }

    private function appendQueryParams(string $url, array $params): string
    {
        $parts = parse_url($url);
        if (!$parts || !isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        foreach ($params as $key => $value) {
            $query[$key] = $value;
        }

        $rebuilt = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $rebuilt .= ':' . $parts['port'];
        }
        $rebuilt .= $parts['path'] ?? '';
        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        if ($queryString !== '') {
            // Keep Stripe placeholder unescaped.
            $queryString = str_replace('%7BCHECKOUT_SESSION_ID%7D', '{CHECKOUT_SESSION_ID}', $queryString);
            $rebuilt .= '?' . $queryString;
        }
        if (isset($parts['fragment'])) {
            $rebuilt .= '#' . $parts['fragment'];
        }

        return $rebuilt;
    }
}
