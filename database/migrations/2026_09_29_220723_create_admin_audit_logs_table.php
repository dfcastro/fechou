<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'admin_audit_logs',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('admin_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('business_id')
                    ->nullable()
                    ->constrained('businesses')
                    ->nullOnDelete();

                $table->string('action')
                    ->index();

                $table->string('subject_type')
                    ->nullable();

                $table->unsignedBigInteger('subject_id')
                    ->nullable();

                $table->json('metadata')
                    ->nullable();

                $table->string('ip_address', 45)
                    ->nullable();

                $table->text('user_agent')
                    ->nullable();

                $table->timestamps();

                $table->index([
                    'subject_type',
                    'subject_id',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'admin_audit_logs'
        );
    }
};
