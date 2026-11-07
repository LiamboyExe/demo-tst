<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Inertia\Inertia;
use Inertia\Response;

class PublicProfileController extends Controller
{
    /**
     * Show the public profile for an approved member.
     */
    public function show(Member $member): Response
    {
        abort_unless($member->approved, 404);

        $member->load(['branch', 'hood', 'nfcCard']);

        return Inertia::render('Profile/Show', [
            'member' => $member,
        ]);
    }
}
