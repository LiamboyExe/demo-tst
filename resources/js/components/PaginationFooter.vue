<script setup lang="ts">
import { Button } from '@/components/ui/button';

defineProps<{
    from: number | null;
    to: number | null;
    total: number;
    currentPage: number;
    lastPage: number;
}>();

const emit = defineEmits<{
    previous: [];
    next: [];
}>();
</script>

<template>
    <div
        class="flex flex-col gap-2 px-4 py-3 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between"
    >
        <p>Showing {{ from ?? 0 }}–{{ to ?? 0 }} of {{ total }}</p>
        <div class="flex items-center gap-2">
            <Button
                variant="outline"
                size="sm"
                aria-label="Previous page"
                :disabled="currentPage <= 1"
                @click="emit('previous')"
            >
                Previous
            </Button>
            <span class="font-mono">{{ currentPage }} / {{ lastPage }}</span>
            <Button
                variant="outline"
                size="sm"
                aria-label="Next page"
                :disabled="currentPage >= lastPage"
                @click="emit('next')"
            >
                Next
            </Button>
        </div>
    </div>
</template>
