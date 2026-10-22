<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Setting;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\CurrencyContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(BranchContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        Paginator::useBootstrapFive();

        $this->defineGates();

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));

        Blade::directive('money', fn (string $expression) => "<?php echo e(\\App\\Enums\\Currency::display({$expression})); ?>");

        View::composer('*', function ($view): void {
            $view->with('appSettings', Setting::allValues());
            $view->with('reportingCurrency', app(CurrencyContext::class)->current());
        });

        View::composer(['components.layouts.admin', 'accounting.*', 'borrowers.*', 'loans.*', 'dashboard'], function ($view): void {
            $context = app(BranchContext::class);

            $view->with([
                'currentBranch' => $context->current(),
                'canSwitchBranch' => $context->canSwitch(),
            ]);
        });

        View::composer('components.layouts.admin', function ($view): void {
            $view->with('branchOptions', app(BranchContext::class)->canSwitch()
                ? Branch::query()->active()->orderBy('name')->pluck('name', 'id')
                : collect());
        });
    }

    /**
     * Role-based abilities used across routes, controllers and views.
     */
    private function defineGates(): void
    {
        $abilities = [
            'manage-users' => [UserRole::Admin],
            'manage-settings' => [UserRole::Admin],
            'view-activity' => [UserRole::Admin],
            'write-off-loans' => [UserRole::Admin],
            'manage-products' => [UserRole::Admin, UserRole::Manager],
            'approve-loans' => [UserRole::Admin, UserRole::Manager],
            'disburse-loans' => [UserRole::Admin, UserRole::Manager],
            'manage-branches' => [UserRole::Admin],
            'view-reports' => [UserRole::Admin, UserRole::Manager, UserRole::Accountant],
            'manage-accounting' => [UserRole::Admin, UserRole::Manager, UserRole::Accountant],
            'delete-records' => [UserRole::Admin, UserRole::Manager],
        ];

        foreach ($abilities as $ability => $roles) {
            Gate::define($ability, fn (User $user) => $user->hasRole(...$roles));
        }
    }
}
