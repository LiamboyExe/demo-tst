<?php

namespace App\Policies;

use App\Models\NfcCard;
use App\Models\User;

class NfcCardPolicy
{
    /**
     * Determine whether the user can view the card list.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the given card.
     */
    public function view(User $user, NfcCard $card): bool
    {
        return $user->isMainAdmin() || $user->branch_id === $card->member?->branch_id;
    }

    /**
     * Determine whether the user can assign cards.
     */
    public function create(User $user): bool
    {
        return $user->isMainAdmin() || $user->branch_id !== null;
    }

    /**
     * Determine whether the user can update the given card.
     */
    public function update(User $user, NfcCard $card): bool
    {
        return $this->view($user, $card);
    }

    /**
     * Determine whether the user can delete the given card.
     */
    public function delete(User $user, NfcCard $card): bool
    {
        return $this->view($user, $card);
    }
}
