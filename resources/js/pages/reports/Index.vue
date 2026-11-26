<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Archive,
    Building2,
    ChartBar,
    Clock,
    CreditCard,
    FileSpreadsheet,
    FileText,
    MapPin,
    Nfc,
    Printer,
    TrendingUp,
    UserCheck,
    Users,
    UserX,
} from '@lucide/vue';
import { computed } from 'vue';
import PaginationFooter from '@/components/PaginationFooter.vue';
import RegistrationTrendChart from '@/components/RegistrationTrendChart.vue';
import ReportFilters from '@/components/ReportFilters.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDate } from '@/lib/format';
import reports from '@/routes/reports';
import type {
    NfcReportData,
    RegistrationReportData,
    ReportData,
    ReportFilters as ReportFiltersType,
    ReportKey,
    ReportOptions,
    SummaryReportData,
    TerritoryReportData,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Reports',
                href: reports.index().url,
            },
        ],
    },
});

const props = defineProps<{
    report: ReportKey;
    filters: ReportFiltersType;
    options: ReportOptions;
    reportData: ReportData;
    generatedAt: string;
}>();

const summary = computed(() => props.reportData as SummaryReportData);
const territories = computed(() => props.reportData as TerritoryReportData);
const registrations = computed(
    () => props.reportData as RegistrationReportData,
);
const nfc = computed(() => props.reportData as NfcReportData);

const tabs: Array<{ key: ReportKey; label: string; icon: typeof Users }> = [
    { key: 'summary', label: 'Summary', icon: ChartBar },
    { key: 'territories', label: 'Hoods & territories', icon: MapPin },
    { key: 'registrations', label: 'Registrations', icon: TrendingUp },
    { key: 'nfc', label: 'NFC cards', icon: Nfc },
];

const reportTitle = computed(
    () => tabs.find((tab) => tab.key === props.report)?.label ?? 'Reports',
);

const generatedLabel = computed(() =>
    new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(props.generatedAt)),
);

function formatNumber(value: number): string {
    return new Intl.NumberFormat('en-PH').format(value);
}

function currentQuery(): Record<string, string | number | undefined> {
    return {
        report: props.report,
        start_date: props.filters.start_date ?? undefined,
        end_date: props.filters.end_date ?? undefined,
        branch: props.filters.branch ?? undefined,
        hood: props.filters.hood ?? undefined,
        membership_type: props.filters.membership_type ?? undefined,
        status: props.filters.status ?? undefined,
        card_status: props.filters.card_status ?? undefined,
        group:
            props.report === 'registrations' ? props.filters.group : undefined,
    };
}

function exportUrl(format: 'pdf' | 'xlsx'): string {
    return reports.export(format, { query: currentQuery() }).url;
}

