<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import RoleDashboard from '@/components/dashboard/RoleDashboard.vue';

defineProps<{
    metrics: Array<{ label: string; value: string; detail: string }>;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Faculty dashboard', href: '/faculty' }] },
});
</script>

<template>
    <Head title="Faculty dashboard" />
    <RoleDashboard
        eyebrow="Faculty review desk"
        title="Review student reasoning with context."
        description="A focused workspace for assigned cases, section-level feedback, rubric decisions, and transparent revision history."
        :metrics="metrics"
        next-action="Prepare the first review queue"
        next-action-detail="The walking skeleton will connect submitted SOAP notes to an immutable faculty review and approval decision."
        accent="faculty"
    />
</template>
