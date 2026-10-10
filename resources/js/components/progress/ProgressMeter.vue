<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    label: string;
    approved: number;
    target: number | null;
}>();

const percent = computed(() =>
    props.target === null || props.target === 0
        ? null
        : Math.min(100, Math.round((props.approved / props.target) * 100)),
);
const met = computed(
    () => props.target !== null && props.approved >= props.target,
);
</script>

<template>
    <div class="space-y-1.5">
        <div class="flex items-baseline justify-between gap-3 text-sm">
            <span class="font-medium text-slate-700 dark:text-slate-200">
                {{ label }}
            </span>
            <span
                class="tabular-nums"
                :class="
                    met
                        ? 'font-semibold text-green-700 dark:text-green-400'
                        : 'text-slate-600 dark:text-slate-300'
                "
            >
                <template v-if="target === null">
                    {{ approved }} approved · no target set
                </template>
                <template v-else> {{ approved }} / {{ target }} </template>
            </span>
        </div>
        <div
            v-if="percent !== null"
            class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
            role="progressbar"
            :aria-label="label"
            :aria-valuenow="approved"
            aria-valuemin="0"
            :aria-valuemax="target ?? undefined"
        >
            <div
                class="h-full rounded-full"
                :class="met ? 'bg-green-600' : 'bg-[#0b2942] dark:bg-sky-400'"
                :style="{ width: `${percent}%` }"
            />
        </div>
    </div>
</template>
