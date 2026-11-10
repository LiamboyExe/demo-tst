<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ShieldCheck, UserCheck } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/activation';

defineProps<{
    member: {
        full_name: string;
        initials: string;
        profile_slug: string;
    };
    passwordRules: string;
}>();
</script>

<template>
    <Head :title="`Create Your Account — ${member.full_name}`" />

    <div
        class="flex min-h-screen items-center justify-center bg-zinc-950 p-4 text-zinc-100"
    >
        <div class="w-full max-w-md">
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
                        Step 2: Choose Permanent Credentials
                    </div>

                    <Avatar
                        class="mx-auto mt-4 size-16 border border-emerald-400/30"
                    >
                        <AvatarFallback
                            class="bg-emerald-400/10 text-xl font-semibold text-emerald-300"
                        >
                            {{ member.initials }}
                        </AvatarFallback>
                    </Avatar>

                    <h1 class="mt-3 text-xl font-semibold tracking-tight">
                        {{ member.full_name }}
                    </h1>
                    <p class="mt-1 text-xs text-zinc-400">
                        Create your permanent username and password. You will use
                        these to log in moving forward.
                    </p>
                </div>

                <div class="my-6 border-t border-zinc-800" />

                <Form
                    v-bind="store.form(member.profile_slug)"
                    :reset-on-success="['password', 'password_confirmation']"
                    v-slot="{ errors, processing }"
                    class="space-y-4"
                >
                    <div class="space-y-2">
                        <Label for="username" class="text-xs text-zinc-300">
                            New Username
                        </Label>
                        <Input
                            id="username"
                            name="username"
                            type="text"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="Choose your username"
                            class="border-zinc-800 bg-zinc-950/80 text-zinc-100 placeholder:text-zinc-600 focus:border-emerald-500"
                        />
                        <InputError :message="errors.username" />
                    </div>

                    <div class="space-y-2">
                        <Label for="password" class="text-xs text-zinc-300">
                            New Password
                        </Label>
                        <PasswordInput
                            id="password"
                            name="password"
                            required
                            autocomplete="new-password"
                            placeholder="New password"
                            :passwordrules="passwordRules"
                            class="border-zinc-800 bg-zinc-950/80 text-zinc-100 placeholder:text-zinc-600 focus:border-emerald-500"
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <div class="space-y-2">
                        <Label for="password_confirmation" class="text-xs text-zinc-300">
                            Confirm New Password
                        </Label>
                        <PasswordInput
                            id="password_confirmation"
                            name="password_confirmation"
                            required
                            autocomplete="new-password"
                            placeholder="Confirm new password"
                            :passwordrules="passwordRules"
                            class="border-zinc-800 bg-zinc-950/80 text-zinc-100 placeholder:text-zinc-600 focus:border-emerald-500"
                        />
                        <InputError :message="errors.password_confirmation" />
                    </div>

                    <Button
                        type="submit"
                        class="w-full bg-emerald-600 text-white hover:bg-emerald-500"
                        :disabled="processing"
                    >
                        <Spinner v-if="processing" />
                        <UserCheck v-else class="mr-1.5 size-4" />
                        Activate Account & View Profile
                    </Button>
                </Form>
            </div>
        </div>
    </div>
</template>
