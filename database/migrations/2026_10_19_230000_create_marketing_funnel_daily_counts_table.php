<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_funnel_daily_counts', function (Blueprint $table) {
            $table->date('day');
            $table->string('event', 40);
            $table->unsignedBigInteger('total')->default(0);

            $table->primary(['day', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_funnel_daily_counts');
    }
};
