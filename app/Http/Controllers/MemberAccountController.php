<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class MemberAccountController extends Controller
{
    /**
     * Create a login account for an existing member.
     *
     * Only administrators may create member accounts; territory admins are
     * limited to members inside their own territory by the policy.
     */
    public function store(Request $request, Member $member): RedirectResponse
    {
        Gate::authorize('createAccount', $member);

        if ($member->user()->exists()) {
            throw ValidationException::withMessages([
                'username' => __(':name already has an account.', ['name' => $member->full_name]),
            ]);
        }

        if (User::query()->where('email', $member->email)->exists()) {
            throw ValidationException::withMessages([
                'username' => __('The email address :email is already used by another account.', ['email' => $member->email]),
            ]);
        }

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:60', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $member->user()->create([
            'name' => $member->full_name,
            'username' => $validated['username'],
            'email' => $member->email,
            'password' => $validated['password'],
            'role' => UserRole::Member,
            'must_change_password' => true,
            'email_verified_at' => now(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Account created for :name. They must change their temporary password on first login.', ['name' => $member->full_name]),
        ]);

        return back();
    }

    /**
     * Remove the member's login account.
     */
    public function destroy(Member $member): RedirectResponse
    {
        Gate::authorize('deleteAccount', $member);

        $account = $member->user()->first();

        if ($account === null) {
            throw ValidationException::withMessages([
                'username' => __(':name does not have an account.', ['name' => $member->full_name]),
            ]);
        }

        $account->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Account removed for :name.', ['name' => $member->full_name]),
        ]);

        return back();
    }
}
