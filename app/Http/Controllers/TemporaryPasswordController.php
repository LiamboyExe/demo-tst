<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TemporaryPasswordController extends Controller
{
    /**
     * Show the forced password change screen shown after first login with
     * an administrator-created temporary password.
     */
    public function edit(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user->must_change_password) {
            return redirect()->intended($this->defaultDestination($user));
        }

        return Inertia::render('auth/ChangeTemporaryPassword', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Replace the temporary password, clearing the change requirement.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->must_change_password) {
            throw ValidationException::withMessages([
                'password' => __('Your password has already been changed.'),
            ]);
        }

        $user->forceFill([
            'password' => $request->validated('password'),
            'must_change_password' => false,
        ])->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Password updated.'),
        ]);

        return redirect()->intended($this->defaultDestination($user));
    }

    /**
     * Where the member should end up once their password is set.
     */
    private function defaultDestination(User $user): string
    {
        if ($user->member !== null) {
            return route('profile.show', $user->member);
        }

        return route('dashboard');
    }
}
