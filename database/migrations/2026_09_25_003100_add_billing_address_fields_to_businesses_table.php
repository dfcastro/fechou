<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string(
                'address_number',
                20
            )
                ->nullable()
                ->after('address');

            $table->string(
                'address_complement'
            )
                ->nullable()
                ->after('address_number');

            $table->string(
                'province'
            )
                ->nullable()
                ->after('address_complement');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'address_number',
                'address_complement',
                'province',
            ]);
        });
    }
};
