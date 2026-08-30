<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table
                ->foreignId('root_quote_id')
                ->nullable()
                ->after('client_id')
                ->constrained('quotes')
                ->nullOnDelete();

            $table
                ->unsignedInteger('version')
                ->default(1)
                ->after('number');

            $table->index([
                'business_id',
                'root_quote_id',
                'version',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropIndex([
                'business_id',
                'root_quote_id',
                'version',
            ]);

            $table->dropConstrainedForeignId(
                'root_quote_id'
            );

            $table->dropColumn('version');
        });
    }
};
