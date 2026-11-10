<?php

use App\Models\Branch;
use App\Models\Member;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->north = Branch::factory()->create();
    $this->south = Branch::factory()->create();
    $this->mainAdmin = User::factory()->mainAdmin()->create();
    $this->northAdmin = User::factory()->branchAdmin($this->north)->create();
    $this->southAdmin = User::factory()->branchAdmin($this->south)->create();
});

test('public visitors see an NFC profile without sensitive data', function () {
    $member = Member::factory()->for($this->north)->create([
        'email' => 'secret@example.com',
        'phone' => '09171234567',
        'address' => '42 Hidden Street',
    ]);

    $this->get(route('profile.show', $member))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Show')
            ->where('canViewSensitive', false)
            ->where('sensitive', null)
            ->missing('member.email')
            ->missing('member.phone')
            ->missing('member.address')
            ->missing('member.birthdate')
            ->missing('member.age')
            ->where('member.first_name', $member->first_name));
});

test('a member sees sensitive information on their own NFC profile', function () {
    $member = Member::factory()->for($this->north)->create();
    $account = User::factory()->member($member)->create();

    $this->actingAs($account)
        ->get(route('profile.show', $member))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Show')
            ->where('canViewSensitive', true)
            ->where('sensitive.email', $member->email)
            ->where('sensitive.phone', $member->phone));
});

test('a signed-in member cannot see another member sensitive information', function () {
    $juan = Member::factory()->for($this->north)->create();
    $pedro = Member::factory()->for($this->north)->create();
    $pedroAccount = User::factory()->member($pedro)->create();

    // Pedro scans Juan's NFC card while signed in.
    $this->actingAs($pedroAccount)
        ->get(route('profile.show', $juan))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Show')
            ->where('canViewSensitive', false)
            ->where('sensitive', null)
            ->missing('member.email')
            ->where('member.first_name', $juan->first_name));
});

test('a main admin can see sensitive information on any member profile', function () {
    $member = Member::factory()->for($this->south)->create();

    $this->actingAs($this->mainAdmin)
        ->get(route('profile.show', $member))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canViewSensitive', true)
            ->where('sensitive.email', $member->email));
});

test('a territory admin sees sensitive information only inside their territory', function () {
    $northMember = Member::factory()->for($this->north)->create();
    $southMember = Member::factory()->for($this->south)->create();

    $this->actingAs($this->northAdmin)
        ->get(route('profile.show', $northMember))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canViewSensitive', true));

    // Another territory's profile stays public-only for this admin.
    $this->actingAs($this->northAdmin)
        ->get(route('profile.show', $southMember))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canViewSensitive', false)
            ->where('sensitive', null));
});

test('logging in from an NFC profile returns to that profile', function () {
    $member = Member::factory()->for($this->north)->create();
    $account = User::factory()->member($member)->create([
        'username' => 'juan',
    ]);

    // The "View Sensitive Information" button links to the login page with
    // the profile remembered as the intended destination.
    $this->get(route('login', ['intended' => '/p/'.$member->profile_slug]));

    $this->post(route('login.store'), [
        'email' => 'juan',
        'password' => 'password',
    ])->assertRedirect('/p/'.$member->profile_slug);

    $this->assertAuthenticatedAs($account);

    $this->get('/p/'.$member->profile_slug)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canViewSensitive', true)
            ->where('sensitive.email', $member->email));
});

test('external intended destinations are ignored on login', function () {
    $user = User::factory()->create();

    $this->get(route('login', ['intended' => 'https://evil.example.com/steal']));
    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->post(route('logout'));
    $this->assertGuest();

    $this->get(route('login', ['intended' => '//evil.example.com']));
    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));
});

test('logging out removes access to sensitive information', function () {
    $member = Member::factory()->for($this->north)->create();
    $account = User::factory()->member($member)->create();

    $this->actingAs($account);

    $this->get(route('profile.show', $member))
        ->assertInertia(fn (Assert $page) => $page->where('canViewSensitive', true));

    $this->post(route('logout'));

    $this->assertGuest();

    $this->get(route('profile.show', $member))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canViewSensitive', false)
            ->where('sensitive', null)
            ->missing('member.email'));
});

test('an expired session requires authentication again', function () {
    $member = Member::factory()->for($this->north)->create();
    $account = User::factory()->member($member)->create([
        'username' => 'juan',
    ]);

    $this->post(route('login.store'), [
        'email' => 'juan',
        'password' => 'password',
    ]);
    $this->assertAuthenticatedAs($account);

    // Simulate the session expiring on the server: the stored session data
    // is gone, so the next request has to authenticate again.
    $this->app['auth']->forgetGuards();
    $this->flushSession();

    $this->get(route('dashboard'))->assertRedirect(route('login'));

    $this->get(route('profile.show', $member))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canViewSensitive', false)
            ->where('sensitive', null));
});
