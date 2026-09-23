<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table
                ->boolean('follow_up_enabled')
                ->default(true);

            $table
                ->unsignedTinyInteger('follow_up_sent_after_days')
                ->default(2);

            $table
                ->unsignedTinyInteger('follow_up_viewed_after_days')
                ->default(2);

            $table
                ->unsignedTinyInteger('follow_up_expiry_warning_days')
                ->default(1);

            $table
                ->unsignedSmallInteger('follow_up_cooldown_hours')
                ->default(24);
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'follow_up_enabled',
                'follow_up_sent_after_days',
                'follow_up_viewed_after_days',
                'follow_up_expiry_warning_days',
                'follow_up_cooldown_hours',
            ]);
        });
    }
};