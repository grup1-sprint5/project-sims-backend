<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class WalletTopupService
{
    /**
     * Credits a user's wallet once per Stripe checkout session.
     *
     * Returns true when the top-up is newly applied, false when already processed
     * or when input is invalid.
     */
    public function applyTopup(
        User $user,
        string $checkoutSessionId,
        ?string $paymentIntentId,
        int $amountCents,
        string $currency = 'eur'
    ): bool {
        $checkoutSessionId = trim($checkoutSessionId);
        if ($checkoutSessionId === '' || $amountCents <= 0) {
            return false;
        }

        return (bool) DB::transaction(function () use ($user, $checkoutSessionId, $paymentIntentId, $amountCents, $currency) {
            $alreadyProcessed = DB::table('wallet_topups')
                ->where('stripe_checkout_session_id', $checkoutSessionId)
                ->lockForUpdate()
                ->exists();

            if ($alreadyProcessed) {
                return false;
            }

            $freshUser = User::query()->lockForUpdate()->find($user->id);
            if (!$freshUser) {
                return false;
            }

            $topupAmount = round($amountCents / 100, 2);
            $freshUser->update([
                'wallet_balance' => round(((float) ($freshUser->wallet_balance ?? 0)) + $topupAmount, 2),
            ]);

            DB::table('wallet_topups')->insert([
                'user_id' => (int) $freshUser->id,
                'stripe_checkout_session_id' => $checkoutSessionId,
                'stripe_payment_intent_id' => $paymentIntentId,
                'amount' => $topupAmount,
                'currency' => strtolower(trim($currency)) ?: 'eur',
                'status' => 'completed',
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        });
    }
}
