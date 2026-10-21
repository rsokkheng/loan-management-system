<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Prepare a new installation: company settings, the chart of accounts and a head office branch.
     *
     * Safe for production and safe to run again: existing records are never overwritten.
     * It creates no users. Run `php artisan app:create-admin` afterwards for the first login.
     */
    public function run(): void
    {
        $missingSettings = array_diff_key(Setting::DEFAULTS, Setting::query()->pluck('value', 'key')->all());
        Setting::setMany($missingSettings);

        Account::installDefaults();

        Branch::query()->firstOrCreate(['code' => 'HQ'], ['name' => 'Head Office', 'is_active' => true]);

        $this->command?->info('Base data ready. Create the first administrator with: php artisan app:create-admin');
    }
}
