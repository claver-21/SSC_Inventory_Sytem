<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);
        Paginator::useTailwind();

        Blade::if('permission', function (string ...$permissions): bool {
            $user = auth()->user();

            return $user !== null && $user->hasAnyPermission($permissions);
        });

        Blade::if('role', function (string ...$roles): bool {
            $user = auth()->user();

            return $user !== null && $user->hasAnyRole($roles);
        });
    }
}
