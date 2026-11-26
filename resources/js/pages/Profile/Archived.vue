<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Archive, ShieldAlert } from '@lucide/vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';

defineProps<{
    member: {
        full_name: string;
        initials: string;
        avatar_url: string | null;
        archived_on: string | null;
    };
}>();
</script>

<template>
    <Head :title="`${member.full_name} — Profile unavailable`" />

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
                            rgba(244, 63, 94, 0.18),
                            transparent 70%
                        );
                    "
                />

                <div class="relative text-center">
                    <div
                        class="inline-flex items-center gap-1.5 rounded-full border border-zinc-700 bg-zinc-900 px-3 py-1 font-mono text-[10px] tracking-[0.18em] text-zinc-400 uppercase"
                    >
                        <ShieldAlert class="size-3 text-rose-400" />
                        Temple Street Demo System
                    </div>

                    <Avatar
                        class="mx-auto mt-5 size-20 border border-zinc-700 grayscale"
                    >
                        <AvatarImage
                            v-if="member.avatar_url"
                            :src="member.avatar_url"
                            :alt="member.full_name"
                            class="object-cover"
                        />
                        <AvatarFallback
                            class="bg-zinc-800 text-2xl font-semibold text-zinc-400"
                        >
                            {{ member.initials }}
                        </AvatarFallback>
                    </Avatar>

                    <h1 class="mt-4 text-2xl font-semibold tracking-tight">
                        {{ member.full_name }}
                    </h1>

                    <div class="mt-2 flex justify-center">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border border-rose-400/30 bg-rose-400/10 px-2.5 py-0.5 text-xs font-medium text-rose-300"
                        >
                            <Archive class="size-3" />
                            Former member
                        </span>
                    </div>

                    <div class="my-6 border-t border-zinc-800" />

                    <p class="text-sm font-medium text-zinc-200">
                        This profile is no longer active.
                    </p>
                    <p class="mt-2 text-sm text-zinc-400">
                        {{ member.full_name }} is no longer an active member of
                        the registry. This NFC card link is kept for reference
                        only.
                    </p>

                    <p
                        v-if="member.archived_on"
                        class="mt-4 font-mono text-[11px] tracking-wide text-zinc-500"
                    >
                        Archived {{ member.archived_on }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
