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
     * Archived members keep their NFC link working, but the page only says
     * that they are no longer an active member instead of returning a 404.
     *
     * The NFC profile stays public, but sensitive contact details are only
     * sent to the browser when the authenticated account belongs to this
     * exact member (users.member_id) or an admin allowed to view them.
     */
    public function show(Request $request, Member $profileMember): Response
    {
        if ($profileMember->trashed()) {
            return Inertia::render('Profile/Archived', [
                'member' => [
                    'full_name' => $profileMember->full_name,
                    'initials' => $profileMember->initials,
                    'avatar_url' => $profileMember->avatar_url,
                    'archived_on' => $profileMember->deleted_at?->format('Y-m-d'),
                ],
            ]);
        }

        abort_unless($profileMember->approved, 404);

        $profileMember->load([
            'branch',
            'hood',
            'nfcCard',
            'user' => fn ($query) => $query->select(['id', 'member_id', 'username', 'role', 'activated_at']),
        ]);

        $user = $request->user();

        $canViewSensitive = $user !== null
            && Gate::forUser($user)->allows('viewSensitive', $profileMember);

        $sensitive = $canViewSensitive ? [
            'email' => $profileMember->email,
            'phone' => $profileMember->phone,
            'address' => $profileMember->address,
            'birthdate' => $profileMember->birthdate?->format('Y-m-d'),
            'age' => $profileMember->age,
        ] : null;

        $profileMember->makeHidden(self::SENSITIVE_ATTRIBUTES);

        return Inertia::render('Profile/Show', [
            'member' => $profileMember,
            'canViewSensitive' => $canViewSensitive,
            'sensitive' => $sensitive,
            'isPendingActivation' => $profileMember->user?->isPendingActivation() ?? false,
            'hasAccount' => $profileMember->user !== null,
        ]);
    }
}
