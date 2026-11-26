<?php

use App\Http\Middleware\CheckIdleTimeout;
use App\Http\Middleware\CheckOverdueBorrowings;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureMfaVerified;
use App\Http\Middleware\EnsureStepUpMfa;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'permission' => CheckPermission::class,
            'role' => CheckRole::class,
            'mfa' => EnsureMfaVerified::class,
            'stepup' => EnsureStepUpMfa::class,
        ]);

        $middleware->web(append: [
            SecurityHeaders::class,
            CheckIdleTimeout::class,
            CheckOverdueBorrowings::class,
        ]);

        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/dashboard');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
