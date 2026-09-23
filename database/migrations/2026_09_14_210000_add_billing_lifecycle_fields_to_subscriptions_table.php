<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'subscriptions',
            function (Blueprint $table): void {
                $table
                    ->string('billing_status', 40)
                    ->nullable()
                    ->after('payment_provider');

                $table
                    ->string('provider_payment_id')
                    ->nullable()
                    ->after('provider_checkout_status');

                $table
                    ->string('provider_payment_status', 40)
                    ->nullable()
                    ->after('provider_payment_id');

                $table
                    ->timestamp('past_due_at')
                    ->nullable()
                    ->after('provider_payment_status');

                $table
                    ->timestamp('grace_ends_at')
                    ->nullable()
                    ->after('past_due_at');

                $table
                    ->timestamp('access_suspended_at')
                    ->nullable()
                    ->after('grace_ends_at');

                $table
                    ->timestamp('last_payment_confirmed_at')
                    ->nullable()
                    ->after('access_suspended_at');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'subscriptions',
            function (Blueprint $table): void {
                $table->dropColumn([
                    'billing_status',
                    'provider_payment_id',
                    'provider_payment_status',
                    'past_due_at',
                    'grace_ends_at',
                    'access_suspended_at',
                    'last_payment_confirmed_at',
                ]);
            }
        );
    }
};
