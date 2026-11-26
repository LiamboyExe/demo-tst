<?php

use App\Models\Branch;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->north = Branch::factory()->create();
    $this->south = Branch::factory()->create();
    $this->mainAdmin = User::factory()->mainAdmin()->create();
    $this->northAdmin = User::factory()->branchAdmin($this->north)->create();
});

test('archiving a member hides them from the members list and keeps their nfc card', function () {
    $member = Member::factory()->for($this->north)->create();
    $card = $member->nfcCard()->create([
        'card_uid' => 'NFC-ARCHIVE01',
        'profile_url' => 'http://example.com/p/archived',
        'card_status' => 'Active',
        'issued_date' => '2026-01-15',
    ]);

    $this->actingAs($this->mainAdmin)
        ->delete(route('members.destroy', $member))
        ->assertRedirect();

    $this->assertSoftDeleted('members', ['id' => $member->id]);
    $this->assertDatabaseHas('nfc_cards', ['id' => $card->id]);

    $this->actingAs($this->mainAdmin)
        ->get(route('members.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('members/Index')
            ->has('members.data', 0));

    $this->actingAs($this->mainAdmin)
        ->get(route('archives.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('members/Archived')
            ->has('members.data', 1)
            ->where('members.data.0.id', $member->id)
            ->whereNot('members.data.0.deleted_at', null));
});

test('an archived member shows a former member page until restored', function () {
    $member = Member::factory()->for($this->north)->create(['approved' => true]);

    $this->actingAs($this->mainAdmin)
        ->delete(route('members.destroy', $member))
        ->assertRedirect();

    $this->get(route('profile.show', $member->profile_slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Archived')
            ->where('member.full_name', $member->full_name)
            ->missing('member.email')
            ->missing('member.phone'));

    $this->actingAs($this->mainAdmin)
        ->patch(route('archives.restore', $member))
        ->assertRedirect();

    $this->assertNotSoftDeleted('members', ['id' => $member->id]);

    $this->get(route('profile.show', $member->profile_slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Profile/Show'));

    $this->actingAs($this->mainAdmin)
        ->get(route('members.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('members/Index')
            ->has('members.data', 1)
            ->where('members.data.0.id', $member->id));

    $this->actingAs($this->mainAdmin)
        ->get(route('archives.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('members/Archived')
            ->has('members.data', 0));
});

test('permanently deleting an archived member removes their record, nfc card and avatar', function () {
    Storage::fake('public');
    Storage::disk('public')->put('avatars/archived.jpg', 'avatar');

    $member = Member::factory()->for($this->north)->create([
        'avatar_path' => 'avatars/archived.jpg',
    ]);
    $card = $member->nfcCard()->create([
        'card_uid' => 'NFC-PURGE01',
        'profile_url' => 'http://example.com/p/purge',
        'card_status' => 'Active',
        'issued_date' => '2026-01-15',
    ]);

    $this->actingAs($this->mainAdmin)
        ->delete(route('members.destroy', $member))
        ->assertRedirect();

    $this->actingAs($this->mainAdmin)
        ->delete(route('archives.destroy', $member))
        ->assertRedirect();

    $this->assertDatabaseMissing('members', ['id' => $member->id]);
    $this->assertDatabaseMissing('nfc_cards', ['id' => $card->id]);
    Storage::disk('public')->assertMissing('avatars/archived.jpg');
});

test('archived routes cannot permanently delete an active member', function () {
    $member = Member::factory()->for($this->north)->create();

    $this->actingAs($this->mainAdmin)
        ->delete(route('archives.destroy', $member))
        ->assertNotFound();

    $this->assertNotSoftDeleted('members', ['id' => $member->id]);
});

test('branch admins only see and manage archives from their own branch', function () {
    $northMember = Member::factory()->for($this->north)->create();
    $southMember = Member::factory()->for($this->south)->create();

    $northMember->delete();
    $southMember->delete();

    $this->actingAs($this->northAdmin)
        ->get(route('archives.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('members/Archived')
            ->has('members.data', 1)
            ->where('members.data.0.id', $northMember->id));

    $this->actingAs($this->northAdmin)
        ->patch(route('archives.restore', $southMember))
        ->assertForbidden();

    $this->actingAs($this->northAdmin)
        ->delete(route('archives.destroy', $southMember))
        ->assertForbidden();
});
