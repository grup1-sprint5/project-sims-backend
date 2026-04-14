<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (!Schema::hasColumn('reservations', 'payment_provider')) {
                $table->string('payment_provider', 32)->nullable()->after('total_price');
            }
            if (!Schema::hasColumn('reservations', 'payment_status')) {
                $table->string('payment_status', 32)->default('unpaid')->after('payment_provider');
            }
            if (!Schema::hasColumn('reservations', 'stripe_checkout_session_id')) {
                $table->string('stripe_checkout_session_id')->nullable()->after('payment_status');
            }
            if (!Schema::hasColumn('reservations', 'stripe_payment_intent_id')) {
                $table->string('stripe_payment_intent_id')->nullable()->after('stripe_checkout_session_id');
            }
            if (!Schema::hasColumn('reservations', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('stripe_payment_intent_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $drop = [];

            foreach ([
                'payment_provider',
                'payment_status',
                'stripe_checkout_session_id',
                'stripe_payment_intent_id',
                'paid_at',
            ] as $column) {
                if (Schema::hasColumn('reservations', $column)) {
                    $drop[] = $column;
                }
            }

            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
