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
        return true;
    }

    /**
     * Determine whether the user can view the given member.
     */
    public function view(User $user, Member $member): bool
    {
        return $user->isMainAdmin() || $user->branch_id === $member->branch_id;
    }

    /**
     * Determine whether the user can create members.
     */
    public function create(User $user): bool
    {
        return $user->isBranchAdmin() ? $user->branch_id !== null : $user->isMainAdmin();
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
