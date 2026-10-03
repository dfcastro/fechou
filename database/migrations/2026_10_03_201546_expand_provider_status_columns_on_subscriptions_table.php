<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table
                ->string(
                    'provider_checkout_status',
                    100
                )
                ->nullable()
                ->change();

            $table
                ->string(
                    'provider_payment_status',
                    100
                )
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table
                ->string(
                    'provider_checkout_status',
                    32
                )
                ->nullable()
                ->change();

            $table
                ->string(
                    'provider_payment_status',
                    32
                )
                ->nullable()
                ->change();
        });
    }
};
