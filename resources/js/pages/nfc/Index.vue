<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Copy, Link2, Nfc, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
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
import { formatDate, today } from '@/lib/format';
import {
    destroy as destroyCard,
    index as nfcCardsIndex,
    store as storeCard,
    update as updateCard,
} from '@/routes/nfc-cards';
import type { Member, NfcCard, NfcCardStatus } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'NFC Cards',
                href: nfcCardsIndex(),
            },
        ],
    },
});

const props = defineProps<{
    cards: NfcCard[];
    membersWithoutCards: Member[];
    statuses: NfcCardStatus[];
}>();

const assignOpen = ref(false);
const editOpen = ref(false);
const deleteOpen = ref(false);
const editing = ref<NfcCard | null>(null);
const deleteTarget = ref<NfcCard | null>(null);


const assignForm = useForm({
    member_id: 0,
    card_uid: generateUid(),
    profile_url: '',
    card_status: 'Active' as NfcCardStatus,
    issued_date: today(),
    notes: '',
});

const editForm = useForm({
    card_uid: '',
    profile_url: '',
    card_status: 'Active' as NfcCardStatus,
    issued_date: today(),
    notes: '',
});

const selectedMember = computed(() =>
    props.membersWithoutCards.find(
        (member) => member.id === assignForm.member_id,
    ),
);

const stats = computed(() => ({
    total: props.cards.length,
    active: props.cards.filter((card) => card.card_status === 'Active').length,
    lost: props.cards.filter((card) => card.card_status === 'Lost').length,
}));

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

function generateUid(): string {
    const alphabet = 'ABCDEF0123456789';
    let suffix = '';

    for (let index = 0; index < 8; index++) {
        suffix += alphabet[Math.floor(Math.random() * alphabet.length)];
    }

    return `NFC-${suffix}`;
}

function openAssign(): void {
    assignForm.reset();
    assignForm.clearErrors();
    assignForm.member_id = props.membersWithoutCards[0]?.id ?? 0;
    assignForm.card_uid = generateUid();
    assignForm.profile_url = '';
    assignForm.card_status = 'Active';
    assignForm.issued_date = today();
    assignForm.notes = '';
    assignOpen.value = true;
}

function submitAssign(): void {
    assignForm.post(storeCard().url, {
        preserveScroll: true,
        onSuccess: () => {
            assignOpen.value = false;
            assignForm.reset();
        },
    });
}

function openEdit(card: NfcCard): void {
    editing.value = card;
    editForm.clearErrors();
    editForm.card_uid = card.card_uid;
    editForm.profile_url = card.profile_url;
    editForm.card_status = card.card_status;
    editForm.issued_date = card.issued_date.slice(0, 10);
    editForm.notes = card.notes ?? '';
    editOpen.value = true;
}

function submitEdit(): void {
    if (!editing.value) {
        return;
    }

    editForm.patch(updateCard(editing.value.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            editOpen.value = false;
            editing.value = null;
        },
    });
}

function confirmDelete(card: NfcCard): void {
    deleteTarget.value = card;
    deleteOpen.value = true;
}

function destroy(): void {
    if (!deleteTarget.value) {
        return;
    }

    router.delete(destroyCard(deleteTarget.value.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            deleteOpen.value = false;
            deleteTarget.value = null;
        },
    });
}

async function copyLink(url: string): Promise<void> {
    try {
        await navigator.clipboard.writeText(url);
        toast.success('Profile link copied.');
    } catch {
        toast.error('Could not copy the link.');
    }
}


</script>

