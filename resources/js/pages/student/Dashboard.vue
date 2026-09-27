<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import RoleDashboard from '@/components/dashboard/RoleDashboard.vue';

defineProps<{
    metrics: Array<{ label: string; value: string; detail: string }>;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Student home', href: '/student' }] },
});
</script>

<template>
    <Head title="Student home" />
    <RoleDashboard
        eyebrow="Student workspace"
        title="Your clinical learning, clearly organised."
        description="Follow your rotation, build de-identified case records, and keep every faculty response connected to the work it reviews."
        :metrics="metrics"
        next-action="Create your first learning case"
        next-action-detail="Case creation and SOAP documentation will be enabled in the walking-skeleton gate after the sync architecture is proven."
        accent="student"
    />
</template>
