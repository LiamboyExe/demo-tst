<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class MemberAccountController extends Controller
{
    /**
     * Issue (or reset) the member's temporary activation credentials.
     *
     * Only administrators may do this; territory admins are limited to
     * members inside their own territory by the policy. Accounts that have
     * already been activated keep their permanent credentials.
     */
    public function store(Request $request, Member $member): RedirectResponse
    {
        Gate::authorize('createAccount', $member);

        $account = $member->user()->first();

        if ($account !== null && $account->isActivated()) {
            throw ValidationException::withMessages([
                'username' => __(':name has already activated their account, so its credentials cannot be reset.', ['name' => $member->full_name]),
            ]);
        }

        if ($account === null && User::query()->where('email', $member->email)->exists()) {
            throw ValidationException::withMessages([
                'username' => __('The email address :email is already used by another account.', ['email' => $member->email]),
            ]);
        }

        DB::transaction(fn () => $member->issueTemporaryCredentials());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Temporary credentials issued for :name. Open their details to copy them.', ['name' => $member->full_name]),
        ]);

        return back();
    }

    /**
     * Reveal the member's pending temporary credentials so an authorized
     * administrator can hand them to the member.
     *
     * These are never included in listings; they are fetched on demand
     * here and disappear once the account is activated.
     */
    public function credentials(Request $request, Member $member): JsonResponse
    {
        Gate::authorize('viewTemporaryCredentials', $member);

        $account = $member->user()->first();

        if ($account === null || $account->isActivated() || blank($account->temp_username)) {
            abort(404);
        }

        return response()->json([
            'temp_username' => $account->temp_username,
            'temp_password' => $account->temp_password,
        ]);
    }

    /**
     * Remove the member's login account.
     */
    public function destroy(Request $request, Member $member): RedirectResponse
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
