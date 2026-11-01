<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Building2, Eye, Pencil, Plus, Search, Trash2 } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import PaginationFooter from '@/components/PaginationFooter.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate, today } from '@/lib/format';
import { index as branchesIndex } from '@/routes/branches';
import {
    destroy as destroyMember,
    index as membersIndex,
    store as storeMember,
    update as updateMember,
} from '@/routes/members';
import { show as profileShow } from '@/routes/profile';
import type {
    Branch,
    Hood,
    Member,
    MemberStatus,
    MembershipType,
    Paginated,
} from '@/types';

const props = defineProps<{
    members: Paginated<Member>;
    branches: Branch[];
    filters: {
        q: string;
        status: string;
        branch: number;
        hood: number;
    };
    isMainAdmin: boolean;
    membershipTypes: MembershipType[];
    statuses: MemberStatus[];
}>();

const activeBranch = computed(() =>
    props.filters.branch
        ? (props.branches.find((b) => b.id === props.filters.branch) ?? null)
        : null,
);

const activeHood = computed(() => {
    if (!props.filters.hood) return null;
    for (const branch of props.branches) {
        const found = branch.hoods?.find((h) => h.id === props.filters.hood);
        if (found) return found;
    }
    return null;
});

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Members', href: membersIndex() }],
    },
});

const filterForm = ref({
    q: props.filters.q,
    status: props.filters.status,
    branch: props.filters.branch || '',
    hood: props.filters.hood || '',
});

// Hoods available for the currently selected filter branch
const filteredHoods = computed<Hood[]>(() => {
    if (!filterForm.value.branch) return [];
    const branch = props.branches.find(
        (b) => b.id === Number(filterForm.value.branch),
    );
    return branch?.hoods ?? [];
});

// When branch changes in filter, reset hood filter
watch(
    () => filterForm.value.branch,
    () => {
        filterForm.value.hood = '';
    },
);

const formOpen = ref(false);
const editing = ref<Member | null>(null);
const viewOpen = ref(false);
const viewing = ref<Member | null>(null);

const memberForm = useForm({
    branch_id: props.branches[0]?.id ?? 0,
    hood_id: null as number | null,
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    address: '',
    membership_type: 'Carnal' as MembershipType,
    status: 'Active' as MemberStatus,
    join_date: today(),
    approved: true,
});

const deleteOpen = ref(false);
const deleteTarget = ref<Member | null>(null);

const formTitle = computed(() =>
    editing.value ? 'Edit member' : 'Add member',
);

// Hoods available for the currently selected form branch
const formHoods = computed<Hood[]>(() => {
    const branch = props.branches.find((b) => b.id === memberForm.branch_id);
    return branch?.hoods ?? [];
});

// When branch changes in form, reset hood
watch(
    () => memberForm.branch_id,
    () => {
        memberForm.hood_id = null;
    },
);

function positionLabel(type: MembershipType | string): string {
    if (type === 'Carnal') {
        return 'Carnal (Boy)';
    }
    if (type === 'Carnala') {
        return 'Carnala (Girl)';
    }

    return type;
}

