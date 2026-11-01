<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Email verification',
        description:
            'Please verify your email address by clicking on the link we just emailed to you.',
    },
});

defineProps<{
    status?: string;
}>();

const verifyingInstant = ref(false);

function instantVerify(): void {
    verifyingInstant.value = true;
    router.post('/email/instant-verify', {}, {
        onFinish: () => {
            verifyingInstant.value = false;
        },
    });
}
</script>

<template>
    <Head title="Email verification" />

    <div
        v-if="status === 'verification-link-sent'"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        A new verification link has been sent to the email address you provided
        during registration.
    </div>

    <div class="space-y-4">
        <Button
            type="button"
            class="w-full"
            :disabled="verifyingInstant"
            @click="instantVerify"
        >
            <Spinner v-if="verifyingInstant" />
            Verify Now &amp; Continue
        </Button>

        <Form
            v-bind="send.form()"
            class="space-y-4 text-center"
            v-slot="{ processing }"
        >
            <Button :disabled="processing" variant="secondary" class="w-full">
                <Spinner v-if="processing" />
                Resend verification email
            </Button>

            <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
                Log out
            </TextLink>
        </Form>
    </div>
</template>
