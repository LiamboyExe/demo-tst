<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PublicProfileController extends Controller
{
    /**
     * Attributes on the member record that are only revealed after the
     * visitor authenticates as that member (or an authorized admin).
     *
     * @var array<int, string>
     */
    private const SENSITIVE_ATTRIBUTES = ['email', 'phone', 'address', 'birthdate', 'age'];

    /**
     * Show the public profile for an approved member.
     *
     * The NFC profile stays public, but sensitive contact details are only
     * sent to the browser when the authenticated account belongs to this
     * exact member (users.member_id) or an admin allowed to view them.
     */
    public function show(Request $request, Member $member): Response
    {
        abort_unless($member->approved, 404);

        $member->load([
            'branch',
            'hood',
            'nfcCard',
            'user' => fn ($query) => $query->select(['id', 'member_id', 'username', 'role', 'activated_at']),
        ]);

        $user = $request->user();

        $canViewSensitive = $user !== null
            && Gate::forUser($user)->allows('viewSensitive', $member);

        $sensitive = $canViewSensitive ? [
            'email' => $member->email,
            'phone' => $member->phone,
            'address' => $member->address,
            'birthdate' => $member->birthdate?->format('Y-m-d'),
            'age' => $member->age,
        ] : null;

        $member->makeHidden(self::SENSITIVE_ATTRIBUTES);

        return Inertia::render('Profile/Show', [
            'member' => $member,
            'canViewSensitive' => $canViewSensitive,
            'sensitive' => $sensitive,
            'isPendingActivation' => $member->user?->isPendingActivation() ?? false,
            'hasAccount' => $member->user !== null,
        ]);
    }
}
