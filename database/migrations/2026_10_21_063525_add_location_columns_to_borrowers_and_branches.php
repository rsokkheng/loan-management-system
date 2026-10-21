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
        foreach (['borrowers', 'branches'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedInteger('province_id')->nullable()->after('address')->index();
                $table->unsignedInteger('district_id')->nullable()->after('province_id')->index();
                $table->unsignedInteger('commune_id')->nullable()->after('district_id')->index();
                $table->unsignedInteger('village_id')->nullable()->after('commune_id')->index();
            });
        }

        // "address" now holds only the house number and street; the rest comes from the location columns.
        Schema::table('borrowers', function (Blueprint $table) {
            $table->string('address')->nullable()->change();
            $table->dropColumn('city');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('borrowers', function (Blueprint $table) {
            $table->string('city')->nullable()->after('address');
        });

        foreach (['borrowers', 'branches'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach (['province_id', 'district_id', 'commune_id', 'village_id'] as $column) {
                    $table->dropIndex("{$tableName}_{$column}_index");
                }
                $table->dropColumn(['province_id', 'district_id', 'commune_id', 'village_id']);
            });
        }
    }
};
