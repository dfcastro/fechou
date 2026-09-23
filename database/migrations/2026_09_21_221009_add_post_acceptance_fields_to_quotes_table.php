<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table
                ->string('payment_status', 20)
                ->nullable()
                ->after('accepted_at');

            $table
                ->timestamp('paid_at')
                ->nullable()
                ->after('payment_status');

            $table
                ->string('execution_status', 20)
                ->nullable()
                ->after('paid_at');

            $table
                ->timestamp('execution_started_at')
                ->nullable()
                ->after('execution_status');

            $table
                ->timestamp('completed_at')
                ->nullable()
                ->after('execution_started_at');
        });

        /*
         * Propostas antigas que já foram aceitas
         * entram no novo fluxo como pendentes.
         */
        DB::table('quotes')
            ->where('status', 'accepted')
            ->update([
                'payment_status' => 'pending',
                'execution_status' => 'pending',
            ]);
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn([
                'payment_status',
                'paid_at',
                'execution_status',
                'execution_started_at',
                'completed_at',
            ]);
        });
    }
};