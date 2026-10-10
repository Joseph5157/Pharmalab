<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AssignmentProgressCard from '@/components/progress/AssignmentProgressCard.vue';
import type { AssignmentProgress } from '@/components/progress/types';

defineProps<{
    assignments: AssignmentProgress[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Student', href: '/student' },
            { title: 'Progress', href: '/student/progress' },
        ],
    },
});
</script>

<template>
    <Head title="Progress" />
    <main
        class="mx-auto w-full max-w-5xl space-y-6 px-4 pt-5 pb-28 sm:px-6 md:pt-8 md:pb-10"
    >
        <div>
            <p
                class="text-xs font-bold tracking-[0.16em] text-amber-700 uppercase"
            >
                Progress
            </p>
            <h1
                class="font-display mt-1 text-2xl font-semibold text-[#0b2942] dark:text-white"
            >
                Your rotation targets
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                Only cases your preceptor has approved count toward a target.
            </p>
        </div>

        <section v-if="assignments.length" class="grid gap-4 md:grid-cols-2">
            <AssignmentProgressCard
                v-for="entry in assignments"
                :key="entry.assignment_id"
                :entry="entry"
            />
        </section>
        <p
            v-else
            class="rounded-3xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500"
        >
            You have no rotation assignments yet.
        </p>
    </main>
</template>
