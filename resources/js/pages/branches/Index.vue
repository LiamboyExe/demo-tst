<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import {
    Building2,
    Mail,
    MapPin,
    Pencil,
    Phone,
    Plus,
    Trash2,
    UserRound,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import {
    destroy as destroyBranch,
    index as branchesIndex,
    store as storeBranch,
    update as updateBranch,
} from '@/routes/branches';
import { index as membersIndex } from '@/routes/members';
import type { Branch } from '@/types';

const props = defineProps<{
    branches: Branch[];
    isMainAdmin: boolean;
}>();

setLayoutProps({
    breadcrumbs: [
        {
            title: props.isMainAdmin ? 'Territories' : 'Territory',
            href: branchesIndex(),
        },
    ],
});

const createOpen = ref(false);
const editOpen = ref(false);
const deleteOpen = ref(false);
const editing = ref<Branch | null>(null);
const deleteTarget = ref<Branch | null>(null);

const createForm = useForm({
    name: '',
    location: '',
    manager: '',
    phone: '',
    email: '',
    admin_username: '',
    admin_password: '',
    admin_password_confirmation: '',
});

const editForm = useForm({
    name: '',
    location: '',
    manager: '',
    phone: '',
    email: '',
    admin_password: '',
    admin_password_confirmation: '',
});

const totalMembers = computed(() =>
    props.branches.reduce(
        (sum, branch) => sum + (branch.members_count ?? 0),
        0,
    ),
);

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

function openCreate(): void {
    createForm.reset();
    createForm.clearErrors();
    createOpen.value = true;
}

function openEdit(branch: Branch): void {
    editing.value = branch;
    editForm.clearErrors();
    editForm.name = branch.name;
    editForm.location = branch.location;
    editForm.manager = branch.manager;
    editForm.phone = branch.phone ?? '';
    editForm.email = branch.email ?? '';
    editForm.admin_password = '';
    editForm.admin_password_confirmation = '';
    editOpen.value = true;
}

function handlePhoneInput(form: { phone: string }, event: Event): void {
    const target = event.target as HTMLInputElement;
    const val = target.value;
    if (val.startsWith('09')) {
        form.phone = val.replace(/\D/g, '').slice(0, 11);
    }
}

function submitCreate(): void {
    createForm.post(storeBranch().url, {
        preserveScroll: true,
        onSuccess: () => {
            createOpen.value = false;
            createForm.reset();
        },
    });
}

function submitEdit(): void {
    if (!editing.value) {
        return;
    }

    editForm.patch(updateBranch(editing.value.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            editOpen.value = false;
            editing.value = null;
        },
    });
}

function confirmDelete(branch: Branch): void {
    deleteTarget.value = branch;
    deleteOpen.value = true;
}

function destroy(): void {
    if (!deleteTarget.value) {
        return;
    }

    router.delete(destroyBranch(deleteTarget.value.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            deleteOpen.value = false;
            deleteTarget.value = null;
        },
    });
}
</script>

