<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use RuntimeException;

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
        // The log mailer writes verification and reset links into the log, and array drops every
        // email: production must have a real mailer, or it does not start. A failover or roundrobin
        // mailer is checked through its list of mailers; each name counts by its transport.
        $mailer = (string) config('mail.default');
        $mailers = [$mailer, ...(array) config("mail.mailers.$mailer.mailers", [])];
        $transports = array_map(fn ($name) => config("mail.mailers.$name.transport", $name), $mailers);
        if (app()->isProduction() && array_intersect($transports, ['log', 'array']) !== []) {
            throw new RuntimeException('MAIL_MAILER must be a real mailer in production, not log or array.');
        }

        // Outside production a relation that was not eager-loaded throws, so an N+1 query shows up
        // in development and in the tests instead of slowing a list down unnoticed.
        Model::preventLazyLoading(! app()->isProduction());

        RateLimiter::for('login', function (Request $request) {
            // Not a string (an array, say): validation refuses it after the limit is counted.
            $email = is_string($request->input('email')) ? Str::lower($request->input('email')) : '';

            // Both limits apply, each with its own key: per email and IP, and per IP across all emails
            // (password spraying; config/security.php).
            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute((int) config('security.login_per_ip_per_minute'))->by('ip:'.$request->ip()),
            ];
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
        // Social login redirect and callback (routes/web.php): per IP, generous for shared networks.
        RateLimiter::for('social', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });
        RateLimiter::for('reset-password', function (Request $request) {
            return Limit::perHour(5)->by($request->ip());
        });
        // Bug reports (README "Bug reports"): per IP, and per account when logged in.
        RateLimiter::for('bug-reports', function (Request $request) {
            return array_filter([
                Limit::perHour(5)->by('ip:'.$request->ip()),
                $request->user() ? Limit::perHour(5)->by('user:'.$request->user()->id) : null,
            ]);
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
