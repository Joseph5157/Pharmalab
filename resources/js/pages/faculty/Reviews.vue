<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ClipboardCheck,
    Clock,
    CheckCircle,
    RotateCcw,
    Eye,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';

type Student = {
    id: number;
    name: string;
    email: string;
};

type ClinicalSite = {
    id: string;
    name: string;
};

type Case = {
    id: string;
    case_number: number;
    status: string;
    encounter_date: string | null;
    case_category: string | null;
    submitted_at: string | null;
    approved_at: string | null;
    student: Student;
    clinical_site: ClinicalSite | null;
};

const props = defineProps<{
    cases: Case[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Faculty', href: '/faculty' },
            { title: 'Reviews', href: '/faculty/reviews' },
        ],
    },
});

const statusColor = (status: string) => {
    switch (status) {
        case 'submitted':
            return 'bg-blue-100 text-blue-700';
        case 'under_review':
            return 'bg-amber-100 text-amber-700';
        case 'returned':
            return 'bg-orange-100 text-orange-700';
        case 'approved':
            return 'bg-green-100 text-green-700';
        default:
            return 'bg-slate-100 text-slate-700';
    }
};

const statusLabel = (status: string) => {
    switch (status) {
        case 'submitted':
            return 'Awaiting review';
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
    <Head title="Review Queue" />
    <main
        class="mx-auto w-full max-w-7xl space-y-6 px-4 pt-5 pb-28 sm:px-6 md:pt-8 md:pb-10"
    >
        <div>
            <p
                class="text-xs font-bold tracking-[0.16em] text-amber-700 uppercase"
            >
                Faculty review
            </p>
            <h1
                class="font-display mt-1 text-2xl font-semibold text-[#0b2942] dark:text-white"
            >
                Review queue
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ cases.length }} case{{ cases.length !== 1 ? 's' : '' }}
                awaiting action
            </p>
        </div>

        <section v-if="cases.length" class="space-y-3">
            <article
                v-for="caseItem in cases"
                :key="caseItem.id"
                class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 dark:border-slate-700 dark:bg-slate-900"
            >
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="text-sm font-bold text-[#0b2942] dark:text-white"
                        >
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
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                        {{ caseItem.student.name }}
                        <template v-if="caseItem.case_category">
                            · {{ caseItem.case_category }}
                        </template>
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        <template v-if="caseItem.submitted_at">
                            Submitted {{ formatDate(caseItem.submitted_at) }}
                        </template>
                        <template v-if="caseItem.clinical_site">
                            · {{ caseItem.clinical_site.name }}
                        </template>
                    </p>
                </div>
                <Button
                    size="sm"
                    variant="outline"
                    @click="router.get(`/faculty/reviews/${caseItem.id}`)"
                >
                    <Eye class="size-4" />
                </Button>
            </article>
        </section>

        <p
            v-else
            class="rounded-3xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500"
        >
            No cases in your review queue.
        </p>
    </main>
</template>
