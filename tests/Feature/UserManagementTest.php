<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_create_staff_accounts(): void
    {
        $this->actingAs(User::factory()->admin()->create())->post(route('users.store'), [
            'name' => 'New Officer',
            'email' => 'new@loanpro.test',
            'role' => 'loan_officer',
            'is_active' => '1',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertRedirect(route('users.index'));

        $user = User::query()->where('email', 'new@loanpro.test')->sole();
        $this->assertSame(UserRole::LoanOfficer, $user->role);
        $this->assertTrue($user->is_active);
    }

    public function test_managers_cannot_manage_users(): void
    {
        $this->actingAs(User::factory()->manager()->create())->get(route('users.index'))->assertForbidden();
    }

    public function test_admins_cannot_demote_or_delete_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'loan_officer',
            'is_active' => '0',
        ])->assertRedirect(route('users.index'));

        $this->assertSame(UserRole::Admin, $admin->fresh()->role);
        $this->assertTrue($admin->fresh()->is_active);

        $this->actingAs($admin)->delete(route('users.destroy', $admin))->assertSessionHas('error');
        $this->assertModelExists($admin);
    }

    public function test_settings_update_changes_the_currency_shown_in_the_app(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('settings.update'), [
            'company_name' => 'Acme Finance',
            'currency_code' => 'KHR',
            'currency_symbol' => '៛',
        ])->assertSessionHas('success');

        $this->assertSame('៛', Setting::get('currency_symbol'));
        $this->actingAs($admin)->get(route('dashboard'))->assertSee('Acme Finance')->assertSee('៛0.00');
    }

    public function test_users_can_change_their_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('profile.password'), [
            'current_password' => 'password',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertSessionHas('success');

        $this->assertTrue(password_verify('brand-new-password', $user->fresh()->password));
    }
}