function applyFilters(): void {
    router.get(
        membersIndex.url(),
        {
            q: filterForm.value.q || undefined,
            status: filterForm.value.status || undefined,
            branch: filterForm.value.branch || undefined,
            hood: filterForm.value.hood || undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

function clearFilters(): void {
    filterForm.value = { q: '', status: '', branch: '', hood: '' };
    router.get(membersIndex.url(), {}, { preserveScroll: true });
}

/**
 * Live search: apply the query automatically as the user types so searching
 * works on mobile too, where pressing Enter on the keyboard is unreliable.
 */
let searchTimeout: ReturnType<typeof setTimeout> | undefined;

watch(
    () => filterForm.value.q,
    (query) => {
        clearTimeout(searchTimeout);

        if (query === props.filters.q) {
            return;
        }

        searchTimeout = setTimeout(() => applyFilters(), 350);
    },
);

function openCreate(): void {
    editing.value = null;
    memberForm.reset();
    memberForm.clearErrors();
    memberForm.branch_id = props.filters.branch || props.branches[0]?.id || 0;
    memberForm.hood_id = props.filters.hood || null;
    formOpen.value = true;
}

function openView(member: Member): void {
    viewing.value = member;
    viewOpen.value = true;
}

function editFromView(): void {
    if (!viewing.value) {
        return;
    }

    const member = viewing.value;
    viewOpen.value = false;
    openEdit(member);
}

function openEdit(member: Member): void {
    editing.value = member;
    memberForm.clearErrors();
    memberForm.branch_id = member.branch_id;
    memberForm.hood_id = member.hood_id;
    memberForm.first_name = member.first_name;
    memberForm.last_name = member.last_name;
    memberForm.email = member.email;
    memberForm.phone = member.phone ?? '';
    memberForm.address = member.address ?? '';
    memberForm.membership_type = member.membership_type;
    memberForm.status = member.status;
    memberForm.join_date = member.join_date.slice(0, 10);
    memberForm.approved = member.approved;
    formOpen.value = true;
}

function submit(): void {
    memberForm.transform((data) => ({
        ...data,
        approved: data.approved ? 1 : 0,
    }));

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            formOpen.value = false;
            editing.value = null;
        },
    };

    if (editing.value) {
        memberForm.patch(updateMember(editing.value.id).url, options);
    } else {
        memberForm.post(storeMember().url, options);
    }
}

function confirmDelete(member: Member): void {
    deleteTarget.value = member;
    deleteOpen.value = true;
}

function destroy(): void {
    if (!deleteTarget.value) {
        return;
    }

    router.delete(destroyMember(deleteTarget.value.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            deleteOpen.value = false;
            deleteTarget.value = null;
        },
    });
}

