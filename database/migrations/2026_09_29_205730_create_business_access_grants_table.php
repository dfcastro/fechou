<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_access_grants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('plan_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('type', 30)
                ->default('courtesy');

            $table->timestamp('starts_at')
                ->nullable();

            $table->timestamp('ends_at');

            $table->text('reason')
                ->nullable();

            $table->foreignId('granted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('revoked_at')
                ->nullable();

            $table->foreignId('revoked_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index([
                'business_id',
                'ends_at',
                'revoked_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_access_grants');
    }
};