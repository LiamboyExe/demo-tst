<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
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
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);

        Fortify::authenticateUsing(function (Request $request): ?User {
            $login = $request->input(Fortify::username());

            if (! is_string($login) || $login === '') {
                return null;
            }

            // If visitor inputs temporary username or pending email into the console login,
            // direct them to their NFC activation profile instead of a generic error.
            $pendingUser = User::query()
                ->where('temp_username', $login)
                ->orWhere(function ($query) use ($login) {
                    $query->where('email', $login)->whereNull('activated_at');
                })
                ->first();

            if ($pendingUser !== null && $pendingUser->isPendingActivation()) {
                $member = $pendingUser->member;
                $slug = $member?->profile_slug;
                $url = $slug ? route('activation.show', $slug) : null;

                $msg = $url
                    ? __('This account is pending activation. Please activate your account at :url using your temporary credentials.', ['url' => $url])
                    : __('This account is pending activation. Please activate it through your NFC profile.');

                throw ValidationException::withMessages([
                    Fortify::username() => $msg,
                ]);
            }

            $user = User::query()
                ->where('username', $login)
                ->orWhere('email', $login)
                ->first();

            // Accounts pending activation only sign in through the NFC
            // profile activation flow, never the console login.
            if ($user === null || $user->isPendingActivation()
                || ! Hash::check((string) $request->input('password'), $user->password)) {
                return null;
            }

            return $user;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(function (Request $request) {
            $this->captureIntendedDestination($request);

            return Inertia::render('auth/Login', [
                'canResetPassword' => Features::enabled(Features::resetPasswords()),
                'status' => $request->session()->get('status'),
            ]);
        });

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/Register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    /**
     * Remember where the visitor wanted to go so successful logins return
     * there (e.g. back to the NFC profile that asked for the login).
     *
     * Only plain same-application paths are accepted: anything carrying a
     * host, a scheme, or a scheme-relative prefix is rejected outright so
     * the login redirect can never be used as an open redirect.
     */
    private function captureIntendedDestination(Request $request): void
    {
        $intended = (string) $request->query('intended', '');

        if ($intended === '' || ! str_starts_with($intended, '/') || str_starts_with($intended, '//')) {
            return;
        }

        if (str_contains($intended, '\\') || parse_url($intended, PHP_URL_HOST) !== null) {
            return;
        }

        redirect()->setIntendedUrl($intended);
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}
