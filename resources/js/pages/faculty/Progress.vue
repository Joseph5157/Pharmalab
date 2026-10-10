<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AssignmentProgressCard from '@/components/progress/AssignmentProgressCard.vue';
import type { AssignmentProgress } from '@/components/progress/types';

const props = defineProps<{
    assignments: AssignmentProgress[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Faculty', href: '/faculty' },
            { title: 'Student progress', href: '/faculty/progress' },
        ],
    },
});

const byRotation = computed(() => {
    const groups = new Map<
        string,
        { name: string; entries: AssignmentProgress[] }
    >();
    for (const entry of props.assignments) {
        const group = groups.get(entry.rotation.id) ?? {
            name: entry.rotation.name,
            entries: [],
        };
        group.entries.push(entry);
        groups.set(entry.rotation.id, group);
    }

    return [...groups.entries()].map(([id, group]) => ({ id, ...group }));
});
</script>

<template>
    <Head title="Student progress" />
    <main
        class="mx-auto w-full max-w-6xl space-y-8 px-4 pt-5 pb-28 sm:px-6 md:pt-8 md:pb-10"
    >
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p
                    class="text-xs font-bold tracking-[0.16em] text-amber-700 uppercase"
                >
                    Student progress
                </p>
                <h1
                    class="font-display mt-1 text-2xl font-semibold text-[#0b2942] dark:text-white"
                >
                    Approved cases against your targets
                </h1>
            </div>
            <Link
                href="/faculty/rotations"
                class="text-sm font-semibold text-[#0b2942] underline underline-offset-4 dark:text-sky-300"
            >
                Set rotation targets
            </Link>
        </div>

        <section v-for="group in byRotation" :key="group.id" class="space-y-3">
            <h2 class="text-lg font-semibold text-[#0b2942] dark:text-white">
                {{ group.name }}
            </h2>
            <div class="grid gap-4 md:grid-cols-2">
                <AssignmentProgressCard
                    v-for="entry in group.entries"
                    :key="entry.assignment_id"
                    :entry="entry"
                    heading="student"
                />
            </div>
        </section>

        <p
            v-if="!assignments.length"
            class="rounded-3xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500"
        >
            No students are assigned to you yet.
        </p>
    </main>
</template>
