<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MemberArchiveController extends Controller
{
    /**
     * List archived members with search.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $search = trim((string) $request->query('q', ''));

        $members = Member::onlyTrashed()
            ->with(['branch', 'hood'])
            ->visibleTo($user)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $like = '%'.$search.'%';
                    $query->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('alias', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->latest('deleted_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('members/Archived', [
            'members' => $members,
            'filters' => [
                'q' => $search,
            ],
        ]);
    }

    /**
     * Restore an archived member to the members list.
     */
    public function restore(Member $archivedMember): RedirectResponse
    {
        Gate::authorize('restore', $archivedMember);

        $archivedMember->restore();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name was restored to the members list.', ['name' => $archivedMember->full_name]),
        ]);

        return back();
    }

    /**
     * Permanently delete an archived member.
     */
    public function destroy(Member $archivedMember): RedirectResponse
    {
        Gate::authorize('forceDelete', $archivedMember);

        $archivedMember->deleteAvatarFile();
        $archivedMember->forceDelete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Member permanently deleted.'),
        ]);

        return back();
    }
}
