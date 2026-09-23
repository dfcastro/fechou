<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('plan_id')
                ->constrained()
                ->restrictOnDelete();

            /*
             * trialing
             * active
             * past_due
             * canceled
             * expired
             */
            $table->string('status')
                ->default('active');

            $table->timestamp('starts_at')
                ->nullable();

            $table->timestamp('trial_ends_at')
                ->nullable();

            $table->timestamp('current_period_starts_at')
                ->nullable();

            $table->timestamp('current_period_ends_at')
                ->nullable();

            $table->timestamp('canceled_at')
                ->nullable();

            $table->timestamp('ends_at')
                ->nullable();

            /*
             * Campos do gateway.
             *
             * Ficam genéricos para não amarrarmos
             * o Fechou a Stripe/Mercado Pago/etc.
             */
            $table->string('payment_provider')
                ->nullable();

            $table->string('provider_customer_id')
                ->nullable();

            $table->string('provider_subscription_id')
                ->nullable();

            $table->timestamps();

            $table->index([
                'business_id',
                'status',
            ]);

            $table->index(
                'provider_customer_id'
            );

            $table->unique([
                'payment_provider',
                'provider_subscription_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};