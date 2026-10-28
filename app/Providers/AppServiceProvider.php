<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(Str::lower((string) $request->input('email')).'|'.$request->ip());
        });
        // Per IP only. A plain throttle:5,1 would key on the logged-in user, and register logs the
        // new user in, so each new account would start a fresh count.
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
        // Email (README "Email"): both limits apply, each with its own key.
        RateLimiter::for('verification-email', function (Request $request) {
            return [
                Limit::perHour(3)->by('user:'.$request->user()?->id),
                Limit::perHour(10)->by('ip:'.$request->ip()),
            ];
        });
        RateLimiter::for('forgot-password', function (Request $request) {
            // Not a string (an array, say): validation refuses it after the limit is counted.
            $email = is_string($request->input('email')) ? Str::lower($request->input('email')) : '';

            return [
                Limit::perHour(3)->by('email:'.$email.'|'.$request->ip()),
                Limit::perHour(10)->by('ip:'.$request->ip()),
            ];
        });
        RateLimiter::for('reset-password', function (Request $request) {
            return Limit::perHour(5)->by($request->ip());
        });

        // The verification link is the API's signed route (60 minutes), which redirects to the
        // frontend. The reset link goes straight to the frontend's form.
        VerifyEmail::createUrlUsing(fn (User $user) => URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())],
        ));
        $resetUrl = fn (User $user, string $token) => rtrim((string) config('app.frontend_url'), '/')
            .'/reset-password?'.http_build_query(['token' => $token, 'email' => $user->getEmailForPasswordReset()]);
        ResetPassword::createUrlUsing($resetUrl);

        // Plain transactional mail in the request's language (lang/*/mail.php, resources/views/mail).
        VerifyEmail::toMailUsing(fn (User $user, string $url) => $this->accountMail($user, 'verify', $url));
        ResetPassword::toMailUsing(fn (User $user, string $token) => $this->accountMail($user, 'reset', $resetUrl($user, $token)));
    }

    // Runs while the notification's locale is active, so __() gives the right language.
    private function accountMail(User $user, string $kind, string $url): MailMessage
    {
        return (new MailMessage)
            ->subject(__("mail.{$kind}_subject"))
            ->view(['html' => 'mail.account', 'text' => 'mail.account-text'], [
                'locale' => app()->getLocale(),
                'name' => $user->name,
                'intro' => __("mail.{$kind}_intro"),
                'button' => __("mail.{$kind}_button"),
                'url' => $url,
                'ignore' => __("mail.{$kind}_ignore"),
            ]);
    }
}
