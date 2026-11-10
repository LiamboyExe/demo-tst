<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, KeyRound, LockKeyhole, ShieldCheck } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { verify } from '@/routes/activation';
import { show as profileShow } from '@/routes/profile';

const props = defineProps<{
    member: {
        full_name: string;
        initials: string;
        profile_slug: string;
    };
}>();

const loginHref = login({
    query: { intended: profileShow.url(props.member.profile_slug) },
}).url;
</script>

<template>
    <Head :title="`Activate Account — ${member.full_name}`" />

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
                        Account Activation
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
                        Enter your temporary credentials to begin activating your
                        account.
                    </p>
                </div>

                <div class="my-6 border-t border-zinc-800" />

                <Form
                    v-bind="verify.form(member.profile_slug)"
                    :reset-on-success="['temp_password']"
                    v-slot="{ errors, processing }"
                    class="space-y-4"
                >
                    <div class="space-y-2">
                        <Label for="temp_username" class="text-xs text-zinc-300">
                            Temporary Username
                        </Label>
                        <Input
                            id="temp_username"
                            name="temp_username"
                            type="text"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="e.g. member-abcd"
                            class="border-zinc-800 bg-zinc-950/80 text-zinc-100 placeholder:text-zinc-600 focus:border-emerald-500"
                        />
                        <InputError :message="errors.temp_username" />
                    </div>

                    <div class="space-y-2">
                        <Label for="temp_password" class="text-xs text-zinc-300">
                            Temporary Password
                        </Label>
                        <PasswordInput
                            id="temp_password"
                            name="temp_password"
                            required
                            autocomplete="current-password"
                            placeholder="Temporary password"
                            class="border-zinc-800 bg-zinc-950/80 text-zinc-100 placeholder:text-zinc-600 focus:border-emerald-500"
                        />
                        <InputError :message="errors.temp_password" />
                    </div>

                    <Button
                        type="submit"
                        class="w-full bg-emerald-600 text-white hover:bg-emerald-500"
                        :disabled="processing"
                    >
                        <Spinner v-if="processing" />
                        <KeyRound v-else class="mr-1.5 size-4" />
                        Verify and Continue
                    </Button>
                </Form>

                <div class="mt-6 space-y-2 text-center text-xs text-zinc-500">
                    <p>
                        Already activated your account?
                        <Link
                            :href="loginHref"
                            class="text-emerald-400 hover:text-emerald-300 underline"
                        >
                            Log in here
                        </Link>
                    </p>

                    <div>
                        <Link
                            :href="profileShow.url(member.profile_slug)"
                            class="inline-flex items-center gap-1 text-zinc-400 hover:text-zinc-200"
                        >
                            <ArrowLeft class="size-3" />
                            Back to public profile
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
