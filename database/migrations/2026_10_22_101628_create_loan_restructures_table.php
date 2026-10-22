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
        Schema::create('loan_restructures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->date('restructure_date');
            $table->decimal('principal_rescheduled', 15, 2);
            $table->decimal('interest_carried', 15, 2)->default(0);
            $table->decimal('penalty_carried', 15, 2)->default(0);
            $table->decimal('penalty_waived', 15, 2)->default(0);
            $table->decimal('previous_interest_rate', 8, 4);
            $table->decimal('new_interest_rate', 8, 4);
            $table->unsignedSmallInteger('previous_remaining_installments');
            $table->unsignedSmallInteger('new_term');
            $table->date('previous_maturity_date')->nullable();
            $table->date('new_maturity_date');
            $table->text('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->date('restructured_at')->nullable()->after('closed_at');
            $table->unsignedSmallInteger('restructure_count')->default(0)->after('restructured_at');
        });

        Schema::table('loan_installments', function (Blueprint $table) {
            $table->boolean('is_arrears')->default(false)->after('balance_after')
                ->comment('Overdue interest and penalty carried over by a restructure');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_installments', function (Blueprint $table) {
            $table->dropColumn('is_arrears');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['restructured_at', 'restructure_count']);
        });

        Schema::dropIfExists('loan_restructures');
    }
};
