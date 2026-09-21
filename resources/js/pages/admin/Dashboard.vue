<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import RoleDashboard from '@/components/dashboard/RoleDashboard.vue';

defineProps<{
    metrics: Array<{ label: string; value: string; detail: string }>;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Administration', href: '/admin' }] },
});
</script>

<template>
    <Head title="Administration" />
    <RoleDashboard
        eyebrow="Institution operations"
        title="Build the structure learning depends on."
        description="Manage programmes, clinical sites, people, and rotations without crossing the boundary into student academic decisions."
        :metrics="metrics"
        next-action="Complete pilot setup"
        next-action-detail="Programme, hospital, ward, rotation, and assignment tools will form the first half of the walking skeleton."
        accent="administrator"
    />
</template>
