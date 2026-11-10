<?php

use App\Enums\MembershipType;
use App\Enums\MemberStatus;
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

test('creating a member automatically creates a user account with temporary credentials', function () {
    $this->actingAs($this->mainAdmin)
        ->post(route('members.store'), [
            'first_name' => 'Maria',
            'last_name' => 'Clara',
            'email' => 'maria.clara@example.com',
            'branch_id' => $this->north->id,
            'membership_type' => MembershipType::Pointman->value,
            'status' => MemberStatus::Active->value,
            'join_date' => '2026-01-15',
            'approved' => true,
        ])
        ->assertRedirect();

    $member = Member::query()->where('email', 'maria.clara@example.com')->firstOrFail();
    $account = $member->user;

    expect($account)->not->toBeNull()
        ->and($account->role)->toBe(UserRole::Member)
        ->and($account->member_id)->toBe($member->id)
        ->and($account->branch_id)->toBeNull()
        ->and($account->username)->toBeNull()
        ->and($account->isPendingActivation())->toBeTrue()
        ->and($account->isActivated())->toBeFalse()
        ->and($account->temp_username)->not->toBeNull()
        ->and($account->temp_password)->not->toBeNull()
        ->and(Hash::check($account->temp_password, $account->password))->toBeTrue();
});

test('a territory admin automatically creates account within their territory only', function () {
    $this->actingAs($this->northAdmin)
        ->post(route('members.store'), [
            'first_name' => 'Northern',
            'last_name' => 'Hero',
            'email' => 'northern.hero@example.com',
            'branch_id' => $this->north->id,
            'membership_type' => MembershipType::Pointman->value,
            'status' => MemberStatus::Active->value,
            'join_date' => '2026-01-15',
            'approved' => true,
        ])
        ->assertRedirect();

    $member = Member::query()->where('email', 'northern.hero@example.com')->firstOrFail();
    expect($member->branch_id)->toBe($this->north->id)
        ->and($member->user)->not->toBeNull();
});

test('cannot create member if email already belongs to a user account', function () {
    User::factory()->create(['email' => 'clashing@example.com']);

    $this->actingAs($this->mainAdmin)
        ->post(route('members.store'), [
            'first_name' => 'Conflict',
            'last_name' => 'Person',
            'email' => 'clashing@example.com',
            'branch_id' => $this->north->id,
            'membership_type' => MembershipType::Pointman->value,
            'status' => MemberStatus::Active->value,
            'join_date' => '2026-01-15',
            'approved' => true,
        ])
        ->assertSessionHasErrors('email');

    $this->assertDatabaseMissing('members', ['email' => 'clashing@example.com']);
});

test('a main admin can issue or reset temporary credentials for any member', function () {
    $member = Member::factory()->for($this->south)->create();

    $this->actingAs($this->mainAdmin)
        ->post(route('members.account.store', $member))
        ->assertRedirect();

    $account = $member->user()->firstOrFail();

    expect($account->role)->toBe(UserRole::Member)
        ->and($account->member_id)->toBe($member->id)
        ->and($account->isPendingActivation())->toBeTrue()
        ->and($account->temp_username)->not->toBeNull()
        ->and($account->temp_password)->not->toBeNull();
});

test('a branch admin can issue temporary credentials for a member in their own territory', function () {
    $member = Member::factory()->for($this->north)->create();

    $this->actingAs($this->northAdmin)
        ->post(route('members.account.store', $member))
        ->assertRedirect();

    expect($member->user()->exists())->toBeTrue();
});

test('a branch admin cannot issue credentials for a member in another territory', function () {
    $member = Member::factory()->for($this->south)->create();

    $this->actingAs($this->northAdmin)
        ->post(route('members.account.store', $member))
        ->assertForbidden();

    expect($member->user()->exists())->toBeFalse();
});

test('authorized admin can retrieve pending temporary credentials', function () {
    $member = Member::factory()->for($this->north)->create();
    $member->issueTemporaryCredentials();

    $account = $member->user;

    $this->actingAs($this->northAdmin)
        ->getJson(route('members.account.credentials', $member))
        ->assertOk()
        ->assertJson([
            'temp_username' => $account->temp_username,
            'temp_password' => $account->temp_password,
        ]);
});

test('admin cannot retrieve temporary credentials for an activated account', function () {
    $member = Member::factory()->for($this->north)->create();
    User::factory()->member($member)->create([
        'username' => 'activated-member',
        'temp_username' => null,
        'temp_password' => null,
        'activated_at' => now(),
    ]);

    $this->actingAs($this->northAdmin)
        ->getJson(route('members.account.credentials', $member))
        ->assertNotFound();
});

test('an unauthorized admin cannot retrieve temporary credentials from another branch', function () {
    $member = Member::factory()->for($this->south)->create();
    $member->issueTemporaryCredentials();

    $this->actingAs($this->northAdmin)
        ->getJson(route('members.account.credentials', $member))
        ->assertForbidden();
});

test('cannot reset credentials for an already activated account', function () {
    $member = Member::factory()->for($this->north)->create();
    User::factory()->member($member)->create([
        'username' => 'activated-member',
        'activated_at' => now(),
    ]);

    $this->actingAs($this->mainAdmin)
        ->post(route('members.account.store', $member))
        ->assertSessionHasErrors('username');
});

test('an admin can remove a member login account', function () {
    $member = Member::factory()->for($this->north)->create();
    $account = $member->issueTemporaryCredentials();

    $this->actingAs($this->northAdmin)
        ->delete(route('members.account.destroy', $member))
        ->assertRedirect();

    $this->assertDatabaseMissing('users', ['id' => $account->id]);
    $this->assertDatabaseHas('members', ['id' => $member->id]);
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
    $account = User::factory()->member($member)->create();

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

    expect($other->fresh()->first_name)->toBe($other->first_name);
});

test('guests are still sent to the login page for administrative pages', function () {
    $this->get(route('members.index'))->assertRedirect(route('login'));
    $this->get(route('branches.index'))->assertRedirect(route('login'));
    $this->get(route('nfc-cards.index'))->assertRedirect(route('login'));
});
