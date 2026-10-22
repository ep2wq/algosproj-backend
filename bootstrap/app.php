<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsNotBanned;
use App\Http\Middleware\EnsureUserIsOwner;
use App\Http\Middleware\SetContentLanguage;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'owner' => EnsureUserIsOwner::class,
            'not-banned' => EnsureUserIsNotBanned::class,
        ]);
        // Cookie sessions for the frontend (README "Sessions"): requests from an origin in
        // sanctum.stateful get the session, encrypted cookies and the CSRF check.
        $middleware->statefulApi();
        // Every request answers in the Accept-Language language (ar or en), messages included.
        // Global, so it also runs for unknown routes and before route binding.
        $middleware->prepend(SetContentLanguage::class);
        // Check the ban and the role before route model binding, so a banned user or a non-admin gets
        // 403 for any id, never 404.
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureUserIsNotBanned::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureUserIsAdmin::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureUserIsOwner::class);
        // Test case text is stored exactly as sent: leading and trailing whitespace can matter.
        $middleware->trimStrings(except: ['input', 'expected_output']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // Laravel's own messages for these are English. In Arabic they are replaced; in English
        // the framework's text is kept as it was.
        $arabic = fn (Request $request) => $request->is('api/*') && app()->getLocale() === 'ar';
        $exceptions->render(fn (AuthenticationException $e, Request $request) => $arabic($request)
            ? response()->json(['message' => __('messages.unauthenticated')], 401) : null);
        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => $arabic($request)
            ? response()->json(['message' => __('messages.not_found')], 404) : null);
        $exceptions->render(fn (ThrottleRequestsException $e, Request $request) => $arabic($request)
            ? response()->json(['message' => __('messages.too_many_attempts')], 429, $e->getHeaders()) : null);
    })->create();
