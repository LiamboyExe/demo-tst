<?php

namespace App\Http\Controllers;

use App\Enums\MembershipType;
use App\Enums\MemberStatus;
use App\Models\Branch;
use App\Models\Hood;
use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    /**
     * List members with search, branch/hood/status filters.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $branch = $request->integer('branch');
        $hood = $request->integer('hood');

        $members = Member::query()
            ->with(['branch', 'hood', 'nfcCard'])
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
            ->when(in_array($status, MemberStatus::values(), true), fn (Builder $query) => $query->where('status', $status))
            ->when($user->isMainAdmin() && $branch > 0, fn (Builder $query) => $query->where('branch_id', $branch))
            ->when($hood > 0, fn (Builder $query) => $query->where('hood_id', $hood))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $branches = $user->isMainAdmin()
            ? Branch::query()->with(['hoods' => fn ($q) => $q->orderBy('name')])->orderBy('name')->get(['id', 'name'])
            : Branch::query()->whereKey($user->branch_id)->with(['hoods' => fn ($q) => $q->orderBy('name')])->get(['id', 'name']);

        return Inertia::render('members/Index', [
            'members' => $members,
            'branches' => $branches,
            'filters' => [
                'q' => $search,
                'status' => $status,
                'branch' => $branch,
                'hood' => $hood,
            ],
            'isMainAdmin' => $user->isMainAdmin(),
            'membershipTypes' => MembershipType::values(),
            'statuses' => MemberStatus::values(),
        ]);
    }

    /**
     * Store a new member.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Member::class);

        $user = $request->user();

        if ($user->isBranchAdmin()) {
            $request->merge(['branch_id' => $user->branch_id]);
        }

        $validated = $request->validate($this->rules());

        $avatarPath = $request->hasFile('avatar')
            ? $request->file('avatar')->store('avatars', 'public')
            : null;

        $member = Member::create([
            ...Arr::except($validated, ['avatar', 'remove_avatar']),
            'avatar_path' => $avatarPath,
            'profile_slug' => $this->uniqueProfileSlug($validated['first_name'], $validated['last_name']),
            'approved' => $request->boolean('approved'),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name was added.', ['name' => $member->full_name]),
        ]);

        return back();
    }

    /**
     * Update the given member.
     */
    public function update(Request $request, Member $member): RedirectResponse
    {
        Gate::authorize('update', $member);

        if ($request->user()->isBranchAdmin()) {
            $request->merge(['branch_id' => $request->user()->branch_id]);
        }

        $validated = $request->validate($this->rules($member->id));
        $data = Arr::except($validated, ['avatar', 'remove_avatar']);

        if ($request->hasFile('avatar')) {
            $this->deleteAvatar($member);
            $data['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        } elseif ($request->boolean('remove_avatar')) {
            $this->deleteAvatar($member);
            $data['avatar_path'] = null;
        }

        $member->update([
            ...$data,
            'approved' => $request->boolean('approved'),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name was updated.', ['name' => $member->full_name]),
        ]);

        return back();
    }

    /**
     * Delete the given member.
     */
    public function destroy(Member $member): RedirectResponse
    {
        Gate::authorize('delete', $member);

        $this->deleteAvatar($member);

        $member->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Member deleted.'),
        ]);

        return back();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(?int $ignoreId = null): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'hood_id' => ['nullable', 'integer', 'exists:hoods,id'],
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'alias' => ['nullable', 'string', 'max:60'],
            'birthdate' => ['nullable', 'date', 'before_or_equal:today'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['sometimes', 'boolean'],
            'email' => $this->emailRules($ignoreId),
            'phone' => ['nullable', 'string', 'regex:/^09\d{9}$/', 'max:11', Rule::unique('members', 'phone')->ignore($ignoreId)],
            'address' => ['nullable', 'string', 'max:1000'],
            'membership_type' => ['required', Rule::enum(MembershipType::class)],
            'status' => ['required', Rule::enum(MemberStatus::class)],
            'join_date' => ['required', 'date'],
        ];
    }

    /**
     * Delete the member's stored avatar file, if one exists.
     */
    private function deleteAvatar(Member $member): void
    {
        if (filled($member->avatar_path)) {
            Storage::disk('public')->delete($member->avatar_path);
        }
    }

    /**
     * Email rules that skip the DNS lookup for the demo domain so the seeded
     * members can still be edited.
     *
     * @return array<int, mixed>
     */
    private function emailRules(?int $ignoreId): array
    {
        $email = mb_strtolower((string) request()->input('email', ''));
        $skipDns = app()->runningUnitTests() || str_ends_with($email, '@mail.demo');

        return [
            'required',
            'string',
            'max:100',
            $skipDns ? 'email' : 'email:rfc,dns',
            Rule::unique('members', 'email')->ignore($ignoreId),
        ];
    }

    private function uniqueProfileSlug(string $firstName, string $lastName): string
    {
        $base = Str::slug($firstName.' '.$lastName) ?: 'member';

        do {
            $slug = $base.'-'.Str::lower(Str::random(6));
        } while (Member::query()->where('profile_slug', $slug)->exists());

        return $slug;
    }
}
