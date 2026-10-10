<?php

namespace App\Providers;

use App\Models\User;
use App\Services\StoreSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(StoreSettings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Outside production, fail loudly if code tries to mass-assign a guarded attribute.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // For admin screens that are not tied to one model (dashboard, inventory, reports, settings).
        Gate::define('admin', fn (User $user) => $user->isAdmin());

        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(
            Str::lower((string) $request->input('email')).'|'.$request->ip()
        ));

        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
    }
}
