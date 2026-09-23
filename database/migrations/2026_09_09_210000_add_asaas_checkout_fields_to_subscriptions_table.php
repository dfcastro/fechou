<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn(
                'subscriptions',
                'provider_checkout_id'
            )) {
                $table
                    ->string('provider_checkout_id')
                    ->nullable()
                    ->after('provider_subscription_id');
            }

            if (!Schema::hasColumn(
                'subscriptions',
                'provider_checkout_status'
            )) {
                $table
                    ->string(
                        'provider_checkout_status',
                        30
                    )
                    ->nullable()
                    ->after('provider_checkout_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn(
                'subscriptions',
                'provider_checkout_status'
            )) {
                $columns[] =
                    'provider_checkout_status';
            }

            if (Schema::hasColumn(
                'subscriptions',
                'provider_checkout_id'
            )) {
                $columns[] =
                    'provider_checkout_id';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
