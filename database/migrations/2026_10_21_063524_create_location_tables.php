<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cambodian administrative divisions: province → district → commune → village.
 *
 * The real data is imported separately (gazetteer dump). Tables are only created when they
 * don't exist yet, using the same columns as the gazetteer, so an existing import is never touched.
 */
return new class extends Migration
{
    /**
     * Table name => parent foreign key column.
     *
     * @var array<string, string|null>
     */
    private array $levels = [
        'provinces' => null,
        'districts' => 'province_id',
        'communes' => 'district_id',
        'villages' => 'commune_id',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->levels as $tableName => $parentColumn) {
            if (Schema::hasTable($tableName)) {
                continue;
            }

            Schema::create($tableName, function (Blueprint $table) use ($parentColumn) {
                $table->increments('id');
                $table->unsignedInteger('ref_id')->nullable()->comment('Reference to the original record');
                $table->string('code', 77)->nullable()->index();
                $table->unsignedMediumInteger('label')->nullable();
                $table->tinyInteger('prefix')->nullable();
                $table->string('name_kh')->nullable()->index();
                $table->string('name_en')->nullable()->index();

                if ($parentColumn) {
                    $table->unsignedInteger($parentColumn)->index();
                }

                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->unsignedTinyInteger('record_status_id')->default(1)->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * Reference data is imported outside the app, so rolling back never drops it.
     */
    public function down(): void
    {
        //
    }
};
