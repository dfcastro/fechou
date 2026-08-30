<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('quote_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('type', 30)->default('service');

            $table->string('description');

            $table->decimal('quantity', 12, 3)->default(1);

            $table->decimal('unit_price', 14, 2);

            $table->decimal('total', 14, 2);

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_items');
    }
};
