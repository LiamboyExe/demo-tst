<?php

use App\Models\Branch;
use App\Models\User;

beforeEach(function () {
    $this->north = Branch::factory()->create();
    $this->south = Branch::factory()->create();
    $this->mainAdmin = User::factory()->mainAdmin()->create();
    $this->northAdmin = User::factory()->branchAdmin($this->north)->create();
});

test('main admin can add a hood to a territory', function () {
    $this->actingAs($this->mainAdmin)
        ->post(route('branches.hoods.store', $this->north), [
            'name' => 'Calle Locos',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('hoods', [
        'branch_id' => $this->north->id,
        'name' => 'Calle Locos',
    ]);
});

test('hood names are unique within a territory but not across territories', function () {
    $this->actingAs($this->mainAdmin)
        ->post(route('branches.hoods.store', $this->north), [
            'name' => 'Calle Locos',
        ])
        ->assertRedirect();

    $this->actingAs($this->mainAdmin)
        ->post(route('branches.hoods.store', $this->north), [
            'name' => 'Calle Locos',
        ])
        ->assertSessionHasErrors(['name']);

    $this->actingAs($this->mainAdmin)
        ->post(route('branches.hoods.store', $this->south), [
            'name' => 'Calle Locos',
        ])
        ->assertRedirect();

    expect($this->north->hoods()->count())->toBe(1)
        ->and($this->south->hoods()->count())->toBe(1);
});

test('branch admins cannot add hoods to a territory', function () {
    $this->actingAs($this->northAdmin)
        ->post(route('branches.hoods.store', $this->north), [
            'name' => 'Calle Locos',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('hoods', ['name' => 'Calle Locos']);
});