<template>
    <Head title="NFC Cards" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"
        >
            <div>
                <p
                    class="font-mono text-[11px] tracking-[0.2em] text-muted-foreground uppercase"
                >
                    Access layer
                </p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight">
                    NFC Cards
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ stats.total }} card{{ stats.total === 1 ? '' : 's' }} ·
                    {{ stats.active }} active · {{ stats.lost }} lost
                </p>
            </div>

            <Button @click="openAssign">
                <Plus class="size-4" />
                Assign card
            </Button>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Card
                v-for="card in cards"
                :key="card.id"
                class="gap-0 overflow-hidden py-0"
            >
                <div
                    class="relative overflow-hidden bg-zinc-950 p-5 text-zinc-50"
                >
                    <div
                        class="pointer-events-none absolute inset-0 opacity-40"
                        style="
                            background:
                                radial-gradient(
                                    circle at 85% 15%,
                                    rgba(16, 185, 129, 0.35),
                                    transparent 45%
                                ),
                                radial-gradient(
                                    circle at 10% 90%,
                                    rgba(59, 130, 246, 0.25),
                                    transparent 50%
                                );
                        "
                    />
                    <div class="relative flex items-start justify-between">
                        <div>
                            <p
                                class="font-mono text-[10px] tracking-[0.25em] text-zinc-400 uppercase"
                            >
                                Temple Street
                            </p>
                            <p class="mt-0.5 text-sm font-medium">
                                Member Access Card
                            </p>
                        </div>
                        <Nfc class="size-6 text-zinc-400" />
                    </div>

                    <div class="relative mt-8 flex items-end justify-between">
                        <div>
                            <p
                                class="font-mono text-xs tracking-[0.15em] text-zinc-300"
                            >
                                {{ card.card_uid }}
                            </p>
                            <p class="mt-3 text-base font-semibold">
                                {{ card.member?.full_name }}
                            </p>
                            <p class="text-xs text-zinc-400">
                                {{ card.member?.branch?.name }}
                            </p>
                        </div>
                        <span
                            class="rounded-full border px-2 py-0.5 font-mono text-[10px] uppercase"
                            :class="
                                card.card_status === 'Active'
                                    ? 'border-emerald-400/40 bg-emerald-400/10 text-emerald-300'
                                    : card.card_status === 'Lost'
                                      ? 'border-rose-400/40 bg-rose-400/10 text-rose-300'
                                      : 'border-zinc-500/40 bg-zinc-500/10 text-zinc-300'
                            "
                        >
                            {{ card.card_status }}
                        </span>
                    </div>
                </div>

                <CardContent class="space-y-4 pt-4 pb-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs text-muted-foreground">
                            Issued {{ formatDate(card.issued_date) }}
                        </span>
                        <Badge
                            v-if="card.notes"
                            variant="outline"
                            class="max-w-full truncate text-[10px]"
                        >
                            {{ card.notes }}
                        </Badge>
                    </div>

                    <a
                        :href="card.profile_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex items-center gap-1.5 truncate text-xs text-muted-foreground hover:text-foreground"
                    >
                        <Link2 class="size-3.5 shrink-0" />
                        <span class="truncate">{{ card.profile_url }}</span>
                    </a>

                    <div class="flex flex-wrap gap-2 border-t pt-3">
                        <Button
                            variant="outline"
                            size="sm"
                            @click="copyLink(card.profile_url)"
                        >
                            <Copy class="size-3.5" />
                            Copy link
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="openEdit(card)"
                        >
                            <Pencil class="size-3.5" />
                            Edit
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                            @click="confirmDelete(card)"
                        >
                            <Trash2 class="size-3.5" />
                            Delete
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card
                v-if="!cards.length"
                class="flex min-h-48 items-center justify-center border-dashed md:col-span-2 xl:col-span-3"
            >
                <div class="text-center">
                    <Nfc class="mx-auto size-8 text-muted-foreground" />
                    <p class="mt-2 text-sm text-muted-foreground">
                        No NFC cards assigned yet.
                    </p>
                </div>
            </Card>
        </div>
    </div>

    <Dialog v-model:open="assignOpen">
        <DialogContent>
            <form class="space-y-4" @submit.prevent="submitAssign">
                <DialogHeader>
                    <DialogTitle>Assign NFC card</DialogTitle>
                    <DialogDescription>
                        Link a card UID to a member. The public profile URL is
                        generated automatically.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-1.5">
                    <Label for="assign_member">Member</Label>
                    <select
                        id="assign_member"
                        v-model.number="assignForm.member_id"
                        :class="selectClass"
                        :disabled="!membersWithoutCards.length"
                    >
                        <option :value="0" disabled>
                            Select a member without a card
                        </option>
                        <option
                            v-for="member in membersWithoutCards"
                            :key="member.id"
                            :value="member.id"
                        >
                            {{ member.full_name }} — {{ member.branch?.name }}
                        </option>
                    </select>
                    <InputError :message="assignForm.errors.member_id" />
                    <p
                        v-if="!membersWithoutCards.length"
                        class="text-xs text-muted-foreground"
                    >
                        Every visible member already has a card.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label for="assign_uid">Card UID</Label>
                        <Input
                            id="assign_uid"
                            v-model="assignForm.card_uid"
                            class="font-mono"
                        />
                        <InputError :message="assignForm.errors.card_uid" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="assign_status">Status</Label>
                        <select
                            id="assign_status"
                            v-model="assignForm.card_status"
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
                        <InputError :message="assignForm.errors.card_status" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="assign_issued">Issued date</Label>
                        <Input
                            id="assign_issued"
                            type="date"
                            v-model="assignForm.issued_date"
                        />
                        <InputError :message="assignForm.errors.issued_date" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="assign_url">Profile URL (optional)</Label>
                        <Input
                            id="assign_url"
                            v-model="assignForm.profile_url"
                            placeholder="Auto-generated"
                        />
                        <InputError :message="assignForm.errors.profile_url" />
                    </div>
                </div>

                <div
                    v-if="selectedMember"
                    class="rounded-lg border bg-muted/40 p-3 text-xs"
                >
                    <p
                        class="font-mono text-[10px] text-muted-foreground uppercase"
                    >
                        Target profile
                    </p>
                    <p class="mt-1 truncate">
                        {{ selectedMember.profile_url }}
                    </p>
                </div>

                <div class="space-y-1.5">
                    <Label for="assign_notes">Notes</Label>
                    <Input id="assign_notes" v-model="assignForm.notes" />
                    <InputError :message="assignForm.errors.notes" />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="secondary"
                        @click="assignOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        :disabled="
                            assignForm.processing || !membersWithoutCards.length
                        "
                    >
                        Assign card
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="editOpen">
        <DialogContent>
            <form class="space-y-4" @submit.prevent="submitEdit">
                <DialogHeader>
                    <DialogTitle>Edit card</DialogTitle>
                    <DialogDescription>
                        Update card details for
                        {{ editing?.member?.full_name }}.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label for="edit_uid">Card UID</Label>
                        <Input
                            id="edit_uid"
                            v-model="editForm.card_uid"
                            class="font-mono"
                        />
                        <InputError :message="editForm.errors.card_uid" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="edit_status">Status</Label>
                        <select
                            id="edit_status"
                            v-model="editForm.card_status"
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
                        <InputError :message="editForm.errors.card_status" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="edit_issued">Issued date</Label>
                        <Input
                            id="edit_issued"
                            type="date"
                            v-model="editForm.issued_date"
                        />
                        <InputError :message="editForm.errors.issued_date" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="edit_url">Profile URL</Label>
                        <Input id="edit_url" v-model="editForm.profile_url" />
                        <InputError :message="editForm.errors.profile_url" />
                    </div>
                </div>

                <div class="space-y-1.5">
                    <Label for="edit_notes">Notes</Label>
                    <Input id="edit_notes" v-model="editForm.notes" />
                    <InputError :message="editForm.errors.notes" />
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
                <DialogTitle>Remove card?</DialogTitle>
                <DialogDescription>
                    The NFC card of {{ deleteTarget?.member?.full_name }} will
                    be removed. The member profile stays intact.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="secondary" @click="deleteOpen = false">
                    Cancel
                </Button>
                <Button variant="destructive" @click="destroy">
                    Remove card
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
