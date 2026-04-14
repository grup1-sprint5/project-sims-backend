<?php

namespace App\Http\Controllers;

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

        $tenantId = $user->tenant_id;
        $successUrl = $validated['success_url'] ?? config('services.stripe.success_url');
        $cancelUrl = $validated['cancel_url'] ?? config('services.stripe.cancel_url');

        if (!$successUrl || !$cancelUrl) {
            return response()->json(['message' => 'Missing success/cancel URL for Stripe checkout.'], 500);
        }

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
}
