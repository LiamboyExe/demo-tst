<?php

use App\Models\Branch;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->member = Member::factory()->for($this->branch)->create();
    $this->account = $this->member->issueTemporaryCredentials();
});

test('a member with temporary credentials cannot sign in through standard login', function () {
    $this->post(route('login.store'), [
        'email' => $this->account->temp_username,
        'password' => $this->account->temp_password,
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('the activation screen renders on the member NFC profile', function () {
    $this->get(route('activation.show', $this->member))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Activate')
            ->where('member.full_name', $this->member->full_name)
            ->where('member.profile_slug', $this->member->profile_slug));
});

test('activation screen redirects if account is already activated', function () {
    $this->account->forceFill([
        'username' => 'activated_user',
        'activated_at' => now(),
    ])->save();

    $this->get(route('activation.show', $this->member))
        ->assertRedirect(route('profile.show', $this->member));
});

test('activation screen redirects if visitor is already signed in', function () {
    $other = User::factory()->create();

    $this->actingAs($other)
        ->get(route('activation.show', $this->member))
        ->assertRedirect(route('profile.show', $this->member));
});

test('verifying with wrong temporary credentials fails', function () {
    $this->post(route('activation.verify', $this->member), [
        'temp_username' => 'wrong_username',
        'temp_password' => 'wrong_password',
    ])->assertSessionHasErrors('temp_username');

    expect(session()->has('activation_user_id'))->toBeFalse();
});

test('verifying with correct temporary credentials unlocks step 2', function () {
    $this->post(route('activation.verify', $this->member), [
        'temp_username' => $this->account->temp_username,
        'temp_password' => $this->account->temp_password,
    ])->assertRedirect(route('activation.create', $this->member));

    expect(session('activation_user_id'))->toBe($this->account->id);

    $this->get(route('activation.create', $this->member))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/ActivateAccount')
            ->where('member.full_name', $this->member->full_name)
            ->has('passwordRules'));
});

test('step 2 cannot be accessed without verifying temporary credentials in session', function () {
    $this->get(route('activation.create', $this->member))
        ->assertRedirect(route('activation.show', $this->member));
});

test('cannot use temporary password as permanent password', function () {
    $tempPass = $this->account->temp_password;

    $this->withSession(['activation_user_id' => $this->account->id])
        ->post(route('activation.store', $this->member), [
            'username' => 'permanent_user',
            'password' => $tempPass,
            'password_confirmation' => $tempPass,
        ])
        ->assertSessionHasErrors('password');
});

test('permanent username must be unique', function () {
    User::factory()->create(['username' => 'existing_user']);

    $this->withSession(['activation_user_id' => $this->account->id])
        ->post(route('activation.store', $this->member), [
            'username' => 'existing_user',
            'password' => 'SafePassword123!',
            'password_confirmation' => 'SafePassword123!',
        ])
        ->assertSessionHasErrors('username');
});

test('completing activation saves permanent credentials, signs in member, and unlocks sensitive profile', function () {
    $this->withSession(['activation_user_id' => $this->account->id])
        ->post(route('activation.store', $this->member), [
            'username' => 'permanent_user',
            'password' => 'SafePassword123!',
            'password_confirmation' => 'SafePassword123!',
        ])
        ->assertRedirect(route('profile.show', $this->member));

    $this->assertAuthenticatedAs($this->account);
    expect(session()->has('activation_user_id'))->toBeFalse();

    $account = $this->account->fresh();

    expect($account->username)->toBe('permanent_user')
        ->and($account->temp_username)->toBeNull()
        ->and($account->temp_password)->toBeNull()
        ->and($account->isActivated())->toBeTrue()
        ->and(Hash::check('SafePassword123!', $account->password))->toBeTrue();

    // Sensitive profile is now viewable by the logged-in member
    $this->get(route('profile.show', $this->member))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canViewSensitive', true)
            ->where('sensitive.email', $this->member->email));
});
