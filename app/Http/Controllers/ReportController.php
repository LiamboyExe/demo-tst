<?php

namespace App\Http\Controllers;

use App\Actions\Reports\GenerateReportXlsx;
use App\Enums\MembershipType;
use App\Enums\MemberStatus;
use App\Enums\NfcCardStatus;
use App\Models\Branch;
use App\Models\Hood;
use App\Models\Member;
use App\Models\NfcCard;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Reports available in the module.
     *
     * @var array<int, string>
     */
    private const REPORTS = ['summary', 'territories', 'registrations', 'nfc'];

    /**
     * Show the selected report with its filters and calculated data.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $report = $this->reportKey($request);
        $filters = $this->validatedFilters($request, $user);

        return Inertia::render('reports/Index', [
            'report' => $report,
            'filters' => $filters,
            'options' => $this->filterOptions($user),
            'reportData' => $this->buildReport($user, $report, $filters),
            'generatedAt' => now()->toIso8601String(),
        ]);
    }

    /**
     * Download the selected report as PDF or Excel, using the active filters.
     */
    public function export(Request $request, string $format): StreamedResponse
    {
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);

        $user = $request->user();
        $report = $this->reportKey($request);
        $filters = $this->validatedFilters($request, $user);
        $document = $this->exportDocument(
            $report,
            $filters,
            $this->reportSections($user, $report, $filters),
        );
        $filename = sprintf(
            '%s-report-%s',
            $report,
            now()->format('Y-m-d-His'),
        );

        if ($format === 'pdf') {
            return response()->streamDownload(
                function () use ($document): void {
                    echo Pdf::loadView('reports.pdf', ['document' => $document])
                        ->setPaper('a4', 'landscape')
                        ->output();
                },
                $filename.'.pdf',
                ['Content-Type' => 'application/pdf'],
            );
        }

        $path = (new GenerateReportXlsx)($document);

        return response()->streamDownload(
            function () use ($path): void {
                readfile($path);
                @unlink($path);
            },
            $filename.'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * Validate the report query string.
     *
     * @return array<string, mixed>
     */
    private function validatedFilters(Request $request, User $user): array
    {
        $validated = Validator::make($request->query(), [
            'report' => ['nullable', Rule::in(self::REPORTS)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'branch' => ['nullable', 'integer', 'exists:branches,id'],
            'hood' => ['nullable', 'integer', 'exists:hoods,id'],
            'membership_type' => ['nullable', Rule::in(MembershipType::values())],
            'status' => ['nullable', Rule::in(MemberStatus::values())],
            'card_status' => ['nullable', Rule::in(NfcCardStatus::values())],
            'group' => ['nullable', Rule::in(['day', 'month', 'year'])],
        ])->validate();

        $branch = $user->isBranchAdmin()
            ? $user->branch_id
            : (filled($validated['branch'] ?? null) ? (int) $validated['branch'] : null);
        $hood = filled($validated['hood'] ?? null) ? (int) $validated['hood'] : null;

        if ($hood !== null && $branch !== null) {
            $belongsToBranch = Hood::query()
                ->whereKey($hood)
                ->where('branch_id', $branch)
                ->exists();

            if (! $belongsToBranch) {
                throw ValidationException::withMessages([
                    'hood' => __('The selected hood does not belong to the selected territory.'),
                ]);
            }
        }

        return [
            'report' => $validated['report'] ?? 'summary',
            'start_date' => filled($validated['start_date'] ?? null) ? $validated['start_date'] : null,
            'end_date' => filled($validated['end_date'] ?? null) ? $validated['end_date'] : null,
            'branch' => $branch,
            'hood' => $hood,
            'membership_type' => filled($validated['membership_type'] ?? null) ? $validated['membership_type'] : null,
            'status' => filled($validated['status'] ?? null) ? $validated['status'] : null,
            'card_status' => filled($validated['card_status'] ?? null) ? $validated['card_status'] : null,
            'group' => $validated['group'] ?? 'month',
        ];
    }

    /**
     * Resolve the requested report tab.
     */
    private function reportKey(Request $request): string
    {
        $report = (string) $request->query('report', 'summary');

        return in_array($report, self::REPORTS, true) ? $report : 'summary';
    }

    /**
     * Options shared by the filter controls.
     *
     * @return array<string, mixed>
     */
    private function filterOptions(User $user): array
    {
        $branches = Branch::query()
            ->when(! $user->isMainAdmin(), fn (Builder $query) => $query->whereKey($user->branch_id))
            ->with(['hoods' => fn ($query) => $query->orderBy('name')->select(['id', 'branch_id', 'name'])])
            ->orderBy('name')
            ->get(['id', 'name']);

        return [
            'branches' => $branches,
            'membershipTypes' => MembershipType::values(),
            'statuses' => MemberStatus::values(),
            'cardStatuses' => NfcCardStatus::values(),
            'groups' => ['day', 'month', 'year'],
            'isMainAdmin' => $user->isMainAdmin(),
        ];
    }

    /**
     * Build the data needed by the selected report.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildReport(User $user, string $report, array $filters): array
    {
        return match ($report) {
            'territories' => $this->territoryReport($user, $filters),
            'registrations' => $this->registrationReport($user, $filters),
            'nfc' => $this->nfcReport($user, $filters),
            default => $this->summaryReport($user, $filters),
        };
    }

    /**
     * Build the sections shared by PDF and Excel exports.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function reportSections(User $user, string $report, array $filters): array
    {
        return match ($report) {
            'territories' => $this->territorySections($this->territoryReport($user, $filters)),
            'registrations' => $this->registrationSections($this->registrationReport($user, $filters, forExport: true)),
            'nfc' => $this->nfcSections($this->nfcReport($user, $filters)),
            default => $this->summarySections($this->summaryReport($user, $filters)),
        };
    }

    /**
     * Membership summary counts and breakdowns.
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     stats: array{total: int, active: int, inactive: int, pending: int, registered: int, archived: int},
     *     territories: array<int, array{id: int, name: string, total: int, active: int, inactive: int, pending: int}>,
     *     types: array<int, array{type: string, total: int}>
     * }
     */
    private function summaryReport(User $user, array $filters): array
    {
        $query = Member::query()->visibleTo($user);
        $this->applyBranchHoodType($query, $filters);

        $statusCounts = (clone $query)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count): int => (int) $count);

        $total = (int) $statusCounts->sum();

        $registeredQuery = Member::withTrashed()->visibleTo($user);
        $this->applyBranchHoodType($registeredQuery, $filters);
        $registered = $registeredQuery->count();

        $branches = Branch::query()
            ->when($filters['branch'] !== null, fn (Builder $query) => $query->whereKey($filters['branch']))
            ->when(! $user->isMainAdmin(), fn (Builder $query) => $query->whereKey($user->branch_id))
            ->orderBy('name')
            ->get(['id', 'name']);

        $branchCounts = (clone $query)
            ->selectRaw('branch_id, status, count(*) as aggregate')
            ->groupBy('branch_id', 'status')
            ->get();

        $territories = $branches->map(function (Branch $branch) use ($branchCounts): array {
            $counts = $branchCounts->where('branch_id', $branch->id);

            return [
                'id' => $branch->id,
                'name' => $branch->name,
                'total' => (int) $counts->sum('aggregate'),
                'active' => (int) $counts->where('status', MemberStatus::Active->value)->sum('aggregate'),
                'inactive' => (int) $counts->where('status', MemberStatus::Inactive->value)->sum('aggregate'),
                'pending' => (int) $counts->where('status', MemberStatus::Pending->value)->sum('aggregate'),
            ];
        })->values();

        $typeCounts = (clone $query)
            ->selectRaw('membership_type, count(*) as aggregate')
            ->groupBy('membership_type')
            ->pluck('aggregate', 'membership_type');

        $types = collect(MembershipType::values())
            ->map(fn (string $type): array => [
                'type' => $type,
                'total' => (int) ($typeCounts[$type] ?? 0),
            ])
            ->values();

        return [
            'stats' => [
                'total' => $total,
                'active' => (int) ($statusCounts[MemberStatus::Active->value] ?? 0),
                'inactive' => (int) ($statusCounts[MemberStatus::Inactive->value] ?? 0),
                'pending' => (int) ($statusCounts[MemberStatus::Pending->value] ?? 0),
                'registered' => $registered,
                'archived' => max(0, $registered - $total),
            ],
            'territories' => $territories->all(),
            'types' => $types->all(),
        ];
    }

    /**
     * Member distribution by territory and hood.
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     stats: array{total: int, active: int, inactive: int, pending: int, hoods: int, territories: int},
     *     rows: array<int, array{territory_id: int, territory: string, hood_id: int|null, hood: string, total: int, active: int, inactive: int, pending: int}>,
     *     territoryTotals: array<int, array{id: int, name: string, total: int, active: int, inactive: int, pending: int, hoods: int}>
     * }
     */
    private function territoryReport(User $user, array $filters): array
    {
        $hoods = Hood::query()
            ->with('branch:id,name')
            ->when($filters['branch'] !== null, fn (Builder $query) => $query->where('branch_id', $filters['branch']))
            ->when($filters['hood'] !== null, fn (Builder $query) => $query->whereKey($filters['hood']))
            ->when(! $user->isMainAdmin(), fn (Builder $query) => $query->where('branch_id', $user->branch_id))
            ->withCount([
                'members as total_members',
                'members as active_members' => fn (Builder $query) => $query->where('status', MemberStatus::Active),
                'members as inactive_members' => fn (Builder $query) => $query->where('status', MemberStatus::Inactive),
                'members as pending_members' => fn (Builder $query) => $query->where('status', MemberStatus::Pending),
            ])
            ->orderBy('name')
            ->get();

        $rows = $hoods->map(fn (Hood $hood): array => $this->territoryRow(
            territoryId: $hood->branch_id,
            territory: $hood->branch->name,
            hoodId: $hood->id,
            hood: $hood->name,
            total: (int) $hood->getAttribute('total_members'),
            active: (int) $hood->getAttribute('active_members'),
            inactive: (int) $hood->getAttribute('inactive_members'),
            pending: (int) $hood->getAttribute('pending_members'),
        ))->values();

        if ($filters['hood'] === null) {
            $unassigned = Member::query()
                ->visibleTo($user)
                ->whereNull('hood_id')
                ->when($filters['branch'] !== null, fn (Builder $query) => $query->where('branch_id', $filters['branch']))
                ->selectRaw('branch_id, status, count(*) as aggregate')
                ->groupBy('branch_id', 'status')
                ->get();

            if ($unassigned->isNotEmpty()) {
                $branchNames = Branch::query()
                    ->whereIn('id', $unassigned->pluck('branch_id')->unique())
                    ->pluck('name', 'id');

                $unassigned->groupBy('branch_id')->each(function ($counts, $branchId) use (&$rows, $branchNames): void {
                    $rows->push($this->territoryRow(
                        territoryId: (int) $branchId,
                        territory: $branchNames[$branchId] ?? 'Unassigned',
                        hoodId: null,
                        hood: 'No hood assigned',
                        total: (int) $counts->sum('aggregate'),
                        active: (int) $counts->where('status', MemberStatus::Active->value)->sum('aggregate'),
                        inactive: (int) $counts->where('status', MemberStatus::Inactive->value)->sum('aggregate'),
                        pending: (int) $counts->where('status', MemberStatus::Pending->value)->sum('aggregate'),
                    ));
                });
            }
        }

        $branchNames = Branch::query()
            ->when($filters['branch'] !== null, fn (Builder $query) => $query->whereKey($filters['branch']))
            ->when(! $user->isMainAdmin(), fn (Builder $query) => $query->whereKey($user->branch_id))
            ->orderBy('name')
            ->pluck('name', 'id');

        $territoryTotals = $branchNames->map(function (string $name, int $id) use ($rows): array {
            $territoryRows = $rows->where('territory_id', $id);

            return [
                'id' => $id,
                'name' => $name,
                'total' => (int) $territoryRows->sum('total'),
                'active' => (int) $territoryRows->sum('active'),
                'inactive' => (int) $territoryRows->sum('inactive'),
                'pending' => (int) $territoryRows->sum('pending'),
                'hoods' => $territoryRows->whereNotNull('hood_id')->count(),
            ];
        })->values();

        $rows = $rows
            ->sortBy([['territory', 'asc'], ['hood', 'asc']])
            ->values();

        return [
            'stats' => [
                'total' => (int) $rows->sum('total'),
                'active' => (int) $rows->sum('active'),
                'inactive' => (int) $rows->sum('inactive'),
                'pending' => (int) $rows->sum('pending'),
                'hoods' => $rows->whereNotNull('hood_id')->count(),
                'territories' => $territoryTotals->count(),
            ],
            'rows' => $rows->all(),
            'territoryTotals' => $territoryTotals->all(),
        ];
    }

    /**
     * @return array{
     *     territory_id: int,
     *     territory: string,
     *     hood_id: int|null,
     *     hood: string,
     *     total: int,
     *     active: int,
     *     inactive: int,
     *     pending: int
     * }
     */
    private function territoryRow(
        int $territoryId,
        string $territory,
        ?int $hoodId,
        string $hood,
        int $total,
        int $active,
        int $inactive,
        int $pending,
    ): array {
        return [
            'territory_id' => $territoryId,
            'territory' => $territory,
            'hood_id' => $hoodId,
            'hood' => $hood,
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'pending' => $pending,
        ];
    }

    /**
     * New member registrations, trends, and filters.
     *
     * @param  array<string, mixed>  $filters
     * @return ($forExport is true
     *     ? array{stats: array{total: int, active: int, inactive: int, pending: int, periods: int}, trend: array<int, array{period: string, label: string, total: int, active: int, inactive: int, pending: int}>, records: array<int, Member>}
     *     : array{stats: array{total: int, active: int, inactive: int, pending: int, periods: int}, trend: array<int, array{period: string, label: string, total: int, active: int, inactive: int, pending: int}>, records: LengthAwarePaginator<int, Member>})
     */
    private function registrationReport(User $user, array $filters, bool $forExport = false): array
    {
        $query = Member::query()
            ->visibleTo($user)
            ->when($filters['branch'] !== null, fn (Builder $query) => $query->where('branch_id', $filters['branch']))
            ->when($filters['hood'] !== null, fn (Builder $query) => $query->where('hood_id', $filters['hood']))
            ->when($filters['membership_type'] !== null, fn (Builder $query) => $query->where('membership_type', $filters['membership_type']))
            ->when($filters['status'] !== null, fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['start_date'] !== null, fn (Builder $query) => $query->whereDate('join_date', '>=', $filters['start_date']))
            ->when($filters['end_date'] !== null, fn (Builder $query) => $query->whereDate('join_date', '<=', $filters['end_date']));

        $statusCounts = (clone $query)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $trendSource = (clone $query)
            ->select(['id', 'join_date', 'status'])
            ->orderBy('join_date')
            ->get();

        $format = match ($filters['group']) {
            'day' => 'Y-m-d',
            'year' => 'Y',
            default => 'Y-m',
        };

        $trend = $trendSource
            ->groupBy(fn (Member $member): string => $member->join_date->format($format))
            ->map(function ($members, string $period) use ($filters): array {
                return [
                    'period' => $period,
                    'label' => match ($filters['group']) {
                        'day' => Carbon::parse($period)->format('M j, Y'),
                        'year' => $period,
                        default => Carbon::parse($period.'-01')->format('M Y'),
                    },
                    'total' => $members->count(),
                    'active' => $members->where('status', MemberStatus::Active)->count(),
                    'inactive' => $members->where('status', MemberStatus::Inactive)->count(),
                    'pending' => $members->where('status', MemberStatus::Pending)->count(),
                ];
            })
            ->sortKeys()
            ->values();

        $records = (clone $query)
            ->select(['id', 'first_name', 'last_name', 'branch_id', 'hood_id', 'membership_type', 'join_date', 'status'])
            ->with(['branch:id,name', 'hood:id,name'])
            ->orderByDesc('join_date')
            ->orderByDesc('id');

        return [
            'stats' => [
                'total' => (int) $statusCounts->sum(),
                'active' => (int) ($statusCounts[MemberStatus::Active->value] ?? 0),
                'inactive' => (int) ($statusCounts[MemberStatus::Inactive->value] ?? 0),
                'pending' => (int) ($statusCounts[MemberStatus::Pending->value] ?? 0),
                'periods' => $trend->count(),
            ],
            'trend' => $trend->all(),
            'records' => $forExport
                ? $records->get()->all()
                : $records->paginate(25)->withQueryString(),
        ];
    }

    /**
     * NFC card assignment statistics and records.
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     stats: array{total: int, assigned: int, unassigned: int, members: int, active: int, inactive: int, lost: int},
     *     cards: array<int, array{id: int, member_name: string, member_status: string, card_uid: string, card_status: string, issued_date: string, territory: string, hood: string|null}>
     * }
     */
    private function nfcReport(User $user, array $filters): array
    {
        $cardsInScope = NfcCard::query()
            ->whereHas('member', fn (Builder $query) => $query
                ->visibleTo($user)
                ->when($filters['branch'] !== null, fn (Builder $query) => $query->where('branch_id', $filters['branch'])));

        $cardStatusCounts = (clone $cardsInScope)
            ->selectRaw('card_status, count(*) as aggregate')
            ->groupBy('card_status')
            ->pluck('aggregate', 'card_status');

        $cards = (clone $cardsInScope)
            ->when($filters['card_status'] !== null, fn (Builder $query) => $query->where('card_status', $filters['card_status']))
            ->with(['member:id,first_name,last_name,branch_id,hood_id,status', 'member.branch:id,name', 'member.hood:id,name'])
            ->orderByDesc('issued_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (NfcCard $card): array => [
                'id' => $card->id,
                'member_name' => $card->member->full_name,
                'member_status' => $card->member->status->value,
                'card_uid' => $card->card_uid,
                'card_status' => $card->card_status->value,
                'issued_date' => $card->issued_date->toDateString(),
                'territory' => $card->member->branch->name,
                'hood' => $card->member->hood?->name,
            ])
            ->values()
            ->all();

        $membersInScope = Member::query()
            ->visibleTo($user)
            ->when($filters['branch'] !== null, fn (Builder $query) => $query->where('branch_id', $filters['branch']));

        return [
            'stats' => [
                'total' => (int) $cardStatusCounts->sum(),
                'assigned' => (int) $cardStatusCounts->sum(),
                'unassigned' => (clone $membersInScope)->doesntHave('nfcCard')->count(),
                'members' => (clone $membersInScope)->count(),
                'active' => (int) ($cardStatusCounts[NfcCardStatus::Active->value] ?? 0),
                'inactive' => (int) ($cardStatusCounts[NfcCardStatus::Inactive->value] ?? 0),
                'lost' => (int) ($cardStatusCounts[NfcCardStatus::Lost->value] ?? 0),
            ],
            'cards' => $cards,
        ];
    }

    /**
     * Apply the filters shared by the summary report.
     *
     * @param  Builder<Member>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyBranchHoodType(Builder $query, array $filters): void
    {
        $query
            ->when($filters['branch'] !== null, fn (Builder $query) => $query->where('branch_id', $filters['branch']))
            ->when($filters['hood'] !== null, fn (Builder $query) => $query->where('hood_id', $filters['hood']))
            ->when($filters['membership_type'] !== null, fn (Builder $query) => $query->where('membership_type', $filters['membership_type']));
    }

    /**
     * Turn report sections into the shared document shape used by PDF and Excel.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<string, mixed>
     */
    private function exportDocument(string $report, array $filters, array $sections): array
    {
        $title = match ($report) {
            'territories' => 'Members by Hood and Territory',
            'registrations' => 'Membership Registration Report',
            'nfc' => 'NFC Card Report',
            default => 'Membership Summary Report',
        };

        return [
            'title' => $title,
            'app_name' => config('app.name'),
            'generated_at' => now()->format('F j, Y g:i A'),
            'filters' => $this->filterLabels($filters),
            'sections' => $sections,
            'notes' => $report === 'nfc'
                ? ['Unassigned cards are members without an assigned NFC card. The NFC card table requires a member for every card, so cards cannot exist unassigned.']
                : [],
        ];
    }

    /**
     * @param  array{
     *     stats: array{total: int, active: int, inactive: int, pending: int, registered: int, archived: int},
     *     territories: array<int, array{name: string, total: int, active: int, inactive: int, pending: int}>,
     *     types: array<int, array{type: string, total: int}>
     * }  $data
     * @return array<int, array<string, mixed>>
     */
    private function summarySections(array $data): array
    {
        return [
            $this->section('Summary', ['Metric', 'Value'], [
                ['Total members', $data['stats']['total']],
                ['Active members', $data['stats']['active']],
                ['Inactive members', $data['stats']['inactive']],
                ['Pending members', $data['stats']['pending']],
                ['Total registered members', $data['stats']['registered']],
                ['Archived members', $data['stats']['archived']],
            ]),
            $this->section(
                'Territory breakdown',
                ['Territory', 'Total', 'Active', 'Inactive', 'Pending'],
                array_map(fn (array $row): array => [
                    $row['name'], $row['total'], $row['active'], $row['inactive'], $row['pending'],
                ], $data['territories']),
            ),
            $this->section(
                'Membership type breakdown',
                ['Membership type', 'Total'],
                array_map(fn (array $row): array => [$row['type'], $row['total']], $data['types']),
            ),
        ];
    }

    /**
     * @param  array{
     *     stats: array{total: int, active: int, inactive: int, pending: int, hoods: int, territories: int},
     *     rows: array<int, array{territory: string, hood: string, total: int, active: int, inactive: int, pending: int}>,
     *     territoryTotals: array<int, array{name: string, hoods: int, total: int, active: int, inactive: int, pending: int}>
     * }  $data
     * @return array<int, array<string, mixed>>
     */
    private function territorySections(array $data): array
    {
        return [
            $this->section('Summary', ['Metric', 'Value'], [
                ['Total members', $data['stats']['total']],
                ['Active members', $data['stats']['active']],
                ['Inactive members', $data['stats']['inactive']],
                ['Pending members', $data['stats']['pending']],
                ['Territories', $data['stats']['territories']],
                ['Hoods', $data['stats']['hoods']],
            ]),
            $this->section(
                'Territory totals',
                ['Territory', 'Hoods', 'Total', 'Active', 'Inactive', 'Pending'],
                array_map(fn (array $row): array => [
                    $row['name'], $row['hoods'], $row['total'], $row['active'], $row['inactive'], $row['pending'],
                ], $data['territoryTotals']),
            ),
            $this->section(
                'Hood detail',
                ['Territory', 'Hood', 'Total', 'Active', 'Inactive', 'Pending'],
                array_map(fn (array $row): array => [
                    $row['territory'], $row['hood'], $row['total'], $row['active'], $row['inactive'], $row['pending'],
                ], $data['rows']),
            ),
        ];
    }

    /**
     * @param  array{
     *     stats: array{total: int, active: int, inactive: int, pending: int, periods: int},
     *     trend: array<int, array{label: string, total: int, active: int, inactive: int, pending: int}>,
     *     records: array<int, Member>
     * }  $data
     * @return array<int, array<string, mixed>>
     */
    private function registrationSections(array $data): array
    {
        return [
            $this->section('Summary', ['Metric', 'Value'], [
                ['Registrations', $data['stats']['total']],
                ['Active members', $data['stats']['active']],
                ['Inactive members', $data['stats']['inactive']],
                ['Pending members', $data['stats']['pending']],
                ['Periods in trend', $data['stats']['periods']],
            ]),
            $this->section(
                'Registration trend',
                ['Period', 'Total', 'Active', 'Inactive', 'Pending'],
                array_map(fn (array $row): array => [
                    $row['label'], $row['total'], $row['active'], $row['inactive'], $row['pending'],
                ], $data['trend']),
            ),
            $this->section(
                'Member registrations',
                ['Member', 'Territory', 'Hood', 'Membership type', 'Registration date', 'Status'],
                array_map(fn (Member $member): array => [
                    $member->full_name,
                    $member->branch->name,
                    $member->hood->name ?? '',
                    $member->membership_type->value,
                    $member->join_date->toDateString(),
                    $member->status->value,
                ], $data['records']),
            ),
        ];
    }

    /**
     * @param  array{
     *     stats: array{total: int, assigned: int, unassigned: int, active: int, inactive: int, lost: int},
     *     cards: array<int, array{member_name: string, card_uid: string, card_status: string, issued_date: string, territory: string, hood: string|null}>
     * }  $data
     * @return array<int, array<string, mixed>>
     */
    private function nfcSections(array $data): array
    {
        return [
            $this->section('Summary', ['Metric', 'Value'], [
                ['Total NFC cards', $data['stats']['total']],
                ['Assigned NFC cards', $data['stats']['assigned']],
                ['Unassigned NFC cards', $data['stats']['unassigned']],
                ['Active NFC cards', $data['stats']['active']],
                ['Inactive NFC cards', $data['stats']['inactive']],
                ['Lost NFC cards', $data['stats']['lost']],
            ]),
            $this->section(
                'NFC card assignments',
                ['Member', 'NFC card UID', 'Card status', 'Assignment date', 'Territory', 'Hood'],
                array_map(fn (array $row): array => [
                    $row['member_name'], $row['card_uid'], $row['card_status'], $row['issued_date'],
                    $row['territory'], $row['hood'] ?? '',
                ], $data['cards']),
            ),
        ];
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function section(string $heading, array $headers, array $rows): array
    {
        return [
            'heading' => $heading,
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    /**
     * Human-readable active filters for exports.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array{label: string, value: string}>
     */
    private function filterLabels(array $filters): array
    {
        $labels = [];

        if ($filters['start_date'] !== null || $filters['end_date'] !== null) {
            $labels[] = [
                'label' => 'Registration period',
                'value' => sprintf(
                    '%s to %s',
                    $filters['start_date'] ? Carbon::parse($filters['start_date'])->format('M j, Y') : 'Beginning',
                    $filters['end_date'] ? Carbon::parse($filters['end_date'])->format('M j, Y') : 'Present',
                ),
            ];
        }

        if ($filters['branch'] !== null) {
            $labels[] = [
                'label' => 'Territory',
                'value' => Branch::query()->whereKey($filters['branch'])->value('name') ?? 'Unknown',
            ];
        }

        if ($filters['hood'] !== null) {
            $labels[] = [
                'label' => 'Hood',
                'value' => Hood::query()->whereKey($filters['hood'])->value('name') ?? 'Unknown',
            ];
        }

        if ($filters['membership_type'] !== null) {
            $labels[] = ['label' => 'Membership type', 'value' => $filters['membership_type']];
        }

        if ($filters['status'] !== null) {
            $labels[] = ['label' => 'Membership status', 'value' => $filters['status']];
        }

        if ($filters['card_status'] !== null) {
            $labels[] = ['label' => 'Card status', 'value' => $filters['card_status']];
        }

        if ($filters['report'] === 'registrations') {
            $labels[] = ['label' => 'Trend grouping', 'value' => ucfirst($filters['group'])];
        }

        if ($labels === []) {
            $labels[] = ['label' => 'Filters', 'value' => 'All records'];
        }

        return $labels;
    }
}
