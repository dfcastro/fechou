<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'quote_templates',
            function (Blueprint $table) {

                $table->id();

                $table
                    ->foreignId('business_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->string('name');
                $table->string('title');

                $table
                    ->text('description')
                    ->nullable();

                $table
                    ->unsignedSmallInteger(
                        'validity_days'
                    )
                    ->default(7);

                $table
                    ->decimal(
                        'discount',
                        12,
                        2
                    )
                    ->default(0);

                $table
                    ->text('notes')
                    ->nullable();

                $table->timestamps();

                $table->index([
                    'business_id',
                    'name',
                ]);
            }
        );


        Schema::create(
            'quote_template_items',
            function (Blueprint $table) {

                $table->id();

                $table
                    ->foreignId(
                        'quote_template_id'
                    )
                    ->constrained(
                        'quote_templates'
                    )
                    ->cascadeOnDelete();

                $table->string(
                    'type',
                    20
                );

                $table->string(
                    'description'
                );

                $table->decimal(
                    'quantity',
                    10,
                    3
                );

                $table->string(
                    'unit',
                    20
                );

                $table->decimal(
                    'unit_price',
                    12,
                    2
                );

                $table
                    ->unsignedInteger(
                        'sort_order'
                    )
                    ->default(0);

                $table->timestamps();
            }
        );
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'quote_template_items'
        );

        Schema::dropIfExists(
            'quote_templates'
        );
    }
};