function goToPage(page: number): void {
    router.get(
        membersIndex.url(),
        {
            ...props.filters,
            page,
        },
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
}

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const isLoading = ref(false);
const SKELETON_COUNT = 8;

let stopListeningStart: (() => void) | undefined;
let stopListeningFinish: (() => void) | undefined;

onMounted(() => {
    stopListeningStart = router.on('start', () => {
        isLoading.value = true;
    });
    stopListeningFinish = router.on('finish', () => {
        isLoading.value = false;
    });
});

onUnmounted(() => {
    clearTimeout(searchTimeout);
    stopListeningStart?.();
    stopListeningFinish?.();
});
</script>

<template>
    <Head title="Members" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"
        >
            <div>
                <p
                    class="font-mono text-[11px] tracking-[0.2em] text-muted-foreground uppercase"
                >
                    Registry
                </p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight">
                    Members
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    <template v-if="activeHood">
                        {{ members.total }} member{{
                            members.total === 1 ? '' : 's'
                        }}
                        in
                        <span class="font-medium text-foreground">{{
                            activeHood.name
                        }}</span>
                    </template>
                    <template v-else-if="activeBranch">
                        {{ members.total }} member{{
                            members.total === 1 ? '' : 's'
                        }}
                        in
                        <Link
                            :href="branchesIndex()"
                            class="inline-flex items-center gap-1 font-medium text-foreground hover:underline"
                        >
                            <Building2 class="size-3.5" />
                            {{ activeBranch.name }}
                        </Link>
                    </template>
                    <template v-else>
                        {{ members.total }} member{{
                            members.total === 1 ? '' : 's'
                        }}
                        in view.
                    </template>
                </p>
            </div>

            <Button class="w-full sm:w-auto" @click="openCreate">
                <Plus class="size-4" />
                Add member
            </Button>
        </div>

        <Card>
            <CardContent>
                <form
                    class="flex flex-col gap-3 md:flex-row md:items-end"
                    @submit.prevent="applyFilters"
                >
                    <div class="flex-1 space-y-1.5">
                        <Label for="search">Search</Label>
                        <div class="relative">
                            <Search
                                class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                id="search"
                                v-model="filterForm.q"
                                type="search"
                                enterkeyhint="search"
                                placeholder="Name or email"
                                class="pl-9"
                            />
                        </div>
                    </div>

                    <div class="space-y-1.5 md:w-40">
                        <Label for="status">Status</Label>
                        <select
                            id="status"
                            v-model="filterForm.status"
                            :class="selectClass"
                        >
                            <option value="">All statuses</option>
                            <option
                                v-for="status in statuses"
                                :key="status"
                                :value="status"
                            >
                                {{ status }}
                            </option>
                        </select>
                    </div>

                    <div v-if="isMainAdmin" class="space-y-1.5 md:w-48">
                        <Label for="branch">Territory</Label>
                        <select
                            id="branch"
                            v-model="filterForm.branch"
                            :class="selectClass"
                        >
                            <option value="">All territories</option>
                            <option
                                v-for="branch in branches"
                                :key="branch.id"
                                :value="branch.id"
                            >
                                {{ branch.name }}
                            </option>
                        </select>
                    </div>

                    <div
                        v-if="filterForm.branch && filteredHoods.length"
                        class="space-y-1.5 md:w-48"
                    >
                        <Label for="hood">Hood</Label>
                        <select
                            id="hood"
                            v-model="filterForm.hood"
                            :class="selectClass"
                        >
                            <option value="">All hoods</option>
                            <option
                                v-for="hood in filteredHoods"
                                :key="hood.id"
                                :value="hood.id"
                            >
                                {{ hood.name }}
                            </option>
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <Button type="submit">Filter</Button>
                        <Button
                            type="button"
                            variant="ghost"
                            @click="clearFilters"
                            >Reset</Button
                        >
                    </div>
                </form>
            </CardContent>
        </Card>

        <!-- Mobile skeleton cards -->
        <div v-if="isLoading" class="flex flex-col gap-3 lg:hidden">
            <Card
                v-for="i in SKELETON_COUNT"
                :key="i"
                class="space-y-3 p-4 py-4"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="size-10 shrink-0 animate-pulse rounded-full bg-muted"
                    />
                    <div class="min-w-0 flex-1 space-y-2">
                        <div class="h-4 w-3/5 animate-pulse rounded bg-muted" />
                        <div class="h-3 w-4/5 animate-pulse rounded bg-muted" />
                        <div class="mt-2 flex gap-1.5">
                            <div
                                class="h-5 w-16 animate-pulse rounded-full bg-muted"
                            />
                            <div
                                class="h-5 w-14 animate-pulse rounded-full bg-muted"
                            />
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-x-4 gap-y-2">
                    <div class="space-y-1">
                        <div
                            class="h-2.5 w-10 animate-pulse rounded bg-muted"
                        />
                        <div
                            class="h-3.5 w-24 animate-pulse rounded bg-muted"
                        />
                    </div>
                    <div class="space-y-1">
                        <div
                            class="h-2.5 w-10 animate-pulse rounded bg-muted"
                        />
                        <div
                            class="h-3.5 w-20 animate-pulse rounded bg-muted"
                        />
                    </div>
                </div>
                <div class="flex gap-2 border-t pt-3">
                    <div class="h-8 flex-1 animate-pulse rounded-md bg-muted" />
                    <div class="h-8 flex-1 animate-pulse rounded-md bg-muted" />
                    <div class="h-8 flex-1 animate-pulse rounded-md bg-muted" />
                </div>
            </Card>
        </div>

        <!-- Mobile real cards -->
        <div v-else class="flex flex-col gap-3 lg:hidden">
            <Card
                v-for="member in members.data"
                :key="member.id"
                class="space-y-3 p-4 py-4"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted font-mono text-xs font-semibold"
                    >
                        {{ member.initials }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">
                            {{ member.full_name }}
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ member.email }}
                        </p>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <StatusBadge :status="member.membership_type" />
                            <StatusBadge :status="member.status" />
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    <div>
                        <p
                            class="text-[10px] font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Territory
                        </p>
                        <p class="mt-0.5 truncate">
                            {{ member.branch?.name ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p
                            class="text-[10px] font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Hood
                        </p>
                        <p class="mt-0.5 truncate">
                            {{ member.hood?.name ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p
                            class="text-[10px] font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Joined
                        </p>
                        <p class="mt-0.5 font-mono text-xs">
                            {{ formatDate(member.join_date) }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 border-t pt-3">
                    <Button
                        variant="outline"
                        size="sm"
                        class="flex-1"
                        aria-label="View member"
                        @click="openView(member)"
                    >
                        <Eye class="size-3.5" />
                        View
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        class="flex-1"
                        aria-label="Edit member"
                        @click="openEdit(member)"
                    >
                        <Pencil class="size-3.5" />
                        Edit
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        class="flex-1 text-destructive hover:bg-destructive/10 hover:text-destructive"
                        aria-label="Delete member"
                        @click="confirmDelete(member)"
                    >
                        <Trash2 class="size-3.5" />
                        Delete
                    </Button>
                </div>
            </Card>

            <Card
                v-if="!members.data.length"
                class="px-4 py-12 text-center text-sm text-muted-foreground"
            >
                No members match your filters.
            </Card>

            <Card v-if="members.total > 0" class="py-0">
                <PaginationFooter
                    :from="members.from"
                    :to="members.to"
                    :total="members.total"
                    :current-page="members.current_page"
                    :last-page="members.last_page"
                    @previous="goToPage(members.current_page - 1)"
                    @next="goToPage(members.current_page + 1)"
                />
            </Card>
        </div>

        <!-- Desktop skeleton table -->
        <Card v-if="isLoading" class="hidden overflow-hidden py-0 lg:block">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/40 text-left">
                            <th class="px-3 py-3 font-medium xl:px-4">
                                Member
                            </th>
                            <th class="px-3 py-3 font-medium xl:px-4">
                                Territory
                            </th>
                            <th
                                class="hidden px-3 py-3 font-medium xl:table-cell xl:px-4"
                            >
                                Hood
                            </th>
                            <th class="px-3 py-3 font-medium xl:px-4">
                                Position
                            </th>
                            <th class="px-3 py-3 font-medium xl:px-4">
                                Status
                            </th>
                            <th
                                class="hidden px-3 py-3 font-medium xl:table-cell xl:px-4"
                            >
                                Joined
                            </th>
                            <th
                                class="px-3 py-3 text-right font-medium xl:px-4"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="i in SKELETON_COUNT"
                            :key="i"
                            class="border-b last:border-0"
                        >
                            <!-- Member cell -->
                            <td class="px-3 py-3 xl:px-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="size-9 shrink-0 animate-pulse rounded-full bg-muted"
                                    />
                                    <div class="space-y-1.5">
                                        <div
                                            class="h-4 w-32 animate-pulse rounded bg-muted"
                                        />
                                        <div
                                            class="hidden h-3 w-44 animate-pulse rounded bg-muted xl:block"
                                        />
                                    </div>
                                </div>
                            </td>
                            <!-- Territory -->
                            <td class="px-3 py-3 xl:px-4">
                                <div
                                    class="h-4 w-24 animate-pulse rounded bg-muted"
                                />
                            </td>
                            <!-- Hood -->
                            <td class="hidden px-3 py-3 xl:table-cell xl:px-4">
                                <div
                                    class="h-4 w-20 animate-pulse rounded bg-muted"
                                />
                            </td>
                            <!-- Position -->
                            <td class="px-3 py-3 xl:px-4">
                                <div
                                    class="h-5 w-16 animate-pulse rounded-full bg-muted"
                                />
                            </td>
                            <!-- Status -->
                            <td class="px-3 py-3 xl:px-4">
                                <div
                                    class="h-5 w-14 animate-pulse rounded-full bg-muted"
                                />
                            </td>
                            <!-- Joined -->
                            <td class="hidden px-3 py-3 xl:table-cell xl:px-4">
                                <div
                                    class="h-3.5 w-20 animate-pulse rounded bg-muted"
                                />
                            </td>
                            <!-- Actions -->
                            <td class="px-3 py-3 xl:px-4">
                                <div class="flex justify-end gap-1">
                                    <div
                                        class="size-7 animate-pulse rounded bg-muted"
                                    />
                                    <div
                                        class="size-7 animate-pulse rounded bg-muted"
                                    />
                                    <div
                                        class="size-7 animate-pulse rounded bg-muted"
                                    />
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                class="flex flex-col gap-2 border-t px-4 py-3 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="h-3.5 w-36 animate-pulse rounded bg-muted" />
                <div class="flex items-center gap-2">
                    <div class="h-7 w-20 animate-pulse rounded-md bg-muted" />
                    <div class="h-3.5 w-10 animate-pulse rounded bg-muted" />
                    <div class="h-7 w-14 animate-pulse rounded-md bg-muted" />
                </div>
            </div>
        </Card>

        <!-- Desktop real table -->
        <Card v-else class="hidden overflow-hidden py-0 lg:block">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/40 text-left">
                            <th class="px-3 py-3 font-medium xl:px-4">
                                Member
                            </th>
                            <th class="px-3 py-3 font-medium xl:px-4">
                                Territory
                            </th>
                            <th
                                class="hidden px-3 py-3 font-medium xl:table-cell xl:px-4"
                            >
                                Hood
                            </th>
                            <th class="px-3 py-3 font-medium xl:px-4">
                                Position
                            </th>
                            <th class="px-3 py-3 font-medium xl:px-4">
                                Status
                            </th>
                            <th
                                class="hidden px-3 py-3 font-medium xl:table-cell xl:px-4"
                            >
                                Joined
                            </th>
                            <th
                                class="px-3 py-3 text-right font-medium xl:px-4"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="member in members.data"
                            :key="member.id"
                            class="border-b last:border-0 hover:bg-muted/20"
                        >
                            <td class="px-3 py-3 xl:px-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted font-mono text-xs font-semibold"
                                    >
                                        {{ member.initials }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate font-medium">
                                            {{ member.full_name }}
                                        </p>
                                        <p
                                            class="hidden truncate text-xs text-muted-foreground xl:block"
                                        >
                                            {{ member.email }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-3 xl:px-4">
                                {{ member.branch?.name ?? '—' }}
                            </td>
                            <td class="hidden px-3 py-3 xl:table-cell xl:px-4">
                                {{ member.hood?.name ?? '—' }}
                            </td>
                            <td class="px-3 py-3 xl:px-4">
                                <StatusBadge :status="member.membership_type" />
                            </td>
                            <td class="px-3 py-3 xl:px-4">
                                <StatusBadge :status="member.status" />
                            </td>
                            <td
                                class="hidden px-3 py-3 font-mono text-xs whitespace-nowrap text-muted-foreground xl:table-cell xl:px-4"
                            >
                                {{ formatDate(member.join_date) }}
                            </td>
                            <td class="px-3 py-3 xl:px-4">
                                <div class="flex justify-end gap-1">
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        aria-label="View member"
                                        @click="openView(member)"
                                    >
                                        <Eye class="size-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        aria-label="Edit member"
                                        @click="openEdit(member)"
                                    >
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        aria-label="Delete member"
                                        class="text-destructive hover:text-destructive"
                                        @click="confirmDelete(member)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="!members.data.length">
                            <td
                                colspan="7"
                                class="px-4 py-12 text-center text-sm text-muted-foreground"
                            >
                                No members match your filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="border-t">
                <PaginationFooter
                    :from="members.from"
                    :to="members.to"
                    :total="members.total"
                    :current-page="members.current_page"
                    :last-page="members.last_page"
                    @previous="goToPage(members.current_page - 1)"
                    @next="goToPage(members.current_page + 1)"
                />
            </div>
        </Card>
    </div>

    <Dialog v-model:open="formOpen">
        <DialogContent class="sm:max-w-2xl">
            <form class="space-y-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ formTitle }}</DialogTitle>
                    <DialogDescription>
                        {{
                            editing
                                ? `Update this member's details.`
                                : 'Add a new member to the registry.'
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label for="first_name">First name</Label>
                        <Input
                            id="first_name"
                            v-model="memberForm.first_name"
                        />
                        <InputError :message="memberForm.errors.first_name" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="last_name">Last name</Label>
                        <Input id="last_name" v-model="memberForm.last_name" />
                        <InputError :message="memberForm.errors.last_name" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="email">Email</Label>
                        <Input
                            id="email"
                            type="email"
                            inputmode="email"
                            autocomplete="email"
                            v-model="memberForm.email"
                        />
                        <InputError :message="memberForm.errors.email" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="phone">Phone</Label>
                        <Input
                            id="phone"
                            type="tel"
                            inputmode="numeric"
                            pattern="09[0-9]{9}"
                            maxlength="11"
                            placeholder="09XXXXXXXXX"
                            v-model="memberForm.phone"
                            @input="
                                (e: Event) => {
                                    const val = (e.target as HTMLInputElement)
                                        .value;
                                    if (val.startsWith('09')) {
                                        memberForm.phone = val
                                            .replace(/\D/g, '')
                                            .slice(0, 11);
                                    }
                                }
                            "
                        />
                        <InputError :message="memberForm.errors.phone" />
                    </div>
                    <div class="space-y-1.5 sm:col-span-2">
                        <Label for="address">Address</Label>
                        <Input id="address" v-model="memberForm.address" />
                        <InputError :message="memberForm.errors.address" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="membership_type">Position</Label>
                        <select
                            id="membership_type"
                            v-model="memberForm.membership_type"
                            :class="selectClass"
                        >
                            <option
                                v-for="type in membershipTypes"
                                :key="type"
                                :value="type"
                            >
                                {{ positionLabel(type) }}
                            </option>
                        </select>
                        <InputError
                            :message="memberForm.errors.membership_type"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="member_status">Status</Label>
                        <select
                            id="member_status"
                            v-model="memberForm.status"
                            :class="selectClass"
                        >
                            <option
                                v-for="status in statuses"
                                :key="status"
                                :value="status"
                            >
                                {{ status }}
                            </option>
                        </select>
                        <InputError :message="memberForm.errors.status" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="join_date">Join date</Label>
                        <Input
                            id="join_date"
                            type="date"
                            v-model="memberForm.join_date"
                        />
                        <InputError :message="memberForm.errors.join_date" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="branch_id">Territory</Label>
                        <select
                            id="branch_id"
                            v-model.number="memberForm.branch_id"
                            :class="selectClass"
                            :disabled="!isMainAdmin"
                        >
                            <option
                                v-for="branch in branches"
                                :key="branch.id"
                                :value="branch.id"
                            >
                                {{ branch.name }}
                            </option>
                        </select>
                        <InputError :message="memberForm.errors.branch_id" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="hood_id">Hood</Label>
                        <select
                            id="hood_id"
                            v-model.number="memberForm.hood_id"
                            :class="selectClass"
                        >
                            <option :value="null">— No hood —</option>
                            <option
                                v-for="hood in formHoods"
                                :key="hood.id"
                                :value="hood.id"
                            >
                                {{ hood.name }}
                            </option>
                        </select>
                        <InputError :message="memberForm.errors.hood_id" />
                    </div>
                </div>

                <label class="flex items-start gap-2 text-sm">
                    <Checkbox
                        id="approved"
                        v-model="memberForm.approved"
                        class="mt-0.5"
                    />
                    <span>
                        Public profile approved
                        <span class="block text-xs text-muted-foreground">
                            Approved members are visible on their NFC profile
                            page.
                        </span>
                    </span>
                </label>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="secondary"
                        @click="formOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="memberForm.processing">
                        {{ editing ? 'Save changes' : 'Add member' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="viewOpen">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Member details</DialogTitle>
                <DialogDescription>
                    Full registry record for this member.
                </DialogDescription>
            </DialogHeader>

            <div v-if="viewing" class="space-y-5">
                <div class="flex items-start gap-3">
                    <div
                        class="flex size-12 shrink-0 items-center justify-center rounded-full bg-muted font-mono text-sm font-semibold"
                    >
                        {{ viewing.initials }}
                    </div>
                    <div class="min-w-0">
                        <p class="truncate font-semibold">
                            {{ viewing.full_name }}
                        </p>
                        <p class="truncate text-sm text-muted-foreground">
                            {{ viewing.email }}
                        </p>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <StatusBadge :status="viewing.membership_type" />
                            <StatusBadge :status="viewing.status" />
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <p
                            class="text-[10px] font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Territory
                        </p>
                        <p class="mt-1 font-medium">
                            {{ viewing.branch?.name ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p
                            class="text-[10px] font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Hood
                        </p>
                        <p class="mt-1 font-medium">
                            {{ viewing.hood?.name ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p
                            class="text-[10px] font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Position
                        </p>
                        <p class="mt-1 font-medium">
                            {{ positionLabel(viewing.membership_type) }}
                        </p>
                    </div>
                    <div>
                        <p
                            class="text-[10px] font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Member ID
                        </p>
                        <p class="mt-1 font-mono">
                            TS-{{ String(viewing.id).padStart(4, '0') }}
                        </p>
                    </div>
                    <div>
                        <p
                            class="text-[10px] font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Phone
                        </p>
                        <p class="mt-1">{{ viewing.phone || '—' }}</p>
                    </div>
                    <div>
                        <p
                            class="text-[10px] font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Joined
                        </p>
                        <p class="mt-1">{{ formatDate(viewing.join_date) }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <p
                            class="text-[10px] font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Address
                        </p>
                        <p class="mt-1">{{ viewing.address || '—' }}</p>
                    </div>
                </div>

                <div class="rounded-lg border p-3">
                    <div class="flex items-center justify-between gap-2">
                        <p
                            class="text-[10px] font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            NFC card
                        </p>
                        <StatusBadge
                            v-if="viewing.nfc_card"
                            :status="viewing.nfc_card.card_status"
                        />
                    </div>
                    <div v-if="viewing.nfc_card" class="mt-2 space-y-1 text-sm">
                        <p class="font-mono">
                            {{ viewing.nfc_card.card_uid }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Issued
                            {{ formatDate(viewing.nfc_card.issued_date) }}
                        </p>
                    </div>
                    <p v-else class="mt-2 text-sm text-muted-foreground">
                        No NFC card assigned.
                    </p>
                </div>

                <div class="rounded-lg border p-3 text-sm">
                    <p
                        class="text-[10px] font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        Public profile
                    </p>
                    <a
                        v-if="viewing.approved"
                        :href="profileShow.url(viewing.profile_slug)"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-1 inline-flex items-center gap-1 font-medium text-foreground hover:underline"
                    >
                        Open public profile
                    </a>
                    <p v-else class="mt-1 text-muted-foreground">
                        Not visible publicly until approved.
                    </p>
                </div>
            </div>

            <DialogFooter>
                <Button variant="secondary" @click="viewOpen = false">
                    Close
                </Button>
                <Button @click="editFromView">Edit member</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="deleteOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Delete member?</DialogTitle>
                <DialogDescription>
                    {{ deleteTarget?.full_name }} and their NFC card will be
                    permanently removed.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="secondary" @click="deleteOpen = false">
                    Cancel
                </Button>
                <Button variant="destructive" @click="destroy">
                    Delete member
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
