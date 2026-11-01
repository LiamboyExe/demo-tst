<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { PanelLeftClose, PanelLeftOpen } from '@lucide/vue';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useSidebar } from './utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();

const { isMobile, state, toggleSidebar } = useSidebar();

const isOpen = () => !isMobile.value && state.value === 'expanded';
</script>

<template>
    <TooltipProvider :delay-duration="400">
        <Tooltip>
            <TooltipTrigger as-child>
                <Button
                    data-sidebar="trigger"
                    data-slot="sidebar-trigger"
                    variant="ghost"
                    size="icon"
                    :class="
                        cn(
                            'relative h-8 w-8 rounded-md border border-transparent',
                            'transition-all duration-200',
                            'hover:border-sidebar-border hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                            'focus-visible:ring-2 focus-visible:ring-sidebar-ring',
                            props.class,
                        )
                    "
                    @click="toggleSidebar"
                >
                    <Transition
                        enter-active-class="transition-all duration-150 ease-out"
                        leave-active-class="transition-all duration-150 ease-in"
                        enter-from-class="opacity-0 scale-75"
                        enter-to-class="opacity-100 scale-100"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-75"
                        mode="out-in"
                    >
                        <PanelLeftOpen
                            v-if="isMobile || state === 'collapsed'"
                            key="open"
                            class="size-4"
                        />
                        <PanelLeftClose
                            v-else
                            key="close"
                            class="size-4"
                        />
                    </Transition>
                    <span class="sr-only">Toggle sidebar</span>
                </Button>
            </TooltipTrigger>
            <TooltipContent side="right" :side-offset="8">
                <p class="text-xs font-medium">
                    {{ isOpen() ? 'Collapse sidebar' : 'Expand sidebar' }}
                </p>
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
