<?php

use App\Models\Branch;
use App\Models\Member;
use App\Models\User;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->member = Member::factory()->for($this->branch)->create();
    $this->account = User::factory()->member($this->member)->create([
        'username' => 'juan',
        'password' => 'temporary9',
        'must_change_password' => true,
    ]);
});

test('a member logging in with a temporary password must change it first', function () {
    $this->get(route('login', ['intended' => '/p/'.$this->member->profile_slug]));

    $this->post(route('login.store'), [
        'email' => 'juan',
        'password' => 'temporary9',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($this->account);

    // The profile the member wanted is withheld until the password is set.
    $this->get('/p/'.$this->member->profile_slug)
        ->assertRedirect(route('temporary-password.edit'));

    $this->get(route('dashboard'))
        ->assertRedirect(route('temporary-password.edit'));
});

test('the temporary password screen renders', function () {
    $this->actingAs($this->account)
        ->get(route('temporary-password.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/ChangeTemporaryPassword')
            ->has('passwordRules'));
});

test('the temporary password cannot be changed with a wrong current password', function () {
    $this->actingAs($this->account)
        ->put(route('temporary-password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertSessionHasErrors('current_password');

    expect($this->account->fresh()->must_change_password)->toBeTrue();
});

test('changing the temporary password unlocks the member profile', function () {
    $this->actingAs($this->account)
        ->put(route('temporary-password.update'), [
            'current_password' => 'temporary9',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertRedirect(route('profile.show', $this->member));

    $account = $this->account->fresh();

    expect($account->must_change_password)->toBeFalse();

    // The old temporary password no longer works; the new one does.
    $this->post(route('logout'));

    $this->post(route('login.store'), [
        'email' => 'juan',
        'password' => 'temporary9',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();

    $this->post(route('login.store'), [
        'email' => 'juan',
        'password' => 'new-password-123',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($account);

    // With the password set, the member can reach their own profile again.
    $this->get('/p/'.$this->member->profile_slug)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canViewSensitive', true)
            ->where('sensitive.email', $this->member->email));
});

test('a member who already changed their password is not asked again', function () {
    $this->account->forceFill(['must_change_password' => false])->save();

    $this->actingAs($this->account)
        ->get(route('temporary-password.edit'))
        ->assertRedirect(route('profile.show', $this->member));

    // Administrative pages still keep the member on their own profile.
    $this->actingAs($this->account)
        ->get(route('dashboard'))
        ->assertRedirect(route('profile.show', $this->member));
});

test('a member can log out while their password change is pending', function () {
    $this->actingAs($this->account)
        ->post(route('logout'));

    $this->assertGuest();
});

test('admins are not affected by the temporary password middleware', function () {
    $admin = User::factory()->mainAdmin()->create(['must_change_password' => true]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertRedirect(route('temporary-password.edit'));

    // Admins do not have an NFC profile to land on.
    $this->actingAs($admin)
        ->put(route('temporary-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertRedirect(route('dashboard', absolute: false));

    expect($admin->fresh()->must_change_password)->toBeFalse();
});
