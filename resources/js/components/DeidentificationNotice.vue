<script setup lang="ts">
import { AlertTriangle } from '@lucide/vue';
import { computed } from 'vue';
import { detectPotentialIdentifiers } from '@/lib/deidentification';

const props = defineProps<{ text: string | null | undefined }>();
const warnings = computed(() => detectPotentialIdentifiers(props.text));
</script>

<template>
    <p
        v-if="warnings.length"
        role="alert"
        data-test="deidentification-warning"
        class="mt-1 flex items-start gap-1.5 text-xs text-amber-700 dark:text-amber-400"
    >
        <AlertTriangle class="mt-0.5 size-3.5 shrink-0" />
        <span>
            This text may contain a {{ warnings.join(' or ') }}. De-identified
            case notes must not include patient names, record numbers, phone
            numbers, or contact details.
        </span>
    </p>
</template>