<template>
    <Head :title="isMainAdmin ? 'Territories' : 'Territory'" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"
        >
            <div>
                <p
                    class="font-mono text-[11px] tracking-[0.2em] text-muted-foreground uppercase"
                >
                    {{ isMainAdmin ? 'Network' : 'Assigned Territory' }}
                </p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight">
                    {{ isMainAdmin ? 'Territories' : 'Territory' }}
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    <template v-if="isMainAdmin">
                        {{ branches.length }} territor{{
                            branches.length === 1 ? 'y' : 'ies'
                        }}
                        · {{ totalMembers }} members
                    </template>
                    <template v-else>
                        {{ branches[0]?.name ?? 'Territory' }} · {{ totalMembers }} members
                    </template>
                </p>
            </div>

            <Button v-if="isMainAdmin" @click="openCreate">
                <Plus class="size-4" />
                New territory
            </Button>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Card v-for="branch in branches" :key="branch.id" class="gap-4">
                <CardHeader class="gap-3">
                    <div class="flex items-start justify-between gap-3">
                        <div
                            class="flex size-10 items-center justify-center rounded-lg bg-muted"
                        >
                            <Building2 class="size-5" />
                        </div>
                        <Badge
                            v-if="branch.admins?.length"
                            variant="outline"
                            class="font-mono text-[10px]"
                        >
                            {{ branch.admins[0]?.username }}
                        </Badge>
                        <Badge v-else variant="outline" class="text-[10px]">
                            No admin account
                        </Badge>
                    </div>
                    <div>
                        <CardTitle class="text-lg">{{ branch.name }}</CardTitle>
                        <p
                            class="mt-1 flex items-center gap-1.5 text-sm text-muted-foreground"
                        >
                            <MapPin class="size-3.5 shrink-0" />
                            {{ branch.location }}
                        </p>
                    </div>
                </CardHeader>

                <CardContent class="space-y-4">
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <Link
                            :href="
                                membersIndex({
                                    query: { branch: branch.id },
                                })
                            "
                            class="rounded-lg border p-2 transition-colors hover:bg-muted/60"
                        >
                            <p class="font-mono text-lg font-semibold">
                                {{ branch.members_count ?? 0 }}
                            </p>
                            <p
                                class="text-[10px] text-muted-foreground uppercase"
                            >
                                Members
                            </p>
                        </Link>
                        <Link
                            :href="
                                membersIndex({
                                    query: {
                                        branch: branch.id,
                                        status: 'Active',
                                    },
                                })
                            "
                            class="rounded-lg border p-2 transition-colors hover:bg-muted/60"
                        >
                            <p
                                class="font-mono text-lg font-semibold text-emerald-600 dark:text-emerald-400"
                            >
                                {{ branch.active_members_count ?? 0 }}
                            </p>
                            <p
                                class="text-[10px] text-muted-foreground uppercase"
                            >
                                Active
                            </p>
                        </Link>
                        <div
                            class="rounded-lg border p-2"
                        >
                            <p class="font-mono text-lg font-semibold text-violet-600 dark:text-violet-400">
                                {{ branch.hoods_count ?? branch.hoods?.length ?? 0 }}
                            </p>
                            <p
                                class="text-[10px] text-muted-foreground uppercase"
                            >
                                Hoods
                            </p>
                        </div>
                    </div>

                    <!-- Hoods list -->
                    <div v-if="branch.hoods?.length" class="space-y-1">
                        <p class="text-[10px] font-medium tracking-wide text-muted-foreground uppercase">Hoods</p>
                        <div class="flex flex-wrap gap-1.5">
                            <Link
                                v-for="hood in branch.hoods"
                                :key="hood.id"
                                :href="membersIndex({ query: { branch: branch.id, hood: hood.id } })"
                                class="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs transition-colors hover:bg-muted/60"
                            >
                                {{ hood.name }}
                                <span class="font-mono text-muted-foreground">{{ hood.members_count ?? 0 }}</span>
                            </Link>
                        </div>
                    </div>

                    <div class="space-y-2 text-sm">
                        <p class="flex items-center gap-2">
                            <UserRound
                                class="size-3.5 shrink-0 text-muted-foreground"
                            />
                            {{ branch.manager }}
                        </p>
                        <p
                            v-if="branch.phone"
                            class="flex items-center gap-2 text-muted-foreground"
                        >
                            <Phone class="size-3.5 shrink-0" />
                            {{ branch.phone }}
                        </p>
                        <p
                            v-if="branch.email"
                            class="flex items-center gap-2 truncate text-muted-foreground"
                        >
                            <Mail class="size-3.5 shrink-0" />
                            {{ branch.email }}
                        </p>
                    </div>

                    <div
                        class="flex flex-wrap items-center gap-2 border-t pt-3"
                    >
                        <Button as-child variant="outline" size="sm">
                            <Link
                                :href="
                                    membersIndex({
                                        query: { branch: branch.id },
                                    })
                                "
                            >
                                <Users class="size-3.5" />
                                View members
                            </Link>
                        </Button>
                        <template v-if="isMainAdmin">
                            <Button
                                variant="outline"
                                size="sm"
                                @click="openEdit(branch)"
                            >
                                <Pencil class="size-3.5" />
                                Edit
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                @click="confirmDelete(branch)"
                            >
                                <Trash2 class="size-3.5" />
                                Delete
                            </Button>
                        </template>
                    </div>
                </CardContent>
            </Card>

            <Card
                v-if="!branches.length"
                class="flex min-h-48 items-center justify-center border-dashed"
            >
                <p class="text-sm text-muted-foreground">No territories found.</p>
            </Card>
        </div>
    </div>

    <Dialog v-model:open="createOpen">
        <DialogContent class="sm:max-w-2xl">
            <form class="space-y-4" @submit.prevent="submitCreate">
                <DialogHeader>
                    <DialogTitle>New territory</DialogTitle>
                    <DialogDescription>
                        Create a territory and its admin login in one step.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label for="branch_name">Territory name</Label>
                        <Input id="branch_name" v-model="createForm.name" />
                        <InputError :message="createForm.errors.name" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="branch_location">Location</Label>
                        <Input
                            id="branch_location"
                            v-model="createForm.location"
                        />
                        <InputError :message="createForm.errors.location" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="branch_manager">Manager</Label>
                        <Input
                            id="branch_manager"
                            v-model="createForm.manager"
                        />
                        <InputError :message="createForm.errors.manager" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="branch_phone">Phone / Contact No. <span class="text-destructive">*</span></Label>
                        <Input
                            id="branch_phone"
                            v-model="createForm.phone"
                            type="tel"
                            required
                            :maxlength="createForm.phone.startsWith('09') ? 11 : 20"
                            placeholder="09171234567 or (02) 8555-1234"
                            @input="handlePhoneInput(createForm, $event)"
                        />
                        <p class="text-[11px] text-muted-foreground">Mobile (09XXXXXXXXX, max 11 digits) or landline (02) 8XXX-XXXX</p>
                        <InputError :message="createForm.errors.phone" />
                    </div>
                    <div class="space-y-1.5 sm:col-span-2">
                        <Label for="branch_email">Email <span class="text-destructive">*</span></Label>
                        <Input
                            id="branch_email"
                            type="email"
                            v-model="createForm.email"
                            required
                            placeholder="territory@templestreet.local"
                        />
                        <InputError :message="createForm.errors.email" />
                    </div>
                </div>

                <div class="space-y-1">
                    <p class="text-sm font-medium">Branch admin login</p>
                    <p class="text-xs text-muted-foreground">
                        This account can only see members in this branch.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label for="admin_username">Username</Label>
                        <Input
                            id="admin_username"
                            v-model="createForm.admin_username"
                            autocomplete="off"
                        />
                        <InputError
                            :message="createForm.errors.admin_username"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="admin_password">Password</Label>
                        <Input
                            id="admin_password"
                            type="password"
                            v-model="createForm.admin_password"
                            autocomplete="new-password"
                        />
                        <InputError
                            :message="createForm.errors.admin_password"
                        />
                    </div>
                    <div class="space-y-1.5 sm:col-span-2">
                        <Label for="admin_password_confirmation"
                            >Confirm password</Label
                        >
                        <Input
                            id="admin_password_confirmation"
                            type="password"
                            v-model="createForm.admin_password_confirmation"
                            autocomplete="new-password"
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="secondary"
                        @click="createOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="createForm.processing">
                        Create territory
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="editOpen">
        <DialogContent class="sm:max-w-2xl">
            <form class="space-y-4" @submit.prevent="submitEdit">
                <DialogHeader>
                    <DialogTitle>Edit territory</DialogTitle>
                    <DialogDescription>
                        Update territory details or reset the admin
                        password.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label for="edit_branch_name">Territory name</Label>
                        <Input id="edit_branch_name" v-model="editForm.name" />
                        <InputError :message="editForm.errors.name" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="edit_branch_location">Location</Label>
                        <Input
                            id="edit_branch_location"
                            v-model="editForm.location"
                        />
                        <InputError :message="editForm.errors.location" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="edit_branch_manager">Manager</Label>
                        <Input
                            id="edit_branch_manager"
                            v-model="editForm.manager"
                        />
                        <InputError :message="editForm.errors.manager" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="edit_branch_phone">Phone / Contact No. <span class="text-destructive">*</span></Label>
                        <Input
                            id="edit_branch_phone"
                            v-model="editForm.phone"
                            type="tel"
                            required
                            :maxlength="editForm.phone.startsWith('09') ? 11 : 20"
                            placeholder="09171234567 or (02) 8555-1234"
                            @input="handlePhoneInput(editForm, $event)"
                        />
                        <p class="text-[11px] text-muted-foreground">Mobile (09XXXXXXXXX, max 11 digits) or landline (02) 8XXX-XXXX</p>
                        <InputError :message="editForm.errors.phone" />
                    </div>
                    <div class="space-y-1.5 sm:col-span-2">
                        <Label for="edit_branch_email">Email <span class="text-destructive">*</span></Label>
                        <Input
                            id="edit_branch_email"
                            type="email"
                            v-model="editForm.email"
                            required
                            placeholder="territory@templestreet.local"
                        />
                        <InputError :message="editForm.errors.email" />
                    </div>
                </div>

                <div class="space-y-1">
                    <p class="text-sm font-medium">Reset admin password</p>
                    <p class="text-xs text-muted-foreground">
                        Leave blank to keep the current password.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label for="edit_admin_password">New password</Label>
                        <Input
                            id="edit_admin_password"
                            type="password"
                            v-model="editForm.admin_password"
                            autocomplete="new-password"
                        />
                        <InputError :message="editForm.errors.admin_password" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="edit_admin_password_confirmation"
                            >Confirm password</Label
                        >
                        <Input
                            id="edit_admin_password_confirmation"
                            type="password"
                            v-model="editForm.admin_password_confirmation"
                            autocomplete="new-password"
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="secondary"
                        @click="editOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="editForm.processing">
                        Save changes
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="deleteOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Delete territory?</DialogTitle>
                <DialogDescription>
                    {{ deleteTarget?.name }} will be deleted along with its
                    hoods, members, NFC cards and admin account. This cannot be undone.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="secondary" @click="deleteOpen = false">
                    Cancel
                </Button>
                <Button variant="destructive" @click="destroy">
                    Delete territory
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
