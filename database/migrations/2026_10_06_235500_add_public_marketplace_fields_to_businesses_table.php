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
                ->boolean('public_profile_enabled')
                ->default(false)
                ->after('logo_path');

            $table
                ->string('public_slug')
                ->nullable()
                ->unique()
                ->after('public_profile_enabled');

            $table
                ->text('public_description')
                ->nullable()
                ->after('public_slug');

            $table
                ->text('public_services')
                ->nullable()
                ->after('public_description');

            $table->index('public_profile_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropIndex([
                'public_profile_enabled',
            ]);

            $table->dropUnique([
                'public_slug',
            ]);

            $table->dropColumn([
                'public_profile_enabled',
                'public_slug',
                'public_description',
                'public_services',
            ]);
        });
    }
};
