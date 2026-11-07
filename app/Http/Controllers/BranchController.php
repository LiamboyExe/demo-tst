<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    /**
     * List branches with member statistics.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $branches = Branch::query()
            ->when(! $user->isMainAdmin(), fn (Builder $query) => $query->whereKey($user->branch_id))
            ->withCount([
                'members',
                'members as active_members_count' => fn (Builder $query) => $query->where('status', MemberStatus::Active),
                'members as inactive_members_count' => fn (Builder $query) => $query->where('status', MemberStatus::Inactive),
                'members as pending_members_count' => fn (Builder $query) => $query->where('status', MemberStatus::Pending),
                'admins as local_admins_count' => fn (Builder $query) => $query->where('role', UserRole::Local),
                'hoods',
            ])
            ->with([
                'admins' => fn ($query) => $query->where('role', UserRole::Local)->select('id', 'name', 'username', 'branch_id'),
                'hoods' => fn ($query) => $query->withCount('members')->orderBy('name'),
            ])
            ->orderBy('name')
            ->get();

        return Inertia::render('branches/Index', [
            'branches' => $branches,
            'isMainAdmin' => $user->isMainAdmin(),
        ]);
    }

    /**
     * Store a new branch together with its branch admin account.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Branch::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'location' => ['required', 'string', 'max:150'],
            'manager' => ['required', 'string', 'max:100'],
            'phone' => [
                'required',
                'string',
                'max:30',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (str_starts_with((string) $value, '09')) {
                        $digits = preg_replace('/\D/', '', (string) $value);
                        if (strlen((string) $value) > 11 || strlen($digits) !== 11) {
                            $fail('Mobile numbers starting with 09 must be exactly 11 digits and cannot exceed 11 digits.');
                        }
                    }
                },
                'regex:/^(09\d{9}|\+639\d{9}|\(0\d{1,2}\)\s?\d{3,4}[-\s]?\d{4}|0\d{1,2}[-\s]?\d{3,4}[-\s]?\d{4}|\d{4}[-\s]?\d{4})$/',
            ],
            'email' => [
                'required',
                'string',
                app()->runningUnitTests() ? 'email' : 'email:rfc,dns',
                'max:100',
            ],
            'admin_username' => ['required', 'string', 'max:60', 'unique:users,username'],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
        ], $this->messages());

        $branch = DB::transaction(function () use ($validated): Branch {
            $branch = Branch::create([
                'name' => $validated['name'],
                'location' => $validated['location'],
                'manager' => $validated['manager'],
                'phone' => $validated['phone'],
                'email' => $validated['email'],
            ]);

            User::create([
                'name' => $validated['manager'],
                'username' => $validated['admin_username'],
                'email' => $validated['admin_username'].'@templestreet.local',
                'password' => $validated['admin_password'],
                'role' => UserRole::Local,
                'branch_id' => $branch->id,
                'email_verified_at' => now(),
            ]);

            return $branch;
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Territory ":name" was created with its admin account.', ['name' => $branch->name]),
        ]);

        return back();
    }

    /**
     * Update the given branch and optionally reset its admin password.
     */
    public function update(Request $request, Branch $branch): RedirectResponse
    {
        Gate::authorize('update', $branch);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'location' => ['required', 'string', 'max:150'],
            'manager' => ['required', 'string', 'max:100'],
            'phone' => [
                'required',
                'string',
                'max:30',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (str_starts_with((string) $value, '09')) {
                        $digits = preg_replace('/\D/', '', (string) $value);
                        if (strlen((string) $value) > 11 || strlen($digits) !== 11) {
                            $fail('Mobile numbers starting with 09 must be exactly 11 digits and cannot exceed 11 digits.');
                        }
                    }
                },
                'regex:/^(09\d{9}|\+639\d{9}|\(0\d{1,2}\)\s?\d{3,4}[-\s]?\d{4}|0\d{1,2}[-\s]?\d{3,4}[-\s]?\d{4}|\d{4}[-\s]?\d{4})$/',
            ],
            'email' => [
                'required',
                'string',
                app()->runningUnitTests() ? 'email' : 'email:rfc,dns',
                'max:100',
            ],
            'admin_password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ], $this->messages());

        $branch->update([
            'name' => $validated['name'],
            'location' => $validated['location'],
            'manager' => $validated['manager'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
        ]);

        if (filled($validated['admin_password'] ?? null)) {
            $admin = $branch->admins()->where('role', UserRole::Local)->first();

            if ($admin === null) {
                throw ValidationException::withMessages([
                    'admin_password' => __('This territory has no admin account to reset.'),
                ]);
            }

            $admin->update(['password' => $validated['admin_password']]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Territory ":name" was updated.', ['name' => $branch->name]),
        ]);

        return back();
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'phone.required' => 'The territory head contact number is required.',
            'phone.regex' => 'Please provide a valid Philippine mobile (e.g. 09171234567) or landline (e.g. (02) 8555-1234).',
            'email.required' => 'The territory email address is required.',
            'email.email' => 'Please provide a valid email address.',
        ];
    }

    /**
     * Delete the given branch with its members, cards and admin accounts.
     */
    public function destroy(Branch $branch): RedirectResponse
    {
        Gate::authorize('delete', $branch);

        DB::transaction(function () use ($branch): void {
            $branch->members()
                ->whereNotNull('avatar_path')
                ->pluck('avatar_path')
                ->each(fn (string $path) => Storage::disk('public')->delete($path));

            $branch->admins()->where('role', UserRole::Local)->delete();
            $branch->delete();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Branch deleted.'),
        ]);

        return back();
    }
}
