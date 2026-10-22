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
        Schema::table('payments', function (Blueprint $table) {
            $table->boolean('is_settlement')->default(false)->after('penalty_amount');
            $table->decimal('settlement_fee', 15, 2)->default(0)->after('is_settlement');
            $table->decimal('interest_waived', 15, 2)->default(0)->after('settlement_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['is_settlement', 'settlement_fee', 'interest_waived']);
        });
    }
};
