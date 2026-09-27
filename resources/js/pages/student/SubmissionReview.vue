<script setup lang="ts">
// Placeholder for Task 3 (backend route/controller only). Task 4 replaces
// this with the real submission-review UI (checklist, error list,
// de-identification attestation, online gating).
defineProps<{
    clinicalCase: { id: string; case_number: number; status: string };
    sectionCompletion: Record<string, boolean>;
    submissionErrors: { section: string; message: string }[];
}>();
</script>

<template>
    <div />
</template>
