<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table
                ->timestamp('onboarding_completed_at')
                ->nullable()
                ->after('pix_key');
        });

        /*
         * Empresas que já existiam antes deste onboarding
         * não devem ser obrigadas a passar pelo fluxo novo.
         */
        DB::table('businesses')
            ->whereNull('onboarding_completed_at')
            ->update([
                'onboarding_completed_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('onboarding_completed_at');
        });
    }
};
