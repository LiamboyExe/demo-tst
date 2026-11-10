<?php

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->north = Branch::factory()->create();
    $this->south = Branch::factory()->create();
    $this->mainAdmin = User::factory()->mainAdmin()->create();
    $this->northAdmin = User::factory()->branchAdmin($this->north)->create();
});

test('a main admin can create a login account for any member', function () {
    $member = Member::factory()->for($this->south)->create();

    $this->actingAs($this->mainAdmin)
        ->post(route('members.account.store', $member), [
            'username' => 'pedro',
            'password' => 'temporary9',
            'password_confirmation' => 'temporary9',
        ])
        ->assertRedirect();

    $account = User::query()->where('username', 'pedro')->firstOrFail();

    expect($account->role)->toBe(UserRole::Member)
        ->and($account->member_id)->toBe($member->id)
        ->and($account->branch_id)->toBeNull()
        ->and($account->must_change_password)->toBeTrue()
        ->and(Hash::check('temporary9', $account->password))->toBeTrue()
        ->and($account->email)->toBe($member->email);
});

test('a branch admin can create a login account for a member in their own territory', function () {
    $member = Member::factory()->for($this->north)->create();

    $this->actingAs($this->northAdmin)
        ->post(route('members.account.store', $member), [
            'username' => 'northern-member',
            'password' => 'temporary9',
            'password_confirmation' => 'temporary9',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('users', [
        'username' => 'northern-member',
        'member_id' => $member->id,
        'role' => UserRole::Member->value,
    ]);
});

test('a branch admin cannot create a login account for a member in another territory', function () {
    $member = Member::factory()->for($this->south)->create();

    $this->actingAs($this->northAdmin)
        ->post(route('members.account.store', $member), [
            'username' => 'stolen',
            'password' => 'temporary9',
            'password_confirmation' => 'temporary9',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('users', ['username' => 'stolen']);
});

test('members cannot create login accounts', function () {
    $member = Member::factory()->for($this->north)->create();
    $account = User::factory()->member($member)->create();

    $other = Member::factory()->for($this->north)->create();

    $this->actingAs($account)
        ->post(route('members.account.store', $other), [
            'username' => 'sneaky',
            'password' => 'temporary9',
            'password_confirmation' => 'temporary9',
        ])
        ->assertForbidden();
});

test('guests cannot create login accounts', function () {
    $member = Member::factory()->for($this->north)->create();

    $this->post(route('members.account.store', $member), [
        'username' => 'anon',
        'password' => 'temporary9',
        'password_confirmation' => 'temporary9',
    ])->assertRedirect(route('login'));
});

test('a member can only have one login account', function () {
    $member = Member::factory()->for($this->north)->create();
    User::factory()->member($member)->create(['username' => 'first']);

    $this->actingAs($this->mainAdmin)
        ->post(route('members.account.store', $member), [
            'username' => 'second',
            'password' => 'temporary9',
            'password_confirmation' => 'temporary9',
        ])
        ->assertSessionHasErrors('username');

    $this->assertDatabaseMissing('users', ['username' => 'second']);
});

test('usernames must be unique when creating a member account', function () {
    $member = Member::factory()->for($this->north)->create();
    User::factory()->create(['username' => 'taken']);

    $this->actingAs($this->mainAdmin)
        ->post(route('members.account.store', $member), [
            'username' => 'taken',
            'password' => 'temporary9',
            'password_confirmation' => 'temporary9',
        ])
        ->assertSessionHasErrors('username');
});

test('a member whose email already belongs to an account cannot receive one', function () {
    $member = Member::factory()->for($this->north)->create();
    User::factory()->create(['email' => $member->email]);

    $this->actingAs($this->mainAdmin)
        ->post(route('members.account.store', $member), [
            'username' => 'clashing',
            'password' => 'temporary9',
            'password_confirmation' => 'temporary9',
        ])
        ->assertSessionHasErrors('username');

    $this->assertDatabaseMissing('users', ['username' => 'clashing']);
});

test('member account input is validated server-side', function () {
    $member = Member::factory()->for($this->north)->create();

    $this->actingAs($this->mainAdmin)
        ->post(route('members.account.store', $member), [
            'username' => '',
            'password' => 'tiny',
            'password_confirmation' => 'mismatch',
        ])
        ->assertSessionHasErrors(['username', 'password']);

    $this->assertDatabaseMissing('users', ['member_id' => $member->id]);
});

test('an admin can remove a member login account', function () {
    $member = Member::factory()->for($this->north)->create();
    $account = User::factory()->member($member)->create();

    $this->actingAs($this->northAdmin)
        ->delete(route('members.account.destroy', $member))
        ->assertRedirect();

    $this->assertDatabaseMissing('users', ['id' => $account->id]);
    // The member record itself is untouched.
    $this->assertDatabaseHas('members', ['id' => $member->id]);
});

test('removing a missing member account reports an error', function () {
    $member = Member::factory()->for($this->north)->create();

    $this->actingAs($this->mainAdmin)
        ->delete(route('members.account.destroy', $member))
        ->assertSessionHasErrors('username');
});

test('a member account cannot remove another member account', function () {
    $member = Member::factory()->for($this->north)->create();
    $account = User::factory()->member($member)->create();

    $other = Member::factory()->for($this->north)->create();
    User::factory()->member($other)->create(['username' => 'other']);

    $this->actingAs($account)
        ->delete(route('members.account.destroy', $other))
        ->assertForbidden();

    $this->assertDatabaseHas('users', ['username' => 'other']);
});

test('the member list shows account status for each member', function () {
    $withoutAccount = Member::factory()->for($this->north)->create(['created_at' => now()->subMinute()]);
    $withAccount = Member::factory()->for($this->north)->create();
    User::factory()->member($withAccount)->create(['username' => 'listed-member']);

    $this->actingAs($this->mainAdmin)
        ->get(route('members.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('members/Index')
            ->where('members.data.0.user.username', 'listed-member')
            ->where('members.data.1.user', null));
});

test('member accounts are kept out of administrative pages', function () {
    $member = Member::factory()->for($this->north)->create();
    $account = User::factory()->member($member)->create(['must_change_password' => false]);

    // Page visits send the member back to their own NFC profile.
    $this->actingAs($account)
        ->get(route('dashboard'))
        ->assertRedirect(route('profile.show', $member));

    $this->actingAs($account)
        ->get(route('members.index'))
        ->assertRedirect(route('profile.show', $member));

    // Other request methods are rejected outright.
    $other = Member::factory()->for($this->north)->create();

    $this->actingAs($account)
        ->patch(route('members.update', $other), ['first_name' => 'Nope'])
        ->assertForbidden();

    $this->actingAs($account)
        ->post(route('branches.store'), [
            'name' => 'Nope',
            'location' => 'Nope',
            'manager' => 'Nope',
            'phone' => '09170000000',
            'email' => 'nope@example.com',
            'admin_username' => 'nope_admin',
            'admin_password' => 'password123',
            'admin_password_confirmation' => 'password123',
        ])
        ->assertForbidden();

    // The member record is unchanged.
    expect($other->fresh()->first_name)->toBe($other->first_name);
});

test('guests are still sent to the login page for administrative pages', function () {
    $this->get(route('members.index'))->assertRedirect(route('login'));
    $this->get(route('branches.index'))->assertRedirect(route('login'));
    $this->get(route('nfc-cards.index'))->assertRedirect(route('login'));
});
