<script setup lang="ts">
import { computed } from 'vue';

type TrendPoint = {
    label: string;
    total: number;
};

const props = defineProps<{
    points: TrendPoint[];
}>();

const width = 760;
const height = 260;
const padding = { top: 18, right: 18, bottom: 46, left: 42 };
const plotWidth = width - padding.left - padding.right;
const plotHeight = height - padding.top - padding.bottom;

const maxValue = computed(() =>
    Math.max(1, ...props.points.map((point) => point.total)),
);

const coordinates = computed(() =>
    props.points.map((point, index) => {
        const x =
            props.points.length === 1
                ? padding.left + plotWidth / 2
                : padding.left + (index / (props.points.length - 1)) * plotWidth;
        const y =
            padding.top + (1 - point.total / maxValue.value) * plotHeight;

        return { ...point, x, y };
    }),
);

const linePath = computed(() =>
    coordinates.value
        .map(
            (point, index) =>
                `${index === 0 ? 'M' : 'L'} ${point.x.toFixed(2)} ${point.y.toFixed(2)}`,
        )
        .join(' '),
);

const areaPath = computed(() => {
    if (!coordinates.value.length) {
        return '';
    }

    const first = coordinates.value[0];
    const last = coordinates.value[coordinates.value.length - 1];

    return `${linePath.value} L ${last.x.toFixed(2)} ${(padding.top + plotHeight).toFixed(2)} L ${first.x.toFixed(2)} ${(padding.top + plotHeight).toFixed(2)} Z`;
});

const yTicks = computed(() =>
    Array.from({ length: 5 }, (_, index) => {
        const value = Math.round((maxValue.value / 4) * (4 - index));

        return {
            value,
            y: padding.top + (index / 4) * plotHeight,
        };
    }),
);

const labelStep = computed(() =>
    Math.max(1, Math.ceil(props.points.length / 8)),
);

function formatNumber(value: number): string {
    return new Intl.NumberFormat('en-PH').format(value);
}
</script>

<template>
    <div v-if="points.length" class="w-full overflow-hidden">
        <svg
            :viewBox="`0 0 ${width} ${height}`"
            role="img"
            aria-label="Registration trend line chart"
            class="h-auto w-full"
        >
            <defs>
                <linearGradient id="registration-trend-fill" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="var(--color-chart-2)" stop-opacity="0.28" />
                    <stop offset="100%" stop-color="var(--color-chart-2)" stop-opacity="0.02" />
                </linearGradient>
            </defs>

            <g>
                <line
                    v-for="tick in yTicks"
                    :key="tick.y"
                    :x1="padding.left"
                    :x2="width - padding.right"
                    :y1="tick.y"
                    :y2="tick.y"
                    stroke="var(--color-border)"
                    stroke-dasharray="3 4"
                />
                <text
                    v-for="tick in yTicks"
                    :key="`label-${tick.y}`"
                    :x="padding.left - 8"
                    :y="tick.y + 3"
                    text-anchor="end"
                    fill="var(--color-muted-foreground)"
                    font-size="10"
                >
                    {{ formatNumber(tick.value) }}
                </text>
            </g>

            <path :d="areaPath" fill="url(#registration-trend-fill)" />
            <path
                :d="linePath"
                fill="none"
                stroke="var(--color-chart-2)"
                stroke-width="2.5"
                stroke-linejoin="round"
                stroke-linecap="round"
            />

            <g v-for="(point, index) in coordinates" :key="point.label">
                <circle
                    v-if="index % labelStep === 0 || index === coordinates.length - 1"
                    :cx="point.x"
                    :cy="point.y"
                    r="3.5"
                    fill="var(--color-background)"
                    stroke="var(--color-chart-2)"
                    stroke-width="2"
                >
                    <title>{{ point.label }}: {{ formatNumber(point.total) }}</title>
                </circle>
            </g>

            <g>
                <text
                    v-for="(point, index) in coordinates"
                    v-show="index % labelStep === 0 || index === coordinates.length - 1"
                    :key="`x-${point.label}`"
                    :x="point.x"
                    :y="height - 18"
                    text-anchor="middle"
                    fill="var(--color-muted-foreground)"
                    font-size="9"
                >
                    {{ point.label }}
                </text>
            </g>
        </svg>
    </div>
    <div
        v-else
        class="flex h-52 items-center justify-center rounded-lg border border-dashed text-sm text-muted-foreground"
    >
        No registrations match the selected filters.
    </div>
</template>
