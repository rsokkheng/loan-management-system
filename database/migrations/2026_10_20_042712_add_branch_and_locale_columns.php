<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables whose records belong to a branch.
     *
     * @var list<string>
     */
    private array $branchTables = ['users', 'borrowers', 'loans', 'payments'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->branchTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->default('en')->after('role');
        });

        $this->assignExistingRecordsToHeadOffice();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });

        foreach ($this->branchTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('branch_id');
            });
        }
    }

    /**
     * Existing installs keep working: everything created before branches existed moves to "Head Office".
     * Admin users stay unassigned so they can see every branch.
     */
    private function assignExistingRecordsToHeadOffice(): void
    {
        if (! DB::table('borrowers')->exists() && ! DB::table('users')->exists()) {
            return;
        }

        $branchId = DB::table('branches')->insertGetId([
            'name' => 'Head Office',
            'code' => 'HQ',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->where('role', '!=', 'admin')->update(['branch_id' => $branchId]);

        foreach (['borrowers', 'loans', 'payments'] as $tableName) {
            DB::table($tableName)->update(['branch_id' => $branchId]);
        }
    }
};
