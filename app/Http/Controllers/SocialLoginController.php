<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as ProviderUser;
use Throwable;

// Log in or sign up with Google or GitHub (README "Social login"). redirect and callback are full
// page visits on the API host (routes/web.php, so they always have a session for the OAuth state
// and PKCE verifier); pending and complete are called by the frontend (routes/api.php). The app
// only ever redirects to FRONTEND_URL paths it builds itself.
class SocialLoginController extends Controller
{
    public const PROVIDERS = ['google', 'github'];

    // The verified sign-in of a new email, waiting for the Terms on /auth/complete. Session only.
    private const PENDING = 'social_pending';

    public function redirect(string $provider)
    {
        return $this->driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider)
    {
        // The user said no on the provider's page, or the provider reported another error.
        if ($request->query('error') !== null) {
            $cancelled = $request->query('error') === 'access_denied';

            return $this->toLogin($cancelled ? 'social_cancelled' : 'social_failed', $provider, 'provider_error');
        }

        try {
            // Checks the state first (InvalidStateException), then swaps the code for a token.
            $providerUser = $this->driver($provider)->user();
        } catch (Throwable $e) {
            return $this->toLogin('social_failed', $provider, $e::class);
        }

        $providerId = (string) $providerUser->getId();
        $email = $this->verifiedEmail($provider, $providerUser);

        if ($providerId === '' || strlen($providerId) > 191) {
            return $this->toLogin('social_failed', $provider, 'bad_provider_id');
        }
        if ($email === null) {
            return $this->toLogin('social_email_unverified', $provider, 'email_unverified');
        }

        $account = SocialAccount::where('provider', $provider)->where('provider_user_id', $providerId)->first();
        $user = $account?->user ?? User::where('email', $email)->first();

        // New email: nothing is created until the user accepts the Terms on /auth/complete.
        if (! $user) {
            $request->session()->put(self::PENDING, [
                'provider' => $provider,
                'provider_user_id' => $providerId,
                'email' => $email,
                'name' => mb_substr(trim((string) ($providerUser->getName() ?: $providerUser->getNickname())), 0, 255),
                'expires_at' => now()->addMinutes(15)->getTimestamp(),
            ]);

            return redirect()->away($this->frontend('/auth/complete'));
        }

        // An account with this email whose owner never proved it is theirs: someone else may have
        // registered it first (pre-account takeover), so it is never linked.
        if (! $account && ! $user->hasVerifiedEmail()) {
            return $this->toLogin('social_email_exists');
        }

        // Like the password login: no session for a banned user.
        if ($user->banned_at !== null) {
            UserActivityLog::record($user, $user->email, 'login_blocked_banned', $request);

            return $this->toLogin('social_banned');
        }

        if (! $account) {
            try {
                SocialAccount::create(['user_id' => $user->id, 'provider' => $provider, 'provider_user_id' => $providerId]);
            } catch (UniqueConstraintViolationException $e) {
                // Linked meanwhile (a second tab), or this user already has another account of this provider.
                return $this->toLogin('social_failed', $provider, $e::class);
            }
        }

        UserActivityLog::record($user, $user->email, 'login', $request);
        $this->startSession($request, $user);

        return redirect()->away($this->frontend('/'));
    }

    // What /auth/complete shows: the provider's name and verified email. 404 when there is none.
    public function pending(Request $request)
    {
        $pending = $this->pendingRecord($request);

        if ($pending === null) {
            return response()->json(['message' => __('messages.social_expired')], 404);
        }

        return response()->json(['name' => $pending['name'], 'email' => $pending['email'], 'provider' => $pending['provider']]);
    }

    // Creates the account of a pending sign-in, like register: the Terms are required, the role is
    // always user, the email is verified (the provider did it) and there is no password.
    public function complete(Request $request)
    {
        if (! $request->hasSession()) {
            throw new TokenMismatchException('CSRF token mismatch.');
        }

        $pending = $this->pendingRecord($request);

        if ($pending === null) {
            return response()->json(['message' => __('messages.social_expired')], 410);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'accepted_terms' => ['bail', 'required', 'accepted', 'boolean'],
        ]);

        // Single use, also when the account cannot be made below.
        $request->session()->forget(self::PENDING);

        try {
            $user = DB::transaction(function () use ($data, $pending) {
                $user = new User(['name' => $data['name'], 'email' => $pending['email']]);
                $user->forceFill([
                    'email_verified_at' => now(),
                    'terms_accepted_at' => now(),
                    'terms_version' => config('legal.terms_version'),
                ])->save();
                SocialAccount::create([
                    'user_id' => $user->id,
                    'provider' => $pending['provider'],
                    'provider_user_id' => $pending['provider_user_id'],
                ]);

                return $user;
            });
        } catch (UniqueConstraintViolationException $e) {
            // The email or the provider account was taken meanwhile (another tab, or a register).
            // Starting again takes the right path of the callback.
            return response()->json(['message' => __('messages.social_expired')], 410);
        }

        $user->refresh();
        UserActivityLog::record($user, $user->email, 'register', $request);
        $this->startSession($request, $user);

        return response()->json(['user' => $user->toArray() + ['email_verified' => $user->hasVerifiedEmail()]], 201);
    }

    private function driver(string $provider): AbstractProvider
    {
        // PKCE (S256) and the state are kept in the session between redirect and callback.
        return Socialite::driver($provider)
            ->setScopes($provider === 'google' ? ['openid', 'email', 'profile'] : ['user:email'])
            ->enablePKCE();
    }

    // The provider's email, lower case, only if the provider says it is verified. Google:
    // email_verified in its userinfo. GitHub: with the user:email scope Socialite asks
    // /user/emails and returns only the primary email if it is verified, else null (README).
    private function verifiedEmail(string $provider, ProviderUser $providerUser): ?string
    {
        $email = $providerUser->getEmail();

        if ($provider === 'google' && ($providerUser->getRaw()['email_verified'] ?? null) !== true) {
            return null;
        }
        if (! is_string($email) || strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return Str::lower($email);
    }

    private function pendingRecord(Request $request): ?array
    {
        $pending = $request->hasSession() ? $request->session()->get(self::PENDING) : null;

        if (! is_array($pending) || $pending['expires_at'] < now()->getTimestamp()) {
            return null;
        }

        return $pending;
    }

    private function startSession(Request $request, User $user): void
    {
        $request->session()->forget(self::PENDING);
        Auth::guard('web')->login($user);
        // A new session id once logged in (session fixation).
        $request->session()->regenerate();
    }

    // Failures only log the provider and a class or code: never the email, a token or the
    // provider's answer.
    private function toLogin(string $error, ?string $provider = null, ?string $reason = null)
    {
        if ($reason !== null) {
            Log::warning('Social login failed', ['provider' => $provider, 'error' => $reason]);
        }

        return redirect()->away($this->frontend('/login?error='.$error));
    }

    private function frontend(string $path): string
    {
        return rtrim((string) config('app.frontend_url'), '/').$path;
    }
}
