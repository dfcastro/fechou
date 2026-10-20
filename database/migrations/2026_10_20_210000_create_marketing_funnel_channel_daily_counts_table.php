<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kept separate from the existing table to preserve historic counts
        // without misleadingly assigning them to an acquisition channel.
        Schema::create('marketing_funnel_channel_daily_counts', function (Blueprint $table) {
            $table->date('day');
            $table->string('source', 20);
            $table->string('event', 40);
            $table->unsignedBigInteger('total')->default(0);

            $table->primary(['day', 'source', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_funnel_channel_daily_counts');
    }
};
