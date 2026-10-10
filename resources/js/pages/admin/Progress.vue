<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminWorkspaceHeader from '@/components/admin/AdminWorkspaceHeader.vue';
import InputError from '@/components/InputError.vue';
import AssignmentProgressCard from '@/components/progress/AssignmentProgressCard.vue';
import type { AssignmentProgress } from '@/components/progress/types';

type RotationOption = { id: string; name: string };
type ApprovedCase = {
    id: string;
    case_number: number;
    student_name: string | null;
    case_category: string | null;
    approved_at: string | null;
};

const props = defineProps<{
    rotations: RotationOption[];
    selectedRotationId: string | null;
    assignments: AssignmentProgress[];
    approvedCases: ApprovedCase[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administration', href: '/admin' },
            { title: 'Progress', href: '/admin/progress' },
        ],
    },
});

const page = usePage();
const filterError = computed(
    () => (page.props.errors as Record<string, string>).rotation,
);
const selected = ref(props.selectedRotationId ?? '');

const applyFilter = () => {
    router.get(
        '/admin/progress',
        selected.value === '' ? {} : { rotation: selected.value },
        { preserveScroll: true },
    );
};

const selectedName = computed(
    () =>
        props.rotations.find(
            (rotation) => rotation.id === props.selectedRotationId,
        )?.name,
);

const formatDate = (value: string) =>
    new Intl.DateTimeFormat('en', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(value));
</script>

<template>
    <Head title="Progress" />
    <main
        class="mx-auto w-full max-w-6xl space-y-6 px-4 pt-5 pb-28 sm:px-6 md:pt-8 md:pb-10"
    >
        <AdminWorkspaceHeader
            eyebrow="Reporting"
            title="Rotation progress"
            description="Approved cases per student against each rotation's targets."
        />

        <div class="flex flex-wrap items-end gap-3">
            <label class="text-sm">
                <span class="text-slate-500">Rotation</span>
                <select
                    v-model="selected"
                    data-test="rotation-filter"
                    class="mt-1 block w-72 max-w-full rounded-xl border p-2"
                    @change="applyFilter"
                >
                    <option value="">All rotations</option>
                    <option
                        v-for="rotation in rotations"
                        :key="rotation.id"
                        :value="rotation.id"
                    >
                        {{ rotation.name }}
                    </option>
                </select>
            </label>
            <InputError :message="filterError" />
        </div>

        <section v-if="assignments.length" class="grid gap-4 md:grid-cols-2">
            <AssignmentProgressCard
                v-for="entry in assignments"
                :key="entry.assignment_id"
                :entry="entry"
                heading="student"
            />
        </section>
        <p
            v-else
            class="rounded-3xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500"
        >
            No rotation assignments to report on.
        </p>

        <section v-if="selectedRotationId" class="space-y-3">
            <h2 class="text-lg font-semibold text-[#0b2942] dark:text-white">
                Approved cases · {{ selectedName }}
            </h2>
            <div
                v-if="approvedCases.length"
                class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-slate-700"
            >
                <table class="w-full min-w-[32rem] text-left text-sm">
                    <thead
                        class="bg-slate-50 text-xs text-slate-500 uppercase dark:bg-slate-800"
                    >
                        <tr>
                            <th class="px-4 py-2">Case</th>
                            <th class="px-4 py-2">Student</th>
                            <th class="px-4 py-2">Category</th>
                            <th class="px-4 py-2">Approved</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-100 dark:divide-slate-800"
                    >
                        <tr v-for="item in approvedCases" :key="item.id">
                            <td class="px-4 py-2 font-semibold">
                                #{{ item.case_number }}
                            </td>
                            <td class="px-4 py-2">{{ item.student_name }}</td>
                            <td class="px-4 py-2">
                                {{ item.case_category ?? '—' }}
                            </td>
                            <td class="px-4 py-2">
                                {{
                                    item.approved_at
                                        ? formatDate(item.approved_at)
                                        : '—'
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="text-sm text-slate-500">
                No approved cases on this rotation yet.
            </p>
        </section>
    </main>
</template>
