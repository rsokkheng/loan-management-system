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
        Schema::table('loan_products', function (Blueprint $table) {
            $table->boolean('requires_guarantor')->default(false)->after('grace_period_days');
            $table->decimal('min_collateral_coverage', 6, 2)->default(0)->after('requires_guarantor')
                ->comment('Collateral value required as a % of principal; 0 = no collateral needed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->dropColumn(['requires_guarantor', 'min_collateral_coverage']);
        });
    }
};
