<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
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
    <div class="px-4 pb-24 sm:px-6 lg:px-8">
        <Link
            href="/student/sync-spike"
            class="mx-auto flex max-w-6xl items-center justify-between rounded-2xl border border-[#c9a43b]/30 bg-[#fffaf0] px-5 py-4 text-sm font-semibold text-[#0b2942] shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:bg-slate-900 dark:text-white"
        >
            <span>Open the offline note experiment</span>
            <span aria-hidden="true">→</span>
        </Link>
    </div>
</template>
