<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Filter, RotateCcw } from '@lucide/vue';
import { computed, reactive, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import reports from '@/routes/reports';
import type { ReportFilters, ReportKey, ReportOptions } from '@/types';

const props = defineProps<{
    report: ReportKey;
    filters: ReportFilters;
    options: ReportOptions;
}>();

const page = usePage();
const errors = computed(() => page.props.errors as Record<string, string>);

const form = reactive({
    start_date: props.filters.start_date ?? '',
    end_date: props.filters.end_date ?? '',
    branch: props.filters.branch ? String(props.filters.branch) : '',
    hood: props.filters.hood ? String(props.filters.hood) : '',
    membership_type: props.filters.membership_type ?? '',
    status: props.filters.status ?? '',
    card_status: props.filters.card_status ?? '',
    group: props.filters.group,
});

watch(
    () => props.filters,
    (filters) => {
        form.start_date = filters.start_date ?? '';
        form.end_date = filters.end_date ?? '';
        form.branch = filters.branch ? String(filters.branch) : '';
        form.hood = filters.hood ? String(filters.hood) : '';
        form.membership_type = filters.membership_type ?? '';
        form.status = filters.status ?? '';
        form.card_status = filters.card_status ?? '';
        form.group = filters.group;
    },
    { deep: true },
);

const filteredHoods = computed(() => {
    if (!form.branch) {
        return [];
    }

    const branch = props.options.branches.find(
        (item) => item.id === Number(form.branch),
    );

    return branch?.hoods ?? [];
});

watch(
    () => form.branch,
    () => {
        if (
            form.hood &&
            !filteredHoods.value.some((hood) => hood.id === Number(form.hood))
        ) {
            form.hood = '';
        }
    },
);

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

function applyFilters(): void {
    router.get(
        reports.index.url(),
        {
            report: props.report,
            start_date: form.start_date || undefined,
            end_date: form.end_date || undefined,
            branch: form.branch || undefined,
            hood: form.hood || undefined,
            membership_type: form.membership_type || undefined,
            status: form.status || undefined,
            card_status: form.card_status || undefined,
            group: props.report === 'registrations' ? form.group : undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}

function resetFilters(): void {
    router.get(
        reports.index.url(),
        { report: props.report },
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}
</script>

<template>
    <form
        class="flex flex-col gap-4"
        @submit.prevent="applyFilters"
    >
        <div class="flex items-center gap-2">
            <Filter class="size-3.5 text-muted-foreground" />
            <p
                class="font-mono text-[11px] tracking-[0.16em] text-muted-foreground uppercase"
            >
                Report filters
            </p>
        </div>

        <div
            class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-6"
        >
            <div v-if="report === 'registrations'" class="space-y-1.5">
                <label for="report-start-date" class="text-xs font-medium">
                    Start date
                </label>
                <Input
                    id="report-start-date"
                    v-model="form.start_date"
                    type="date"
                    :max="form.end_date || undefined"
                />
                <InputError :message="errors.start_date" />
            </div>

            <div v-if="report === 'registrations'" class="space-y-1.5">
                <label for="report-end-date" class="text-xs font-medium">
                    End date
                </label>
                <Input
                    id="report-end-date"
                    v-model="form.end_date"
                    type="date"
                    :min="form.start_date || undefined"
                />
                <InputError :message="errors.end_date" />
            </div>

            <div class="space-y-1.5">
                <label for="report-branch" class="text-xs font-medium">
                    Territory
                </label>
                <select
                    id="report-branch"
                    v-model="form.branch"
                    :class="selectClass"
                    :disabled="!options.isMainAdmin"
                >
                    <option value="">All territories</option>
                    <option
                        v-for="branch in options.branches"
                        :key="branch.id"
                        :value="String(branch.id)"
                    >
                        {{ branch.name }}
                    </option>
                </select>
                <InputError :message="errors.branch" />
            </div>

            <div
                v-if="['summary', 'territories', 'registrations'].includes(report)"
                class="space-y-1.5"
            >
                <label for="report-hood" class="text-xs font-medium">
                    Hood
                </label>
                <select
                    id="report-hood"
                    v-model="form.hood"
                    :class="selectClass"
                    :disabled="!form.branch"
                >
                    <option value="">
                        {{ form.branch ? 'All hoods' : 'Select a territory first' }}
                    </option>
                    <option
                        v-for="hood in filteredHoods"
                        :key="hood.id"
                        :value="String(hood.id)"
                    >
                        {{ hood.name }}
                    </option>
                </select>
                <InputError :message="errors.hood" />
            </div>

            <div v-if="report === 'registrations'" class="space-y-1.5">
                <label for="report-type" class="text-xs font-medium">
                    Membership type
                </label>
                <select id="report-type" v-model="form.membership_type" :class="selectClass">
                    <option value="">All types</option>
                    <option
                        v-for="type in options.membershipTypes"
                        :key="type"
                        :value="type"
                    >
                        {{ type }}
                    </option>
                </select>
                <InputError :message="errors.membership_type" />
            </div>

            <div v-if="report === 'registrations'" class="space-y-1.5">
                <label for="report-status" class="text-xs font-medium">
                    Membership status
                </label>
                <select id="report-status" v-model="form.status" :class="selectClass">
                    <option value="">All statuses</option>
                    <option
                        v-for="status in options.statuses"
                        :key="status"
                        :value="status"
                    >
                        {{ status }}
                    </option>
                </select>
                <InputError :message="errors.status" />
            </div>

            <div v-if="report === 'nfc'" class="space-y-1.5">
                <label for="report-card-status" class="text-xs font-medium">
                    Card status
                </label>
                <select
                    id="report-card-status"
                    v-model="form.card_status"
                    :class="selectClass"
                >
                    <option value="">All card statuses</option>
                    <option
                        v-for="status in options.cardStatuses"
                        :key="status"
                        :value="status"
                    >
                        {{ status }}
                    </option>
                </select>
                <InputError :message="errors.card_status" />
            </div>

            <div v-if="report === 'registrations'" class="space-y-1.5">
                <label for="report-group" class="text-xs font-medium">
                    Trend grouping
                </label>
                <select id="report-group" v-model="form.group" :class="selectClass">
                    <option value="day">By day</option>
                    <option value="month">By month</option>
                    <option value="year">By year</option>
                </select>
                <InputError :message="errors.group" />
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <Button type="submit" size="sm">Apply filters</Button>
            <Button
                type="button"
                variant="outline"
                size="sm"
                @click="resetFilters"
            >
                <RotateCcw class="size-3.5" />
                Reset
            </Button>
        </div>
    </form>
</template>
