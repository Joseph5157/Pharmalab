<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, FileText, Clock, CheckCircle, RotateCcw, Eye } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import InputError from '@/components/InputError.vue';

type Case = {
    id: string;
    case_number: number;
    status: string;
    encounter_date: string | null;
    case_category: string | null;
    created_at: string;
    clinical_site: { name: string } | null;
};

const props = defineProps<{
    cases: Case[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Student', href: '/student' },
            { title: 'Clinical Cases', href: '/student/cases' },
        ],
    },
});

const createForm = useForm({});
const createCase = () =>
    createForm.post('/student/cases', {
        preserveScroll: true,
    });

const statusColor = (status: string) => {
    switch (status) {
        case 'draft':
            return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
        case 'submitted':
            return 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300';
        case 'under_review':
            return 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300';
        case 'returned':
            return 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300';
        case 'approved':
            return 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300';
        default:
            return 'bg-slate-100 text-slate-700';
    }
};

const statusLabel = (status: string) => {
    switch (status) {
        case 'draft':
            return 'Draft';
        case 'submitted':
            return 'Submitted';
        case 'under_review':
            return 'Under review';
        case 'returned':
            return 'Returned';
        case 'approved':
            return 'Approved';
        default:
            return status;
    }
};

const formatDate = (value: string) =>
    new Intl.DateTimeFormat('en', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(value));
</script>

<template>
    <Head title="Clinical Cases" />
    <main
        class="mx-auto w-full max-w-7xl space-y-6 px-4 pt-5 pb-28 sm:px-6 md:pt-8 md:pb-10"
    >
        <div class="flex items-center justify-between">
            <div>
                <p
                    class="text-xs font-bold tracking-[0.16em] text-amber-700 uppercase"
                >
                    Clinical cases
                </p>
                <h1
                    class="font-display mt-1 text-2xl font-semibold text-[#0b2942] dark:text-white"
                >
                    Your cases
                </h1>
            </div>
            <Button
                class="bg-[#0b2942] text-white"
                :disabled="createForm.processing"
                @click="createCase"
            >
                <Plus class="size-4" /> New case
            </Button>
        </div>

        <section v-if="cases.length" class="space-y-3">
            <article
                v-for="caseItem in cases"
                :key="caseItem.id"
                class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 dark:border-slate-700 dark:bg-slate-900"
            >
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-bold text-[#0b2942] dark:text-white">
                            #{{ caseItem.case_number }}
                        </span>
                        <span
                            :class="[
                                'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                statusColor(caseItem.status),
                            ]"
                        >
                            {{ statusLabel(caseItem.status) }}
                        </span>
                    </div>
                    <p
                        v-if="caseItem.case_category"
                        class="mt-1 text-sm text-slate-600 dark:text-slate-400"
                    >
                        {{ caseItem.case_category }}
                    </p>
                    <p
                        class="mt-1 text-xs text-slate-500"
                    >
                        {{ formatDate(caseItem.created_at) }}
                        <template v-if="caseItem.clinical_site">
                            · {{ caseItem.clinical_site.name }}
                        </template>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        v-if="caseItem.status === 'draft' || caseItem.status === 'returned'"
                        size="sm"
                        variant="outline"
                        @click="router.get(`/student/cases/${caseItem.id}/soap`)"
                    >
                        <FileText class="size-4" />
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        @click="router.get(`/student/cases/${caseItem.id}`)"
                    >
                        <Eye class="size-4" />
                    </Button>
                </div>
            </article>
        </section>

        <p
            v-else
            class="rounded-3xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500"
        >
            No cases yet. Create your first clinical case to begin documenting.
        </p>
    </main>
</template>
