<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Account;
use App\Models\Borrower;
use App\Models\Branch;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_seeder_creates_base_data_without_any_users(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('borrowers', 0);
        $this->assertDatabaseCount('loans', 0);
        $this->assertSame(count(Account::DEFAULT_CHART), Account::query()->count());
        $this->assertSame(['HQ'], Branch::query()->pluck('code')->all());
        $this->assertSame(Setting::DEFAULTS['company_name'], Setting::get('company_name'));
    }

    public function test_production_seeder_can_run_again_without_overwriting_changes(): void
    {
        $this->seed(DatabaseSeeder::class);
        Setting::setMany(['company_name' => 'Kampot Credit']);
        Branch::query()->where('code', 'HQ')->update(['name' => 'Kampot HQ']);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame('Kampot Credit', Setting::get('company_name'));
        $this->assertSame(['Kampot HQ'], Branch::query()->pluck('name')->all());
        $this->assertSame(count(Account::DEFAULT_CHART), Account::query()->count());
    }

    public function test_demo_seeder_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->seed(DemoSeeder::class);

        $this->assertDatabaseCount('users', 0);
        $this->assertSame(0, Borrower::query()->count());
    }

    public function test_create_admin_command_creates_an_administrator(): void
    {
        $this->artisan('app:create-admin', ['--name' => 'Sokha Chea', '--email' => 'sokha@example.com'])
            ->expectsQuestion('Password (at least 8 characters)', 'a-strong-password')
            ->expectsQuestion('Confirm password', 'a-strong-password')
            ->assertSuccessful();

        $admin = User::query()->where('email', 'sokha@example.com')->sole();
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertNull($admin->branch_id);
        $this->assertTrue(password_verify('a-strong-password', $admin->password));
    }

    public function test_create_admin_command_rejects_a_short_password(): void
    {
        $this->artisan('app:create-admin', ['--name' => 'Sokha Chea', '--email' => 'sokha@example.com'])
            ->expectsQuestion('Password (at least 8 characters)', 'short')
            ->expectsQuestion('Confirm password', 'short')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
