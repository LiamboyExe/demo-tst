<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Building2,
    CakeSlice,
    CalendarDays,
    CreditCard,
    EyeOff,
    Home,
    LockKeyhole,
    LogOut,
    Mail,
    MapPin,
    MapPinned,
    Phone,
    ShieldAlert,
    ShieldCheck,
} from '@lucide/vue';
import { computed } from 'vue';
import { formatDate } from '@/lib/format';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { login, logout } from '@/routes';
import { show as profileShow } from '@/routes/profile';
import type { Member, User } from '@/types';

const props = defineProps<{
    member: Member;
    canViewSensitive: boolean;
    sensitive: {
        email: string;
        phone: string | null;
        address: string | null;
        birthdate: string | null;
        age: number | null;
    } | null;
}>();

const authUser = computed(() => usePage().props.auth?.user as User | undefined);

// Remember this profile so the login flow returns the member right here.
const loginHref = login({
    query: { intended: profileShow.url(props.member.profile_slug) },
}).url;

const memberId = `TS-${String(props.member.id).padStart(4, '0')}`;

function handleLogout(): void {
    router.flushAll();
}

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

                    <Avatar
                        class="mx-auto mt-5 size-20 border border-emerald-400/30"
                    >
                        <AvatarImage
                            v-if="member.avatar_url"
                            :src="member.avatar_url"
                            :alt="member.full_name"
                            class="object-cover"
                        />
                        <AvatarFallback
                            class="bg-emerald-400/10 text-2xl font-semibold text-emerald-300"
                        >
                            {{ member.initials }}
                        </AvatarFallback>
                    </Avatar>

                    <h1 class="mt-4 text-2xl font-semibold tracking-tight">
                        {{ member.full_name }}
                    </h1>

                    <p
                        v-if="member.alias"
                        class="mt-1 text-sm text-emerald-300/90"
                    >
                        a.k.a.
                        <span class="font-medium">“{{ member.alias }}”</span>
                    </p>

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
                            <MapPinned class="size-3" /> Hood
                        </p>
                        <p class="mt-1 text-sm">
                            {{ member.hood?.name ?? 'N/A' }}
                        </p>
                    </div>
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
                    <div class="sm:col-span-2">
                        <p
                            class="flex items-center gap-1 text-[10px] tracking-[0.14em] text-zinc-500 uppercase"
                        >
                            <ShieldCheck class="size-3" /> Member ID
                        </p>
                        <p class="mt-1 font-mono text-sm">{{ memberId }}</p>
                    </div>
                </div>

                <!-- Private details: only rendered for the member's own
                     account (or an authorized admin). -->
                <template v-if="canViewSensitive && sensitive">
                    <div class="my-6 border-t border-zinc-800" />

                    <div
                        class="rounded-xl border border-emerald-400/30 bg-emerald-400/5 p-4"
                    >
                        <p
                            class="flex items-center gap-1 text-[10px] tracking-[0.14em] text-emerald-300/80 uppercase"
                        >
                            <LockKeyhole class="size-3" /> Private information
                        </p>

                        <div
                            class="mt-3 grid grid-cols-1 gap-4 text-left sm:grid-cols-2"
                        >
                            <div>
                                <p
                                    class="flex items-center gap-1 text-[10px] tracking-[0.14em] text-zinc-500 uppercase"
                                >
                                    <CakeSlice class="size-3" /> Birthdate
                                </p>
                                <p class="mt-1 text-sm">
                                    {{
                                        sensitive.birthdate
                                            ? formatDate(sensitive.birthdate)
                                            : 'N/A'
                                    }}
                                </p>
                            </div>
                            <div>
                                <p
                                    class="flex items-center gap-1 text-[10px] tracking-[0.14em] text-zinc-500 uppercase"
                                >
                                    <CalendarDays class="size-3" /> Age
                                </p>
                                <p class="mt-1 text-sm">
                                    {{ sensitive.age ?? 'N/A' }}
                                </p>
                            </div>
                            <div>
                                <p
                                    class="flex items-center gap-1 text-[10px] tracking-[0.14em] text-zinc-500 uppercase"
                                >
                                    <Mail class="size-3" /> Email
                                </p>
                                <p class="mt-1 text-sm break-all">
                                    {{ sensitive.email }}
                                </p>
                            </div>
                            <div>
                                <p
                                    class="flex items-center gap-1 text-[10px] tracking-[0.14em] text-zinc-500 uppercase"
                                >
                                    <Phone class="size-3" /> Phone
                                </p>
                                <p class="mt-1 text-sm">
                                    {{ sensitive.phone ?? 'N/A' }}
                                </p>
                            </div>
                            <div class="sm:col-span-2">
                                <p
                                    class="flex items-center gap-1 text-[10px] tracking-[0.14em] text-zinc-500 uppercase"
                                >
                                    <Home class="size-3" /> Address
                                </p>
                                <p class="mt-1 text-sm">
                                    {{ sensitive.address ?? 'N/A' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="authUser"
                        class="mt-3 flex items-center justify-between gap-2 text-xs text-zinc-500"
                    >
                        <p class="flex items-center gap-1">
                            <ShieldCheck class="size-3 text-emerald-400" />
                            Signed in as {{ authUser.name }}
                        </p>
                        <Link
                            :href="logout()"
                            as="button"
                            class="inline-flex items-center gap-1 text-zinc-400 transition-colors hover:text-zinc-200"
                            @click="handleLogout"
                        >
                            <LogOut class="size-3" />
                            Log out
                        </Link>
                    </div>
                </template>

                <!-- Signed in, but this profile belongs to another member. -->
                <div
                    v-else-if="authUser"
                    class="my-6 border-t border-zinc-800 pt-5"
                >
                    <div
                        class="flex items-start gap-3 rounded-xl border border-amber-400/30 bg-amber-400/5 p-4"
                    >
                        <ShieldAlert
                            class="mt-0.5 size-5 shrink-0 text-amber-300"
                        />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-amber-200">
                                Private information hidden
                            </p>
                            <p
                                class="mt-1 text-xs leading-relaxed text-amber-200/70"
                            >
                                You are signed in as {{ authUser.name }}.
                                Private details are only shown on the profile
                                of the member linked to your own account.
                            </p>
                            <Link
                                :href="logout()"
                                as="button"
                                class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-amber-200/80 transition-colors hover:text-amber-100"
                                @click="handleLogout"
                            >
                                <LogOut class="size-3" />
                                Log out
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Anonymous visitor: invite the member to sign in. -->
                <div v-else class="my-6 border-t border-zinc-800 pt-5">
                    <div
                        class="rounded-xl border border-dashed border-zinc-700 bg-zinc-950/60 p-4 text-center"
                    >
                        <EyeOff
                            class="mx-auto size-5 text-zinc-500"
                        />
                        <p
                            class="mt-2 text-[10px] tracking-[0.14em] text-zinc-500 uppercase"
                        >
                            Private information
                        </p>
                        <p
                            class="mt-1 text-xs leading-relaxed text-zinc-400"
                        >
                            Contact details are only visible to this member.
                        </p>
                        <Button as-child class="mt-4 w-full">
                            <Link :href="loginHref">
                                <LockKeyhole class="size-4" />
                                View Sensitive Information
                            </Link>
                        </Button>
                        <p class="mt-2 text-[11px] text-zinc-600">
                            Member login — you will return to this profile.
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
