<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/temporary-password';

defineOptions({
    layout: {
        title: 'Change temporary password',
        description:
            'Your account was created with a temporary password. Choose a new password to continue.',
    },
});

defineProps<{
    passwordRules: string;
}>();
</script>

<template>
    <Head title="Change temporary password" />

    <Form
        v-bind="update.form()"
        :reset-on-success="['current_password', 'password', 'password_confirmation']"
        v-slot="{ errors, processing }"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="current_password"> Temporary password </Label>
                <PasswordInput
                    id="current_password"
                    name="current_password"
                    autocomplete="current-password"
                    class="mt-1 block w-full"
                    autofocus
                    placeholder="Temporary password"
                />
                <InputError :message="errors.current_password" />
            </div>

            <div class="grid gap-2">
                <Label for="password"> New password </Label>
                <PasswordInput
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    class="mt-1 block w-full"
                    placeholder="New password"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation"> Confirm new password </Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
                    class="mt-1 block w-full"
                    placeholder="Confirm new password"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :disabled="processing"
                data-test="change-temporary-password-button"
            >
                <Spinner v-if="processing" />
                Save password and continue
            </Button>
        </div>
    </Form>
</template>
