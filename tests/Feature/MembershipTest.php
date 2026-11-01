<?php

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Hood;
use App\Models\Member;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->north = Branch::factory()->create();
    $this->south = Branch::factory()->create();
    $this->mainAdmin = User::factory()->mainAdmin()->create();
    $this->northAdmin = User::factory()->branchAdmin($this->north)->create();
});

test('main admin sees members from every branch', function () {
    Member::factory()->for($this->north)->create();
    Member::factory()->for($this->south)->create();

    $this->actingAs($this->mainAdmin)
        ->get(route('members.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('members/Index')
            ->has('members.data', 2));
});

test('branch admin only sees members from their own branch', function () {
    Member::factory()->for($this->north)->create();
    Member::factory()->for($this->south)->create();

    $this->actingAs($this->northAdmin)
        ->get(route('members.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('members/Index')
            ->has('members.data', 1)
            ->where('members.data.0.branch_id', $this->north->id));
});

test('branch admin can add a member and is forced into their own branch', function () {
    $this->actingAs($this->northAdmin)
        ->post(route('members.store'), [
            'branch_id' => $this->south->id,
            'first_name' => 'Jamie',
            'last_name' => 'Rivera',
            'email' => 'jamie@example.com',
            'membership_type' => 'Carnal',
            'status' => 'Active',
            'join_date' => '2026-01-15',
            'approved' => 1,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('members', [
        'email' => 'jamie@example.com',
        'branch_id' => $this->north->id,
    ]);
});

test('branch admin cannot update a member from another branch', function () {
    $member = Member::factory()->for($this->south)->create();

    $this->actingAs($this->northAdmin)
        ->patch(route('members.update', $member), [
            'first_name' => 'Hacked',
        ])
        ->assertForbidden();
});

test('main admin can update and delete members from any branch', function () {
    $member = Member::factory()->for($this->south)->create();

    $this->actingAs($this->mainAdmin)
        ->patch(route('members.update', $member), [
            'branch_id' => $this->south->id,
            'first_name' => 'Updated',
            'last_name' => $member->last_name,
            'email' => $member->email,
            'membership_type' => 'Carnal',
            'status' => 'Active',
            'join_date' => '2026-01-15',
            'approved' => 1,
        ])
        ->assertRedirect();

    expect($member->fresh()->first_name)->toBe('Updated');

    $this->actingAs($this->mainAdmin)
        ->delete(route('members.destroy', $member))
        ->assertRedirect();

    $this->assertDatabaseMissing('members', ['id' => $member->id]);
});

test('branch admin can only manage their own branch', function () {
    $this->actingAs($this->mainAdmin)
        ->post(route('branches.store'), [
            'name' => 'East Branch',
            'location' => 'Rizal Ave',
            'manager' => 'Branch Manager',
            'phone' => '09170000000',
            'email' => 'east@example.com',
            'admin_username' => 'east_admin',
            'admin_password' => 'password123',
            'admin_password_confirmation' => 'password123',
        ])
        ->assertRedirect();

    $branch = Branch::query()->where('name', 'East Branch')->firstOrFail();

    $this->assertDatabaseHas('users', [
        'username' => 'east_admin',
        'branch_id' => $branch->id,
        'role' => UserRole::Local->value,
    ]);

    $this->actingAs($this->northAdmin)
        ->get(route('branches.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('branches/Index')
            ->has('branches', 1));

    $this->actingAs($this->northAdmin)
        ->post(route('branches.store'), [
            'name' => 'Not Allowed',
            'location' => 'Nowhere',
            'manager' => 'Nobody',
            'admin_username' => 'nope_admin',
            'admin_password' => 'password123',
            'admin_password_confirmation' => 'password123',
        ])
        ->assertForbidden();
});

test('deleting a branch removes its members, cards and admin account', function () {
    $member = Member::factory()->for($this->south)->create();
    $card = $member->nfcCard()->create([
        'card_uid' => 'NFC-TEST0001',
        'profile_url' => 'http://example.com/p/test',
        'card_status' => 'Active',
        'issued_date' => '2026-01-15',
    ]);
    $branchAdmin = User::factory()->branchAdmin($this->south)->create();

    $this->actingAs($this->mainAdmin)
        ->delete(route('branches.destroy', $this->south))
        ->assertRedirect();

    $this->assertDatabaseMissing('branches', ['id' => $this->south->id]);
    $this->assertDatabaseMissing('members', ['id' => $member->id]);
    $this->assertDatabaseMissing('nfc_cards', ['id' => $card->id]);
    $this->assertDatabaseMissing('users', ['id' => $branchAdmin->id]);
});

test('members can be filtered by hood', function () {
    $hoodA = Hood::create(['branch_id' => $this->north->id, 'name' => 'Calle Locos']);
    $hoodB = Hood::create(['branch_id' => $this->north->id, 'name' => 'Traviezo']);

    Member::factory()->for($this->north)->create(['hood_id' => $hoodA->id]);
    Member::factory()->for($this->north)->create(['hood_id' => $hoodB->id]);

    $this->actingAs($this->mainAdmin)
        ->get(route('members.index', ['hood' => $hoodA->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('members/Index')
            ->has('members.data', 1)
            ->where('members.data.0.hood_id', $hoodA->id));
});

test('territory phone and email must be valid on store and update', function () {
    // Fails with invalid phone and email
    $this->actingAs($this->mainAdmin)
        ->post(route('branches.store'), [
            'name' => 'Invalid Territory',
            'location' => 'Nowhere',
            'manager' => 'Some Manager',
            'phone' => 'not-a-phone',
            'email' => 'not-an-email',
            'admin_username' => 'inv_admin',
            'admin_password' => 'password123',
            'admin_password_confirmation' => 'password123',
        ])
        ->assertSessionHasErrors(['phone', 'email']);

    // Succeeds with valid landline phone and valid email
    $this->actingAs($this->mainAdmin)
        ->post(route('branches.store'), [
            'name' => 'Valid Landline Territory',
            'location' => 'Quezon City',
            'manager' => 'Landline Manager',
            'phone' => '(02) 8555-9999',
            'email' => 'landline@example.com',
            'admin_username' => 'll_admin',
            'admin_password' => 'password123',
            'admin_password_confirmation' => 'password123',
        ])
        ->assertRedirect();

    $branch = Branch::query()->where('name', 'Valid Landline Territory')->firstOrFail();
    expect($branch->phone)->toBe('(02) 8555-9999');
    expect($branch->email)->toBe('landline@example.com');

    // Fails on update with invalid phone
    $this->actingAs($this->mainAdmin)
        ->patch(route('branches.update', $branch), [
            'name' => 'Valid Landline Territory',
            'location' => 'Quezon City',
            'manager' => 'Landline Manager',
            'phone' => '12345',
            'email' => 'landline@example.com',
        ])
        ->assertSessionHasErrors(['phone']);

    // Fails when 09 number exceeds 11 digits (e.g. 12 digits)
    $this->actingAs($this->mainAdmin)
        ->post(route('branches.store'), [
            'name' => 'Too Long Phone Territory',
            'location' => 'Quezon City',
            'manager' => 'Too Long Manager',
            'phone' => '091712345678',
            'email' => 'toolong@example.com',
            'admin_username' => 'toolong_admin',
            'admin_password' => 'password123',
            'admin_password_confirmation' => 'password123',
        ])
        ->assertSessionHasErrors(['phone']);

    // Fails on update with phone exceeding 11 digits
    $this->actingAs($this->mainAdmin)
        ->patch(route('branches.update', $branch), [
            'name' => 'Valid Landline Territory',
            'location' => 'Quezon City',
            'manager' => 'Landline Manager',
            'phone' => '0917123456789',
            'email' => 'updated@example.com',
        ])
        ->assertSessionHasErrors(['phone']);

    // Succeeds on update with exactly 11 digits (09XXXXXXXXX)
    $this->actingAs($this->mainAdmin)
        ->patch(route('branches.update', $branch), [
            'name' => 'Valid Landline Territory',
            'location' => 'Quezon City',
            'manager' => 'Landline Manager',
            'phone' => '09171234567',
            'email' => 'updated@example.com',
        ])
        ->assertRedirect();

    expect($branch->fresh()->phone)->toBe('09171234567');
    expect($branch->fresh()->email)->toBe('updated@example.com');
});