function goToPage(page: number): void {
    router.get(
        reports.index.url(),
        {
            ...currentQuery(),
            page,
        },
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}

function printReport(): void {
    const cleanup = (): void => {
        document.body.classList.remove('report-printing');
        window.removeEventListener('afterprint', cleanup);
    };

    document.body.classList.add('report-printing');
    window.addEventListener('afterprint', cleanup);
    window.print();
}
</script>

<template>
    <Head :title="`${reportTitle} report`" />

    <div class="reports-page flex flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end"
        >
            <div>
                <p
                    class="font-mono text-[11px] tracking-[0.2em] text-muted-foreground uppercase"
                >
                    Temple Street / Reports &amp; analytics
                </p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight">
                    Reports &amp; analytics
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Live membership and NFC card reporting for
                    {{ options.isMainAdmin ? 'the whole network' : 'your territory' }}.
                    Generated {{ generatedLabel }}.
                </p>
            </div>

            <div class="no-print flex flex-wrap items-center gap-2">
                <Button variant="outline" size="sm" @click="printReport">
                    <Printer class="size-3.5" />
                    Print report
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <a :href="exportUrl('xlsx')" download>
                        <FileSpreadsheet class="size-3.5" />
                        Excel
                    </a>
                </Button>
                <Button size="sm" as-child>
                    <a :href="exportUrl('pdf')" download>
                        <FileText class="size-3.5" />
                        PDF
                    </a>
                </Button>
            </div>
        </div>

        <div class="hidden print:block">
            <p class="text-sm font-semibold">
                {{ reportTitle }} report
            </p>
            <p class="text-xs text-muted-foreground">
                Generated {{ generatedLabel }}
            </p>
        </div>

        <div
            class="no-print flex flex-wrap items-center gap-1 rounded-lg border bg-muted/30 p-1"
        >
            <Link
                v-for="tab in tabs"
                :key="tab.key"
                :href="reports.index({ query: { report: tab.key } }).url"
                class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                :class="
                    report === tab.key
                        ? 'bg-background text-foreground shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'
                "
                preserve-scroll
            >
                <component :is="tab.icon" class="size-4" />
                {{ tab.label }}
            </Link>
        </div>

        <Card class="no-print py-5">
            <CardContent>
                <ReportFilters
                    :report="report"
                    :filters="filters"
                    :options="options"
                />
            </CardContent>
        </Card>

        <!-- Membership summary -->
        <template v-if="report === 'summary'">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <Card class="py-5">
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle
                            class="font-mono text-[11px] font-medium tracking-[0.16em] text-muted-foreground uppercase"
                        >
                            Total members
                        </CardTitle>
                        <Users class="size-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-semibold tracking-tight">
                            {{ formatNumber(summary.stats.total) }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Current membership records
                        </p>
                    </CardContent>
                </Card>

                <Card class="py-5">
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle
                            class="font-mono text-[11px] font-medium tracking-[0.16em] text-muted-foreground uppercase"
                        >
                            Active members
                        </CardTitle>
                        <UserCheck class="size-4 text-emerald-500" />
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-semibold tracking-tight">
                            {{ formatNumber(summary.stats.active) }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{
                                summary.stats.total
                                    ? `${Math.round((summary.stats.active / summary.stats.total) * 100)}% of members`
                                    : 'No members yet'
                            }}
                        </p>
                    </CardContent>
                </Card>

                <Card class="py-5">
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle
                            class="font-mono text-[11px] font-medium tracking-[0.16em] text-muted-foreground uppercase"
                        >
                            Inactive members
                        </CardTitle>
                        <UserX class="size-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-semibold tracking-tight">
                            {{ formatNumber(summary.stats.inactive) }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Not currently active
                        </p>
                    </CardContent>
                </Card>

                <Card class="py-5">
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle
                            class="font-mono text-[11px] font-medium tracking-[0.16em] text-muted-foreground uppercase"
                        >
                            Pending members
                        </CardTitle>
                        <Clock class="size-4 text-amber-500" />
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-semibold tracking-tight">
                            {{ formatNumber(summary.stats.pending) }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Awaiting confirmation
                        </p>
                    </CardContent>
                </Card>

                <Card class="py-5">
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle
                            class="font-mono text-[11px] font-medium tracking-[0.16em] text-muted-foreground uppercase"
                        >
                            Total registered
                        </CardTitle>
                        <Archive class="size-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-semibold tracking-tight">
                            {{ formatNumber(summary.stats.registered) }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Includes {{ formatNumber(summary.stats.archived) }}
                            archived
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div class="grid gap-4 xl:grid-cols-5">
                <Card class="xl:col-span-3">
                    <CardHeader>
                        <CardTitle class="text-base">
                            Territory breakdown
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-y bg-muted/40 text-left">
                                        <th class="px-4 py-2.5 font-medium">
                                            Territory
                                        </th>
                                        <th class="px-4 py-2.5 text-right font-medium">
                                            Total
                                        </th>
                                        <th class="px-4 py-2.5 text-right font-medium">
                                            Active
                                        </th>
                                        <th class="px-4 py-2.5 text-right font-medium">
                                            Inactive
                                        </th>
                                        <th class="px-4 py-2.5 text-right font-medium">
                                            Pending
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="row in summary.territories"
                                        :key="row.id"
                                        class="border-b last:border-0"
                                    >
                                        <td class="px-4 py-3 font-medium">
                                            {{ row.name }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-mono">
                                            {{ formatNumber(row.total) }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-mono text-emerald-600 dark:text-emerald-400">
                                            {{ formatNumber(row.active) }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-mono text-muted-foreground">
                                            {{ formatNumber(row.inactive) }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-mono text-amber-600 dark:text-amber-400">
                                            {{ formatNumber(row.pending) }}
                                        </td>
                                    </tr>
                                    <tr v-if="!summary.territories.length">
                                        <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">
                                            No territories match the selected filters.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <Card class="xl:col-span-2">
                    <CardHeader>
                        <CardTitle class="text-base">
                            Membership types
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div
                            v-for="type in summary.types"
                            :key="type.type"
                            class="space-y-2"
                        >
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <StatusBadge :status="type.type" />
                                <span class="font-mono">{{ formatNumber(type.total) }}</span>
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-muted">
                                <div
                                    class="h-full rounded-full bg-emerald-500"
                                    :style="{
                                        width: `${summary.stats.total ? (type.total / summary.stats.total) * 100 : 0}%`,
                                    }"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </template>

        <!-- Hood and territory report -->
        <template v-else-if="report === 'territories'">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <Card class="py-5">
                    <CardHeader class="pb-2">
                        <CardTitle class="font-mono text-[11px] tracking-[0.16em] text-muted-foreground uppercase">
                            Total members
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-semibold">{{ formatNumber(territories.stats.total) }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ formatNumber(territories.stats.territories) }} territories ·
                            {{ formatNumber(territories.stats.hoods) }} hoods
                        </p>
                    </CardContent>
                </Card>
                <Card class="py-5">
                    <CardHeader class="pb-2">
                        <CardTitle class="font-mono text-[11px] tracking-[0.16em] text-muted-foreground uppercase">
                            Active
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-semibold text-emerald-600 dark:text-emerald-400">
                            {{ formatNumber(territories.stats.active) }}
                        </p>
                    </CardContent>
                </Card>
                <Card class="py-5">
                    <CardHeader class="pb-2">
                        <CardTitle class="font-mono text-[11px] tracking-[0.16em] text-muted-foreground uppercase">
                            Inactive
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-semibold">{{ formatNumber(territories.stats.inactive) }}</p>
                    </CardContent>
                </Card>
                <Card class="py-5">
                    <CardHeader class="pb-2">
                        <CardTitle class="font-mono text-[11px] tracking-[0.16em] text-muted-foreground uppercase">
                            Pending
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-semibold text-amber-600 dark:text-amber-400">
                            {{ formatNumber(territories.stats.pending) }}
                        </p>
                    </CardContent>
                </Card>
                <Card class="py-5">
                    <CardHeader class="pb-2">
                        <CardTitle class="font-mono text-[11px] tracking-[0.16em] text-muted-foreground uppercase">
                            Selected total
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-semibold">{{ formatNumber(territories.stats.total) }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ filters.branch ? 'Territory filter applied' : 'All territories' }}
                            {{ filters.hood ? ' · hood filter applied' : '' }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Territory totals</CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-y bg-muted/40 text-left">
                                    <th class="px-4 py-2.5 font-medium">Territory</th>
                                    <th class="px-4 py-2.5 text-right font-medium">Hoods</th>
                                    <th class="px-4 py-2.5 text-right font-medium">Members</th>
                                    <th class="px-4 py-2.5 text-right font-medium">Active</th>
                                    <th class="px-4 py-2.5 text-right font-medium">Inactive</th>
                                    <th class="px-4 py-2.5 text-right font-medium">Pending</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in territories.territoryTotals"
                                    :key="row.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="px-4 py-3 font-medium">{{ row.name }}</td>
                                    <td class="px-4 py-3 text-right font-mono">{{ formatNumber(row.hoods) }}</td>
                                    <td class="px-4 py-3 text-right font-mono">{{ formatNumber(row.total) }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-emerald-600 dark:text-emerald-400">
                                        {{ formatNumber(row.active) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-muted-foreground">
                                        {{ formatNumber(row.inactive) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-amber-600 dark:text-amber-400">
                                        {{ formatNumber(row.pending) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Hood detail</CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-y bg-muted/40 text-left">
                                    <th class="px-4 py-2.5 font-medium">Territory</th>
                                    <th class="px-4 py-2.5 font-medium">Hood</th>
                                    <th class="px-4 py-2.5 text-right font-medium">Total</th>
                                    <th class="px-4 py-2.5 text-right font-medium">Active</th>
                                    <th class="px-4 py-2.5 text-right font-medium">Inactive</th>
                                    <th class="px-4 py-2.5 text-right font-medium">Pending</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in territories.rows"
                                    :key="`${row.territory_id}-${row.hood_id ?? 'none'}`"
                                    class="border-b last:border-0"
                                >
                                    <td class="px-4 py-3">{{ row.territory }}</td>
                                    <td class="px-4 py-3 font-medium">
                                        {{ row.hood }}
                                        <Badge
                                            v-if="row.hood_id === null"
                                            variant="outline"
                                            class="ml-1 text-[10px]"
                                        >
                                            Unassigned
                                        </Badge>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono">{{ formatNumber(row.total) }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-emerald-600 dark:text-emerald-400">
                                        {{ formatNumber(row.active) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-muted-foreground">
                                        {{ formatNumber(row.inactive) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-amber-600 dark:text-amber-400">
                                        {{ formatNumber(row.pending) }}
                                    </td>
                                </tr>
                                <tr v-if="!territories.rows.length">
                                    <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">
                                        No hoods match the selected filters.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </template>

        <!-- Registration report -->
        <template v-else-if="report === 'registrations'">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <Card class="py-5">
                    <CardHeader class="pb-2">
                        <CardTitle class="font-mono text-[11px] tracking-[0.16em] text-muted-foreground uppercase">
                            Registrations
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-semibold">{{ formatNumber(registrations.stats.total) }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">In selected period</p>
                    </CardContent>
                </Card>
                <Card class="py-5">
                    <CardHeader class="pb-2">
                        <CardTitle class="font-mono text-[11px] tracking-[0.16em] text-muted-foreground uppercase">
                            Active
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-semibold text-emerald-600 dark:text-emerald-400">
                            {{ formatNumber(registrations.stats.active) }}
                        </p>
                    </CardContent>
                </Card>
                <Card class="py-5">
                    <CardHeader class="pb-2">
                        <CardTitle class="font-mono text-[11px] tracking-[0.16em] text-muted-foreground uppercase">
                            Inactive
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-semibold">{{ formatNumber(registrations.stats.inactive) }}</p>
                    </CardContent>
                </Card>
                <Card class="py-5">
                    <CardHeader class="pb-2">
                        <CardTitle class="font-mono text-[11px] tracking-[0.16em] text-muted-foreground uppercase">
                            Pending
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-semibold text-amber-600 dark:text-amber-400">
                            {{ formatNumber(registrations.stats.pending) }}
                        </p>
                    </CardContent>
                </Card>
                <Card class="py-5">
                    <CardHeader class="pb-2">
                        <CardTitle class="font-mono text-[11px] tracking-[0.16em] text-muted-foreground uppercase">
                            Trend periods
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-semibold">{{ formatNumber(registrations.stats.periods) }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Grouped by {{ filters.group }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Registration trend</CardTitle>
                </CardHeader>
                <CardContent>
                    <RegistrationTrendChart :points="registrations.trend" />
                </CardContent>
            </Card>

            <div class="grid gap-4 xl:grid-cols-3">
                <Card class="xl:col-span-1">
                    <CardHeader>
                        <CardTitle class="text-base">Grouped registrations</CardTitle>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div class="max-h-96 overflow-auto">
                            <table class="w-full text-sm">
                                <thead class="sticky top-0 bg-muted/90 backdrop-blur">
                                    <tr class="border-y text-left">
                                        <th class="px-4 py-2.5 font-medium">Period</th>
                                        <th class="px-4 py-2.5 text-right font-medium">Total</th>
                                        <th class="px-4 py-2.5 text-right font-medium">Active</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="point in [...registrations.trend].reverse()"
                                        :key="point.period"
                                        class="border-b last:border-0"
                                    >
                                        <td class="px-4 py-2.5">{{ point.label }}</td>
                                        <td class="px-4 py-2.5 text-right font-mono">{{ formatNumber(point.total) }}</td>
                                        <td class="px-4 py-2.5 text-right font-mono text-emerald-600 dark:text-emerald-400">
                                            {{ formatNumber(point.active) }}
                                        </td>
                                    </tr>
                                    <tr v-if="!registrations.trend.length">
                                        <td colspan="3" class="px-4 py-8 text-center text-muted-foreground">
                                            No registrations match the filters.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <Card class="xl:col-span-2">
                    <CardHeader>
                        <CardTitle class="text-base">Newly registered members</CardTitle>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-y bg-muted/40 text-left">
                                        <th class="px-4 py-2.5 font-medium">Member</th>
                                        <th class="px-4 py-2.5 font-medium">Territory</th>
                                        <th class="px-4 py-2.5 font-medium">Hood</th>
                                        <th class="px-4 py-2.5 font-medium">Type</th>
                                        <th class="px-4 py-2.5 font-medium">Registered</th>
                                        <th class="px-4 py-2.5 font-medium">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="member in registrations.records.data"
                                        :key="member.id"
                                        class="border-b last:border-0"
                                    >
                                        <td class="px-4 py-3 font-medium">{{ member.full_name }}</td>
                                        <td class="px-4 py-3">{{ member.branch?.name ?? '—' }}</td>
                                        <td class="px-4 py-3">{{ member.hood?.name ?? '—' }}</td>
                                        <td class="px-4 py-3">{{ member.membership_type }}</td>
                                        <td class="px-4 py-3 font-mono text-xs">{{ formatDate(member.join_date) }}</td>
                                        <td class="px-4 py-3"><StatusBadge :status="member.status" /></td>
                                    </tr>
                                    <tr v-if="!registrations.records.data.length">
                                        <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">
                                            No members registered in this period.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-if="registrations.records.total > 0" class="border-t">
                            <PaginationFooter
                                :from="registrations.records.from"
                                :to="registrations.records.to"
                                :total="registrations.records.total"
                                :current-page="registrations.records.current_page"
                                :last-page="registrations.records.last_page"
                                @previous="goToPage(registrations.records.current_page - 1)"
                                @next="goToPage(registrations.records.current_page + 1)"
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </template>

        <!-- NFC report -->
        <template v-else>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                <Card class="py-5">
                    <CardHeader class="pb-2">
                        <CardTitle class="font-mono text-[11px] tracking-[0.16em] text-muted-foreground uppercase">
                            Total NFC cards
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-semibold">{{ formatNumber(nfc.stats.total) }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">Issued card records</p>
                    </CardContent>
                </Card>
                <Card class="py-5">
                    <CardHeader class="pb-2">
                        <CardTitle class="font-mono text-[11px] tracking-[0.16em] text-muted-foreground uppercase">
                            Assigned
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-semibold">{{ formatNumber(nfc.stats.assigned) }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">Linked to members</p>
                    </CardContent>
                </Card>
                <Card class="py-5">
                    <CardHeader class="pb-2">
                        <CardTitle class="font-mono text-[11px] tracking-[0.16em] text-muted-foreground uppercase">
                            Unassigned
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-semibold">{{ formatNumber(nfc.stats.unassigned) }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">Members without a card</p>
                    </CardContent>
                </Card>
                <Card class="py-5">
                    <CardHeader class="pb-2">
                        <CardTitle class="font-mono text-[11px] tracking-[0.16em] text-muted-foreground uppercase">
                            Active
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-semibold text-emerald-600 dark:text-emerald-400">
                            {{ formatNumber(nfc.stats.active) }}
                        </p>
                    </CardContent>
                </Card>
                <Card class="py-5">
                    <CardHeader class="pb-2">
                        <CardTitle class="font-mono text-[11px] tracking-[0.16em] text-muted-foreground uppercase">
                            Inactive
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-semibold">{{ formatNumber(nfc.stats.inactive) }}</p>
                    </CardContent>
                </Card>
                <Card class="py-5">
                    <CardHeader class="pb-2">
                        <CardTitle class="font-mono text-[11px] tracking-[0.16em] text-muted-foreground uppercase">
                            Lost
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-semibold text-rose-600 dark:text-rose-400">
                            {{ formatNumber(nfc.stats.lost) }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div class="flex items-start gap-2 rounded-lg border bg-muted/30 px-4 py-3 text-xs text-muted-foreground">
                <CreditCard class="mt-0.5 size-4 shrink-0" />
                <p>
                    Every NFC card record must be linked to a member. “Unassigned” therefore
                    counts members who do not yet have an NFC card, not unattached card records.
                    {{ formatNumber(nfc.stats.members) }} members are in scope.
                </p>
            </div>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-base">NFC card assignments</CardTitle>
                    <Badge variant="outline" class="font-mono text-[11px]">
                        {{ formatNumber(nfc.cards.length) }} shown
                    </Badge>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-y bg-muted/40 text-left">
                                    <th class="px-4 py-2.5 font-medium">Member</th>
                                    <th class="px-4 py-2.5 font-medium">NFC card UID</th>
                                    <th class="px-4 py-2.5 font-medium">Card status</th>
                                    <th class="px-4 py-2.5 font-medium">Assignment date</th>
                                    <th class="px-4 py-2.5 font-medium">Territory</th>
                                    <th class="px-4 py-2.5 font-medium">Hood</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="card in nfc.cards"
                                    :key="card.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="px-4 py-3 font-medium">{{ card.member_name }}</td>
                                    <td class="px-4 py-3 font-mono text-xs">{{ card.card_uid }}</td>
                                    <td class="px-4 py-3"><StatusBadge :status="card.card_status" /></td>
                                    <td class="px-4 py-3 font-mono text-xs">
                                        {{ formatDate(card.issued_date) }}
                                    </td>
                                    <td class="px-4 py-3">{{ card.territory ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ card.hood ?? '—' }}</td>
                                </tr>
                                <tr v-if="!nfc.cards.length">
                                    <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">
                                        No NFC cards match the selected filters.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </template>

        <div class="flex items-center gap-2 text-xs text-muted-foreground">
            <Building2 class="size-3.5" />
            <span>
                Reports are generated from live database records. Registration date uses the
                member's join date.
            </span>
        </div>
    </div>
</template>
