<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArchiveRestore, RotateCcw, Search, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import PaginationFooter from '@/components/PaginationFooter.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { formatDate } from '@/lib/format';
import {
    destroy as forceDeleteMember,
    index as archivesIndex,
    restore as restoreMember,
} from '@/routes/archives';
import type { Member, Paginated } from '@/types';

const props = defineProps<{
    members: Paginated<Member>;
    filters: {
        q: string;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Archives', href: archivesIndex() }],
    },
});

const search = ref(props.filters.q);

const restoreOpen = ref(false);
const restoreTarget = ref<Member | null>(null);
const deleteOpen = ref(false);
const deleteTarget = ref<Member | null>(null);

function goToPage(page: number): void {
    router.get(
        archivesIndex.url(),
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

function applySearch(): void {
    router.get(
        archivesIndex.url(),
        { q: search.value || undefined },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

let searchTimeout: ReturnType<typeof setTimeout> | undefined;

watch(search, (query) => {
    clearTimeout(searchTimeout);

    if (query === props.filters.q) {
        return;
    }

    searchTimeout = setTimeout(() => applySearch(), 350);
});

function confirmRestore(member: Member): void {
    restoreTarget.value = member;
    restoreOpen.value = true;
}

function restore(): void {
    if (!restoreTarget.value) {
        return;
    }

    router.patch(
        restoreMember(restoreTarget.value.id).url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                restoreOpen.value = false;
                restoreTarget.value = null;
            },
        },
    );
}

function confirmDelete(member: Member): void {
    deleteTarget.value = member;
    deleteOpen.value = true;
}

function destroy(): void {
    if (!deleteTarget.value) {
        return;
    }

    router.delete(forceDeleteMember(deleteTarget.value.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            deleteOpen.value = false;
            deleteTarget.value = null;
        },
    });
}
</script>

<template>
    <Head title="Archives" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div>
            <p
                class="font-mono text-[11px] tracking-[0.2em] text-muted-foreground uppercase"
            >
                Registry
            </p>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight">Archives</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ members.total }} archived member{{
                    members.total === 1 ? '' : 's'
                }}. Restore a member or delete them permanently.
            </p>
        </div>

        <Card>
            <CardContent>
                <form
                    class="flex flex-col gap-3 md:flex-row md:items-end"
                    @submit.prevent="applySearch"
                >
                    <div class="flex-1 space-y-1.5">
                        <Label for="search">Search</Label>
                        <div class="relative">
                            <Search
                                class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                id="search"
                                v-model="search"
                                type="search"
                                enterkeyhint="search"
                                placeholder="Name, alias or email"
                                class="pl-9"
                            />
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <Button type="submit">Filter</Button>
                        <Button
                            type="button"
                            variant="ghost"
                            :disabled="!search"
                            @click="
                                () => {
                                    search = '';
                                    applySearch();
                                }
                            "
                        >
                            Reset
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <!-- Mobile cards -->
        <div class="flex flex-col gap-3 lg:hidden">
            <Card
                v-for="member in members.data"
                :key="member.id"
                class="space-y-3 p-4 py-4"
            >
                <div class="flex items-start gap-3">
                    <Avatar class="size-10 shrink-0">
                        <AvatarImage
                            v-if="member.avatar_url"
                            :src="member.avatar_url"
                            :alt="member.full_name"
                            class="object-cover"
                        />
                        <AvatarFallback
                            class="bg-muted font-mono text-xs font-semibold"
                        >
                            {{ member.initials }}
                        </AvatarFallback>
                    </Avatar>
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
                            Archived
                        </p>
                        <p
                            v-if="member.deleted_at"
                            class="mt-0.5 font-mono text-xs"
                        >
                            {{ formatDate(member.deleted_at) }}
                        </p>
                        <p v-else class="mt-0.5">—</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 border-t pt-3">
                    <Button
                        variant="outline"
                        size="sm"
                        class="flex-1"
                        aria-label="Restore member"
                        @click="confirmRestore(member)"
                    >
                        <RotateCcw class="size-3.5" />
                        Restore
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        class="flex-1 text-destructive hover:bg-destructive/10 hover:text-destructive"
                        aria-label="Delete member permanently"
                        @click="confirmDelete(member)"
                    >
                        <Trash2 class="size-3.5" />
                        Delete permanently
                    </Button>
                </div>
            </Card>

            <Card
                v-if="!members.data.length"
                class="px-4 py-12 text-center text-sm text-muted-foreground"
            >
                <ArchiveRestore class="mx-auto mb-3 size-8 opacity-60" />
                No archived members match your filters.
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

        <!-- Desktop table -->
        <Card class="hidden overflow-hidden py-0 lg:block">
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
                                Archived
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
                                    <Avatar class="size-9 shrink-0">
                                        <AvatarImage
                                            v-if="member.avatar_url"
                                            :src="member.avatar_url"
                                            :alt="member.full_name"
                                            class="object-cover"
                                        />
                                        <AvatarFallback
                                            class="bg-muted font-mono text-xs font-semibold"
                                        >
                                            {{ member.initials }}
                                        </AvatarFallback>
                                    </Avatar>
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
                                {{
                                    member.deleted_at
                                        ? formatDate(member.deleted_at)
                                        : '—'
                                }}
                            </td>
                            <td class="px-3 py-3 xl:px-4">
                                <div class="flex justify-end gap-1">
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        aria-label="Restore member"
                                        @click="confirmRestore(member)"
                                    >
                                        <RotateCcw class="size-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        aria-label="Delete member permanently"
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
                                No archived members match your filters.
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

    <Dialog v-model:open="restoreOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Restore member?</DialogTitle>
                <DialogDescription>
                    {{
                        restoreTarget
                            ? `${restoreTarget.full_name} will be returned to the members list. Their record, login account, avatar and NFC card are kept.`
                            : 'This member will be returned to the members list.'
                    }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="secondary" @click="restoreOpen = false">
                    Cancel
                </Button>
                <Button @click="restore">
                    <RotateCcw class="size-4" />
                    Restore member
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="deleteOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Delete member permanently?</DialogTitle>
                <DialogDescription>
                    {{
                        deleteTarget
                            ? `${deleteTarget.full_name}, their login account and NFC card will be permanently removed. This cannot be undone.`
                            : 'This member will be permanently removed. This cannot be undone.'
                    }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="secondary" @click="deleteOpen = false">
                    Cancel
                </Button>
                <Button variant="destructive" @click="destroy">
                    Delete permanently
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
