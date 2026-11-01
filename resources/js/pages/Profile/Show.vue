<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Building2,
    CalendarDays,
    CreditCard,
    Home,
    Mail,
    MapPin,
    Phone,
    ShieldCheck,
} from '@lucide/vue';
import { formatDate } from '@/lib/format';
import type { Member } from '@/types';

const props = defineProps<{
    member: Member;
}>();

const memberId = `TS-${String(props.member.id).padStart(4, '0')}`;

function badgeClass(value: string): string {
    switch (value) {
        case 'Active':
            return 'border-emerald-400/30 bg-emerald-400/10 text-emerald-300';
        case 'Pending':
            return 'border-amber-400/30 bg-amber-400/10 text-amber-300';
        case 'Inactive':
            return 'border-zinc-500/30 bg-zinc-500/10 text-zinc-300';
        case 'Lost':
            return 'border-rose-400/30 bg-rose-400/10 text-rose-300';
        case 'High Table Council':
            return 'border-amber-400/40 bg-amber-400/15 text-amber-300 font-semibold';
        case 'Shot Caller':
            return 'border-purple-400/40 bg-purple-400/15 text-purple-300 font-medium';
        case 'Alas':
            return 'border-indigo-400/40 bg-indigo-400/15 text-indigo-300 font-medium';
        case 'Pointman':
            return 'border-sky-400/40 bg-sky-400/15 text-sky-300 font-medium';
        case 'Carnal':
            return 'border-emerald-400/40 bg-emerald-400/15 text-emerald-300 font-medium';
        case 'Carnala':
            return 'border-pink-400/40 bg-pink-400/15 text-pink-300 font-medium';
        case 'Premium':
            return 'border-violet-400/30 bg-violet-400/10 text-violet-300';
        case 'Student':
            return 'border-sky-400/30 bg-sky-400/10 text-sky-300';
        case 'Senior':
            return 'border-orange-400/30 bg-orange-400/10 text-orange-300';
        default:
            return 'border-zinc-500/30 bg-zinc-500/10 text-zinc-300';
    }
}
</script>

<template>
    <Head :title="`${member.full_name} — Member Profile`" />

    <div
        class="flex min-h-screen items-center justify-center bg-zinc-950 p-4 text-zinc-100"
    >
        <div class="w-full max-w-lg">
            <div
                class="relative overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-900/70 p-6 shadow-2xl backdrop-blur sm:p-8"
            >
                <div
                    class="pointer-events-none absolute inset-x-0 top-0 h-40 opacity-50"
                    style="
                        background: radial-gradient(
                            circle at 50% 0%,
                            rgba(16, 185, 129, 0.25),
                            transparent 70%
                        );
                    "
                />

                <div class="relative text-center">
                    <div
                        class="inline-flex items-center gap-1.5 rounded-full border border-zinc-700 bg-zinc-900 px-3 py-1 font-mono text-[10px] tracking-[0.18em] text-zinc-400 uppercase"
                    >
                        <ShieldCheck class="size-3 text-emerald-400" />
                        Temple Street Demo System
                    </div>

                    <div
                        class="mx-auto mt-5 flex size-20 items-center justify-center rounded-full border border-emerald-400/30 bg-emerald-400/10 text-2xl font-semibold text-emerald-300"
                    >
                        {{ member.initials }}
                    </div>

                    <h1 class="mt-4 text-2xl font-semibold tracking-tight">
                        {{ member.full_name }}
                    </h1>

                    <div class="mt-2 flex flex-wrap justify-center gap-2">
                        <span
                            class="rounded-full border px-2.5 py-0.5 text-xs font-medium"
                            :class="badgeClass(member.membership_type)"
                        >
                            {{ member.membership_type }}
                        </span>
                        <span
                            class="rounded-full border px-2.5 py-0.5 text-xs font-medium"
                            :class="badgeClass(member.status)"
                        >
                            {{ member.status }}
                        </span>
                    </div>

                    <p
                        class="mt-3 flex flex-wrap items-center justify-center gap-1.5 text-sm text-zinc-400"
                    >
                        <Building2 class="size-3.5" />
                        {{ member.branch?.name }}
                        <span class="text-zinc-600">·</span>
                        <MapPin class="size-3.5" />
                        {{ member.branch?.location }}
                    </p>
                </div>

                <div class="my-6 border-t border-zinc-800" />

                <div class="grid grid-cols-1 gap-4 text-left sm:grid-cols-2">
                    <div>
                        <p
                            class="flex items-center gap-1 text-[10px] tracking-[0.14em] text-zinc-500 uppercase"
                        >
                            <CalendarDays class="size-3" /> Member since
                        </p>
                        <p class="mt-1 text-sm">
                            {{ formatDate(member.join_date) }}
                        </p>
                    </div>
                    <div>
                        <p
                            class="flex items-center gap-1 text-[10px] tracking-[0.14em] text-zinc-500 uppercase"
                        >
                            <ShieldCheck class="size-3" /> Member ID
                        </p>
                        <p class="mt-1 font-mono text-sm">{{ memberId }}</p>
                    </div>
                    <div>
                        <p
                            class="flex items-center gap-1 text-[10px] tracking-[0.14em] text-zinc-500 uppercase"
                        >
                            <Mail class="size-3" /> Email
                        </p>
                        <p class="mt-1 text-sm break-all">
                            {{ member.email }}
                        </p>
                    </div>
                    <div>
                        <p
                            class="flex items-center gap-1 text-[10px] tracking-[0.14em] text-zinc-500 uppercase"
                        >
                            <Phone class="size-3" /> Phone
                        </p>
                        <p class="mt-1 text-sm">
                            {{ member.phone ?? 'N/A' }}
                        </p>
                    </div>
                    <div class="sm:col-span-2">
                        <p
                            class="flex items-center gap-1 text-[10px] tracking-[0.14em] text-zinc-500 uppercase"
                        >
                            <Home class="size-3" /> Address
                        </p>
                        <p class="mt-1 text-sm">
                            {{ member.address ?? 'N/A' }}
                        </p>
                    </div>
                </div>

                <template v-if="member.nfc_card">
                    <div class="my-6 border-t border-zinc-800" />

                    <div
                        class="rounded-xl border border-zinc-800 bg-zinc-950/60 p-4"
                    >
                        <p
                            class="flex items-center gap-1 text-[10px] tracking-[0.14em] text-zinc-500 uppercase"
                        >
                            <CreditCard class="size-3" /> NFC Card
                        </p>
                        <div
                            class="mt-1 flex flex-wrap items-center justify-between gap-2"
                        >
                            <p class="font-mono text-sm text-zinc-300">
                                {{ member.nfc_card.card_uid }}
                            </p>
                            <span
                                class="rounded-full border px-2 py-0.5 text-xs"
                                :class="badgeClass(member.nfc_card.card_status)"
                            >
                                {{ member.nfc_card.card_status }}
                            </span>
                        </div>
                    </div>
                </template>

                <div class="my-6 border-t border-zinc-800" />

                <p
                    class="text-center text-[11px] leading-relaxed text-zinc-500"
                >
                    This is a demo profile. All data is fictional and this
                    system is for demonstration purposes only.
                </p>
            </div>

            <p class="mt-4 text-center text-xs text-zinc-600">
                Temple Street · Membership Management Demo
            </p>
        </div>
    </div>
</template>
