<script setup lang="ts">
import ProgressMeter from '@/components/progress/ProgressMeter.vue';
import type { AssignmentProgress } from '@/components/progress/types';

withDefaults(
    defineProps<{
        entry: AssignmentProgress;
        heading?: 'rotation' | 'student';
    }>(),
    { heading: 'rotation' },
);

const formatDate = (value: string) =>
    new Intl.DateTimeFormat('en', {
        day: '2-digit',
        month: 'short',
        timeZone: 'UTC',
    }).format(new Date(value));
</script>

<template>
    <article
        :data-test="`progress-${entry.assignment_id}`"
        class="space-y-4 rounded-3xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"
    >
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <h3
                    class="text-base font-semibold text-[#0b2942] dark:text-white"
                >
                    {{
                        heading === 'student'
                            ? entry.student.name
                            : entry.rotation.name
                    }}
                </h3>
                <p class="mt-0.5 text-xs text-slate-500">
                    {{ formatDate(entry.rotation.starts_on) }} –
                    {{ formatDate(entry.rotation.ends_on) }}
                </p>
            </div>
            <span
                v-if="entry.status !== 'active'"
                class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
            >
                Inactive
            </span>
        </div>

        <ProgressMeter
            label="All approved cases"
            :approved="entry.overall.approved"
            :target="entry.overall.target"
        />

        <div v-if="entry.categories.length" class="space-y-3">
            <ProgressMeter
                v-for="category in entry.categories"
                :key="category.category"
                :label="category.category"
                :approved="category.approved"
                :target="category.target"
            />
        </div>

        <p v-if="entry.uncategorised_approved" class="text-xs text-slate-500">
            {{ entry.uncategorised_approved }} approved case{{
                entry.uncategorised_approved === 1 ? '' : 's'
            }}
            without a category count toward the overall total only.
        </p>
    </article>
</template>
