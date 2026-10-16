<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsOwner;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['admin' => EnsureUserIsAdmin::class, 'owner' => EnsureUserIsOwner::class]);
        // Check the role before route model binding, so a non-admin gets 403 for any id, never 404.
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureUserIsAdmin::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureUserIsOwner::class);
        // Test case text is stored exactly as sent: leading and trailing whitespace can matter.
        $middleware->trimStrings(except: ['input', 'expected_output']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
