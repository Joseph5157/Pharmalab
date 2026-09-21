<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 48 48"
        :class="className"
        v-bind="$attrs"
    >
        <rect x="5" y="5" width="38" height="38" rx="12" fill="currentColor" />
        <path
            d="M24 13v22M13 24h22"
            stroke="white"
            stroke-width="4"
            stroke-linecap="round"
        />
        <path
            d="M15 36c4-2.2 7-2.2 9 0 2-2.2 5-2.2 9 0"
            fill="none"
            stroke="#E8BD55"
            stroke-width="2.5"
            stroke-linecap="round"
        />
    </svg>
</template>
