<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'gateway_webhook_events',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->string('provider', 30);

                $table
                    ->string(
                        'provider_event_id',
                        191
                    );

                $table
                    ->string('event_type', 80);

                $table->json('payload');

                $table
                    ->timestamp('processed_at')
                    ->nullable();

                $table->timestamps();

                $table->unique([
                    'provider',
                    'provider_event_id',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'gateway_webhook_events'
        );
    }
};
