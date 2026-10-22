<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['loan_products', 'loans', 'journal_entries'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('currency', 3)->default('USD')->after('id')->index();
            });
        }

        $existing = DB::table('settings')->where('key', 'currency_code')->value('value');

        if ($existing === 'KHR') {
            foreach (['loan_products', 'loans', 'journal_entries'] as $table) {
                DB::table($table)->update(['currency' => 'KHR']);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['loan_products', 'loans', 'journal_entries'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropIndex(['currency']);
                $table->dropColumn('currency');
            });
        }
    }
};
