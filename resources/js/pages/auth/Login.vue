<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import PasskeyVerify from '@/components/PasskeyVerify.vue';

defineOptions({
    layout: {
        title: 'Log in to your console',
        description: 'Enter your username or email and password below',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
function fillDemo(username: string, pass: string): void {
    const emailEl = document.getElementById('email') as HTMLInputElement | null;
    const passEl = document.getElementById('password') as HTMLInputElement | null;

    if (emailEl) {
        emailEl.value = username;
        emailEl.dispatchEvent(new Event('input', { bubbles: true }));
        emailEl.dispatchEvent(new Event('change', { bubbles: true }));
        emailEl.focus();
    }

    if (passEl) {
        passEl.value = pass;
        passEl.dispatchEvent(new Event('input', { bubbles: true }));
        passEl.dispatchEvent(new Event('change', { bubbles: true }));
    }
}
</script>

<template>
    <Head title="Log in" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <PasskeyVerify />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">Username or email</Label>
                <Input
                    id="email"
                    type="text"
                    name="email"
                    required
                    v-focus
                    :tabindex="1"
                    autocomplete="username"
                    placeholder="admin"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">Password</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm"
                        :tabindex="5"
                    >
                        Forgot your password?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Password"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>Remember me</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Log in
            </Button>
        </div>

        <div
            class="rounded-xl border border-dashed border-border bg-muted/30 p-3.5 text-xs"
        >
            <div class="mb-2.5 flex items-center justify-between">
                <p class="font-mono text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                    Demo Accounts
                </p>
                <span class="text-[10px] text-muted-foreground">Click to fill</span>
            </div>

            <div class="grid gap-2">
                <button
                    type="button"
                    @click="fillDemo('fv_admin', 'fv123456')"
                    class="flex items-center justify-between rounded-lg border border-border/60 bg-background/70 px-3 py-2 text-left transition-colors hover:border-primary/50 hover:bg-accent hover:text-accent-foreground"
                >
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span class="font-medium text-foreground">Fairview Admin</span>
                            <span class="rounded bg-sky-500/10 px-1.5 py-0.2 text-[10px] font-semibold text-sky-600 dark:text-sky-400">Territory</span>
                        </div>
                        <p class="mt-0.5 font-mono text-[11px] text-muted-foreground">
                            fv_admin <span class="text-border">/</span> fv123456
                        </p>
                    </div>
                    <span class="text-[11px] font-medium text-primary">Use &rarr;</span>
                </button>

                <button
                    type="button"
                    @click="fillDemo('ts_admin', 'ts123456')"
                    class="flex items-center justify-between rounded-lg border border-border/60 bg-background/70 px-3 py-2 text-left transition-colors hover:border-primary/50 hover:bg-accent hover:text-accent-foreground"
                >
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span class="font-medium text-foreground">Tandang Sora Admin</span>
                            <span class="rounded bg-emerald-500/10 px-1.5 py-0.2 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">Territory</span>
                        </div>
                        <p class="mt-0.5 font-mono text-[11px] text-muted-foreground">
                            ts_admin <span class="text-border">/</span> ts123456
                        </p>
                    </div>
                    <span class="text-[11px] font-medium text-primary">Use &rarr;</span>
                </button>

                <button
                    type="button"
                    @click="fillDemo('admin', 'admin123')"
                    class="flex items-center justify-between rounded-lg border border-border/60 bg-background/70 px-3 py-2 text-left transition-colors hover:border-primary/50 hover:bg-accent hover:text-accent-foreground"
                >
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span class="font-medium text-foreground">Main Administrator</span>
                            <span class="rounded bg-primary/10 px-1.5 py-0.2 text-[10px] font-semibold text-primary">Full Access</span>
                        </div>
                        <p class="mt-0.5 font-mono text-[11px] text-muted-foreground">
                            admin <span class="text-border">/</span> admin123
                        </p>
                    </div>
                    <span class="text-[11px] font-medium text-primary">Use &rarr;</span>
                </button>
            </div>
        </div>
    </Form>
</template>
