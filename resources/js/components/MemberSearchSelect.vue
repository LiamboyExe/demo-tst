<script setup lang="ts">
import { Check, ChevronsUpDown } from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import type { Member } from '@/types';

const props = withDefaults(
    defineProps<{
        modelValue: number;
        members: Member[];
        id?: string;
        disabled?: boolean;
    }>(),
    {
        id: 'member-search',
        disabled: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: number];
}>();

const root = ref<HTMLElement | null>(null);
const isOpen = ref(false);
const query = ref('');
const activeIndex = ref(-1);

const selectedMember = computed(
    () =>
        props.members.find((member) => member.id === props.modelValue) ?? null,
);

const filteredMembers = computed<Member[]>(() => {
    const search = query.value.trim().toLowerCase();

    if (!search) {
        return props.members;
    }

    return props.members.filter((member) =>
        member.full_name.toLowerCase().includes(search),
    );
});

const listboxId = computed(() => `${props.id}-listbox`);

const activeOptionId = computed(() => {
    const member = filteredMembers.value[activeIndex.value];

    return member ? `${props.id}-option-${member.id}` : undefined;
});

watch(
    () => props.modelValue,
    () => {
        if (!isOpen.value) {
            query.value = selectedMember.value?.full_name ?? '';
        }
    },
    { immediate: true },
);

function focusInput(): void {
    root.value?.querySelector('input')?.focus();
}

function openPanel(): void {
    if (props.disabled || isOpen.value) {
        return;
    }

    isOpen.value = true;
    query.value = '';
    activeIndex.value = filteredMembers.value.findIndex(
        (member) => member.id === props.modelValue,
    );
    scrollActiveIntoView();
}

function closePanel(): void {
    isOpen.value = false;
    query.value = selectedMember.value?.full_name ?? '';
    activeIndex.value = -1;
}

function togglePanel(): void {
    if (isOpen.value) {
        closePanel();
    } else {
        openPanel();
        focusInput();
    }
}

function selectMember(member: Member): void {
    emit('update:modelValue', member.id);
    closePanel();

    void nextTick(() => {
        focusInput();
        selectInputText();
    });
}

function selectAllText(event: FocusEvent): void {
    (event.target as HTMLInputElement | null)?.select();
}

function selectInputText(): void {
    root.value?.querySelector('input')?.select();
}

function handleQueryChange(value: string | number): void {
    query.value = String(value);

    if (!isOpen.value) {
        isOpen.value = true;
    }

    activeIndex.value = filteredMembers.value.length ? 0 : -1;
    scrollActiveIntoView();
}

function onKeydown(event: KeyboardEvent): void {
    if (props.disabled) {
        return;
    }

    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();

        if (!isOpen.value) {
            openPanel();

            return;
        }

        if (!filteredMembers.value.length) {
            return;
        }

        const step = event.key === 'ArrowDown' ? 1 : -1;
        const nextIndex = activeIndex.value + step;

        if (nextIndex < 0) {
            activeIndex.value = filteredMembers.value.length - 1;
        } else if (nextIndex >= filteredMembers.value.length) {
            activeIndex.value = 0;
        } else {
            activeIndex.value = nextIndex;
        }

        scrollActiveIntoView();

        return;
    }

    if (event.key === 'Enter' && isOpen.value) {
        event.preventDefault();

        const member = filteredMembers.value[activeIndex.value];

        if (member) {
            selectMember(member);
        }

        return;
    }

    if (event.key === 'Escape' && isOpen.value) {
        event.preventDefault();
        event.stopPropagation();
        closePanel();
    }
}

async function scrollActiveIntoView(): Promise<void> {
    await nextTick();

    if (activeOptionId.value) {
        document
            .getElementById(activeOptionId.value)
            ?.scrollIntoView({ block: 'nearest' });
    }
}

function onDocumentPointerDown(event: MouseEvent): void {
    if (!root.value?.contains(event.target as Node)) {
        closePanel();
    }
}

onMounted(() => {
    document.addEventListener('mousedown', onDocumentPointerDown);
});

onUnmounted(() => {
    document.removeEventListener('mousedown', onDocumentPointerDown);
});
</script>

<template>
    <div ref="root" class="relative">
        <Input
            :id="id"
            :model-value="query"
            :disabled="disabled"
            type="text"
            role="combobox"
            autocomplete="off"
            aria-autocomplete="list"
            :aria-expanded="isOpen"
            :aria-controls="listboxId"
            :aria-activedescendant="activeOptionId"
            placeholder="Search member by name"
            class="pr-9"
            @update:model-value="handleQueryChange"
            @focus="selectAllText"
            @click="openPanel"
            @blur="closePanel"
            @keydown="onKeydown"
        />

        <button
            type="button"
            tabindex="-1"
            :disabled="disabled"
            :aria-label="
                isOpen ? 'Close member options' : 'Open member options'
            "
            class="absolute top-1/2 right-2 -translate-y-1/2 rounded p-1 text-muted-foreground transition-colors hover:text-foreground disabled:opacity-50"
            @mousedown.prevent
            @click="togglePanel"
        >
            <ChevronsUpDown class="size-4" />
        </button>

        <div
            v-if="isOpen"
            class="absolute z-50 mt-1 w-full overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md"
        >
            <ul
                v-if="filteredMembers.length"
                :id="listboxId"
                role="listbox"
                class="max-h-56 overflow-y-auto p-1"
            >
                <li
                    v-for="(member, index) in filteredMembers"
                    :id="`${id}-option-${member.id}`"
                    :key="member.id"
                    role="option"
                    :aria-selected="member.id === modelValue"
                    class="flex cursor-pointer items-center justify-between gap-2 rounded-sm px-2 py-2 text-sm outline-hidden select-none"
                    :class="
                        index === activeIndex
                            ? 'bg-accent text-accent-foreground'
                            : ''
                    "
                    @mousedown.prevent
                    @click="selectMember(member)"
                    @mouseenter="activeIndex = index"
                >
                    <span class="min-w-0">
                        <span class="block truncate">
                            {{ member.full_name }}
                        </span>
                        <span
                            class="block truncate text-xs text-muted-foreground"
                        >
                            {{ member.branch?.name ?? 'No territory' }}
                        </span>
                    </span>
                    <Check
                        v-if="member.id === modelValue"
                        class="size-4 shrink-0"
                    />
                </li>
            </ul>
            <p
                v-else
                class="px-3 py-6 text-center text-sm text-muted-foreground"
            >
                No members match "{{ query }}".
            </p>
        </div>
    </div>
</template>
