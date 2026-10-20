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
        Schema::create('loan_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->text('description')->nullable();
            $table->decimal('interest_rate', 8, 4)->comment('Annual interest rate in percent');
            $table->string('interest_method', 30);
            $table->string('repayment_frequency', 20);
            $table->decimal('min_amount', 15, 2);
            $table->decimal('max_amount', 15, 2);
            $table->unsignedSmallInteger('min_term')->comment('Number of installments');
            $table->unsignedSmallInteger('max_term')->comment('Number of installments');
            $table->decimal('processing_fee_percent', 5, 2)->default(0);
            $table->decimal('late_penalty_percent', 5, 2)->default(0)->comment('Percent of the overdue installment balance');
            $table->unsignedSmallInteger('grace_period_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_products');
    }
};
