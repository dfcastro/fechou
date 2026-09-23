<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'businesses',
            function (Blueprint $table) {

                $table
                    ->boolean(
                        'payment_collection_enabled'
                    )
                    ->default(false);

                $table
                    ->text(
                        'payment_instructions'
                    )
                    ->nullable();
            }
        );


        Schema::table(
            'quotes',
            function (Blueprint $table) {

                $table
                    ->boolean(
                        'payment_collection_enabled'
                    )
                    ->default(false);
            }
        );
    }


    public function down(): void
    {
        Schema::table(
            'quotes',
            function (Blueprint $table) {

                $table->dropColumn(
                    'payment_collection_enabled'
                );
            }
        );


        Schema::table(
            'businesses',
            function (Blueprint $table) {

                $table->dropColumn([
                    'payment_collection_enabled',
                    'payment_instructions',
                ]);
            }
        );
    }
};
