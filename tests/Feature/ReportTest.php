<?php

use App\Models\Branch;
use App\Models\Member;
use App\Models\NfcCard;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->north = Branch::factory()->create(['name' => 'North Territory']);
    $this->south = Branch::factory()->create(['name' => 'South Territory']);
    $this->northHood = $this->north->hoods()->create(['name' => 'North Hood']);
    $this->southHood = $this->south->hoods()->create(['name' => 'South Hood']);
    $this->mainAdmin = User::factory()->mainAdmin()->create();
    $this->northAdmin = User::factory()->branchAdmin($this->north)->create();
});

test('guests cannot visit reports', function () {
    $this->get(route('reports.index'))->assertRedirect(route('login'));
});

test('summary report uses live member statuses and includes archived records', function () {
    Member::factory()->for($this->north)->create(['status' => 'Active']);
    Member::factory()->for($this->north)->create(['status' => 'Inactive']);
    Member::factory()->for($this->south)->create(['status' => 'Pending']);

    $archived = Member::factory()->for($this->north)->create(['status' => 'Active']);
    $archived->delete();

    $this->actingAs($this->mainAdmin)
        ->get(route('reports.index', ['report' => 'summary']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/Index')
            ->where('report', 'summary')
            ->where('reportData.stats.total', 3)
            ->where('reportData.stats.active', 1)
            ->where('reportData.stats.inactive', 1)
            ->where('reportData.stats.pending', 1)
            ->where('reportData.stats.registered', 4)
            ->where('reportData.stats.archived', 1));
});

test('branch admins only see their own territory in reports', function () {
    Member::factory()->for($this->north)->create(['status' => 'Active']);
    Member::factory()->for($this->south)->create(['status' => 'Active']);

    $this->actingAs($this->northAdmin)
        ->get(route('reports.index', [
            'report' => 'summary',
            'branch' => $this->south->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.branch', $this->north->id)
            ->where('reportData.stats.total', 1)
            ->where('reportData.territories.0.name', 'North Territory'));
});

test('hood and territory report calculates totals and includes unassigned hoods', function () {
    Member::factory()->for($this->north)->create([
        'hood_id' => $this->northHood->id,
        'status' => 'Active',
    ]);
    Member::factory()->for($this->north)->create([
        'hood_id' => $this->northHood->id,
        'status' => 'Inactive',
    ]);
    Member::factory()->for($this->north)->create([
        'hood_id' => null,
        'status' => 'Pending',
    ]);
    Member::factory()->for($this->south)->create([
        'hood_id' => $this->southHood->id,
        'status' => 'Active',
    ]);

    $this->actingAs($this->mainAdmin)
        ->get(route('reports.index', [
            'report' => 'territories',
            'branch' => $this->north->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reportData.stats.total', 3)
            ->where('reportData.stats.active', 1)
            ->where('reportData.stats.inactive', 1)
            ->where('reportData.stats.pending', 1)
            ->where('reportData.rows', function ($rows) {
                return collect($rows)->contains(fn (array $row) => $row['hood'] === 'No hood assigned' && $row['total'] === 1);
            }));
});

test('registration report applies date and status filters and groups the trend', function () {
    Member::factory()->for($this->north)->create([
        'hood_id' => $this->northHood->id,
        'status' => 'Active',
        'join_date' => '2024-03-10',
    ]);
    Member::factory()->for($this->north)->create([
        'status' => 'Inactive',
        'join_date' => '2024-04-11',
    ]);
    Member::factory()->for($this->north)->create([
        'status' => 'Active',
        'join_date' => '2023-01-01',
    ]);

    $this->actingAs($this->mainAdmin)
        ->get(route('reports.index', [
            'report' => 'registrations',
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'status' => 'Active',
            'group' => 'year',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reportData.stats.total', 1)
            ->where('reportData.trend.0.period', '2024')
            ->where('reportData.trend.0.total', 1)
            ->where('reportData.records.total', 1));
});

test('nfc report counts cards and members without cards', function () {
    $withActiveCard = Member::factory()->for($this->north)->create();
    $withLostCard = Member::factory()->for($this->north)->create();
    Member::factory()->for($this->north)->create();

    NfcCard::factory()->for($withActiveCard)->create(['card_status' => 'Active']);
    NfcCard::factory()->for($withLostCard)->create(['card_status' => 'Lost']);

    $this->actingAs($this->mainAdmin)
        ->get(route('reports.index', ['report' => 'nfc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('reportData.stats.total', 2)
            ->where('reportData.stats.assigned', 2)
            ->where('reportData.stats.unassigned', 1)
            ->where('reportData.stats.active', 1)
            ->where('reportData.stats.lost', 1)
            ->has('reportData.cards', 2));
});

test('filters reject a hood outside the selected territory', function () {
    $this->actingAs($this->mainAdmin)
        ->get(route('reports.index', [
            'report' => 'territories',
            'branch' => $this->north->id,
            'hood' => $this->southHood->id,
        ]))
        ->assertSessionHasErrors('hood');
});

test('reports can be exported as pdf and xlsx', function () {
    $member = Member::factory()->for($this->north)->create([
        'first_name' => 'Report',
        'last_name' => 'Member',
        'status' => 'Active',
    ]);
    NfcCard::factory()->for($member)->create(['card_status' => 'Active']);

    $pdf = $this->actingAs($this->mainAdmin)
        ->get(route('reports.export', ['format' => 'pdf', 'report' => 'summary']));

    $pdf->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect($pdf->streamedContent())->toStartWith('%PDF');

    $xlsx = $this->actingAs($this->mainAdmin)
        ->get(route('reports.export', ['format' => 'xlsx', 'report' => 'registrations']));

    $xlsx->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $path = tempnam(sys_get_temp_dir(), 'report-test-');

    file_put_contents($path, $xlsx->streamedContent());

    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();

    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');

    expect($sheet)->toContain('Report Member')
        ->and($sheet)->toContain('Membership Registration Report');

    $zip->close();
    File::delete($path);
});

test('exports apply the active territory filter', function () {
    Member::factory()->for($this->north)->create([
        'first_name' => 'North',
        'last_name' => 'Only',
    ]);
    Member::factory()->for($this->south)->create([
        'first_name' => 'South',
        'last_name' => 'Only',
    ]);

    $response = $this->actingAs($this->mainAdmin)
        ->get(route('reports.export', [
            'format' => 'xlsx',
            'report' => 'registrations',
            'branch' => $this->north->id,
        ]));

    $path = tempnam(sys_get_temp_dir(), 'report-filter-test-');

    file_put_contents($path, $response->streamedContent());

    $zip = new ZipArchive;
    $zip->open($path);
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    File::delete($path);

    expect($sheet)->toContain('North Only')
        ->and($sheet)->not->toContain('South Only');
});
