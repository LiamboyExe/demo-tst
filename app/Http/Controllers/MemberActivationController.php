<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MemberActivationController extends Controller
{
    /**
     * Step 1: show the temporary-credential form on the member's NFC
     * profile so they can begin activating their account.
     */
    public function show(Request $request, Member $member): Response|RedirectResponse
    {
        abort_unless($member->approved, 404);

        $account = $member->user()->first();

        if ($account === null || $account->isActivated()) {
            return redirect()->route('profile.show', $member->profile_slug);
        }

        if ($request->user() !== null) {
            return redirect()->route('profile.show', $member->profile_slug);
        }

        return Inertia::render('Profile/Activate', [
            'member' => [
                'full_name' => $member->full_name,
                'initials' => $member->initials,
                'profile_slug' => $member->profile_slug,
            ],
        ]);
    }

    /**
     * Step 1 submit: verify the temporary username and password against
     * this member's pending account.
     */
    public function verify(Request $request, Member $member): RedirectResponse
    {
        abort_unless($member->approved, 404);

        if ($request->user() !== null) {
            return redirect()->route('profile.show', $member->profile_slug);
        }

        $validated = $request->validate([
            'temp_username' => ['required', 'string', 'max:60'],
            'temp_password' => ['required', 'string', 'max:255'],
        ]);

        $account = $member->user()->first();

        // The credentials must belong to this exact member's pending
        // account, and the comparison is timing-safe.
        if ($account === null || $account->isActivated()
            || ! hash_equals(Str::lower((string) $account->temp_username), Str::lower($validated['temp_username']))
            || ! hash_equals((string) $account->temp_password, $validated['temp_password'])) {
            throw ValidationException::withMessages([
                'temp_username' => __('These temporary credentials are not valid for this member.'),
            ]);
        }

        $request->session()->put('activation_user_id', $account->id);

        return redirect()->route('activation.create', $member->profile_slug);
    }

    /**
     * Step 2: show the create-account form after the temporary credentials
     * have been verified in this session.
     */
    public function create(Request $request, Member $member): Response|RedirectResponse
    {
        abort_unless($member->approved, 404);

        if ($request->user() !== null || ! $this->verifiedInSession($request, $member)) {
            return redirect()->route('activation.show', $member->profile_slug);
        }

        return Inertia::render('Profile/ActivateAccount', [
            'member' => [
                'full_name' => $member->full_name,
                'initials' => $member->initials,
                'profile_slug' => $member->profile_slug,
            ],
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Step 2 submit: save the permanent username and password, retire the
     * temporary credentials, sign the member in and return them to their
     * NFC profile.
     */
    public function store(Request $request, Member $member): RedirectResponse
    {
        abort_unless($member->approved, 404);

        if ($request->user() !== null || ! $this->verifiedInSession($request, $member)) {
            return redirect()->route('activation.show', $member->profile_slug);
        }

        $account = $member->user()->first();

        if ($account === null || $account->isActivated()) {
            return redirect()->route('profile.show', $member->profile_slug);
        }

        $validated = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:60', Rule::unique('users', 'username')],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        if (hash_equals((string) $account->temp_password, $validated['password'])) {
            throw ValidationException::withMessages([
                'password' => __('The new password must be different from your temporary password.'),
            ]);
        }

        DB::transaction(function () use ($account, $validated): void {
            $account->forceFill([
                'username' => $validated['username'],
                'password' => $validated['password'],
                'temp_username' => null,
                'temp_password' => null,
                'activated_at' => now(),
            ])->save();
        });

        $request->session()->forget('activation_user_id');

        Auth::login($account);
        $request->session()->regenerate();

        return redirect()->route('profile.show', $member->profile_slug);
    }

    /**
     * Whether this session has verified the temporary credentials for this
     * exact member's pending account.
     */
    private function verifiedInSession(Request $request, Member $member): bool
    {
        $account = $member->user()->first();

        if ($account === null || $account->isActivated()) {
            return false;
        }

        return $request->session()->get('activation_user_id') === $account->id;
    }
}
