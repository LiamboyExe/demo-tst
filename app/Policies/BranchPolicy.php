<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    /**
     * Determine whether the user can view the branch list.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the given branch.
     */
    public function view(User $user, Branch $branch): bool
    {
        return $user->isMainAdmin() || $user->branch_id === $branch->id;
    }

    /**
     * Determine whether the user can create branches.
     */
    public function create(User $user): bool
    {
        return $user->isMainAdmin();
    }

    /**
     * Determine whether the user can update the given branch.
     */
    public function update(User $user, Branch $branch): bool
    {
        return $user->isMainAdmin();
    }

    /**
     * Determine whether the user can delete the given branch.
     */
    public function delete(User $user, Branch $branch): bool
    {
        return $user->isMainAdmin();
    }
}
