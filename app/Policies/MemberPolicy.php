<?php

namespace App\Policies;

use App\Models\Member;
use App\Models\User;

class MemberPolicy
{
    /**
     * Determine whether the user can view the member list.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view the given member.
     */
    public function view(User $user, Member $member): bool
    {
        return $user->isMainAdmin() || $user->branch_id === $member->branch_id;
    }

    /**
     * Determine whether the user can view the member's sensitive profile
     * details (contact information and birthdate) on the NFC profile.
     *
     * Member accounts may only view their own record; administrators follow
     * the normal territory rules.
     */
    public function viewSensitive(User $user, Member $member): bool
    {
        if ($user->isMember()) {
            return $user->member_id === $member->id;
        }

        return $this->view($user, $member);
    }

    /**
     * Determine whether the user can create members.
     */
    public function create(User $user): bool
    {
        return $user->isBranchAdmin() ? $user->branch_id !== null : $user->isMainAdmin();
    }

    /**
     * Determine whether the user can create a login account for the member.
     */
    public function createAccount(User $user, Member $member): bool
    {
        return $user->isMainAdmin()
            || ($user->isBranchAdmin() && $user->branch_id === $member->branch_id);
    }

    /**
     * Determine whether the user can delete the member's login account.
     */
    public function deleteAccount(User $user, Member $member): bool
    {
        return $this->createAccount($user, $member);
    }

    /**
     * Determine whether the user can update the given member.
     */
    public function update(User $user, Member $member): bool
    {
        return $this->view($user, $member);
    }

    /**
     * Determine whether the user can delete the given member.
     */
    public function delete(User $user, Member $member): bool
    {
        return $this->view($user, $member);
    }
}
