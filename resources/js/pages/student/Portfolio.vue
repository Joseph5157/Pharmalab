<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Award, MapPin } from '@lucide/vue';

type ApprovedVersion = {
    id: string;
    version_number: number;
    approved_at: string | null;
    approved_by: { name: string } | null;
};

type Case = {
    id: string;
    case_number: number;
    case_category: string | null;
    approved_at: string | null;
    clinical_site: { name: string } | null;
    department: { name: string } | null;
    ward: { name: string } | null;
    versions: ApprovedVersion[];
};

const props = defineProps<{
    cases: Case[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Student', href: '/student' },
            { title: 'Portfolio', href: '/student/portfolio' },
        ],
    },
});

const formatDate = (value: string) =>
    new Intl.DateTimeFormat('en', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(value));
</script>

<template>
    <Head title="Portfolio" />
    <main
        class="mx-auto w-full max-w-7xl space-y-6 px-4 pt-5 pb-28 sm:px-6 md:pt-8 md:pb-10"
    >
        <div>
            <p
                class="text-xs font-bold tracking-[0.16em] text-amber-700 uppercase"
            >
                Portfolio
            </p>
            <h1
                class="font-display mt-1 text-2xl font-semibold text-[#0b2942] dark:text-white"
            >
                Approved cases
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ cases.length }} approved case{{ cases.length !== 1 ? 's' : '' }}
            </p>
        </div>

        <section v-if="cases.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <article
                v-for="caseItem in cases"
                :key="caseItem.id"
                class="rounded-3xl border border-green-200 bg-green-50/50 p-5 dark:border-green-800 dark:bg-green-950/20"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <span
                            class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-bold text-green-700 dark:bg-green-900 dark:text-green-300"
                        >
                            #{{ caseItem.case_number }}
                        </span>
                        <h3
                            v-if="caseItem.case_category"
                            class="mt-2 font-display text-lg font-semibold text-[#0b2942] dark:text-white"
                        >
                            {{ caseItem.case_category }}
                        </h3>
                    </div>
                    <Award class="size-5 text-green-600" />
                </div>

                <div class="mt-3 space-y-1 text-sm text-slate-600 dark:text-slate-400">
                    <p v-if="caseItem.clinical_site" class="flex items-center gap-1.5">
                        <MapPin class="size-3.5" />
                        {{ caseItem.clinical_site.name }}
                        <template v-if="caseItem.ward"> · {{ caseItem.ward.name }}</template>
                    </p>
                    <p v-if="caseItem.approved_at" class="text-xs text-green-700 dark:text-green-400">
                        Approved {{ formatDate(caseItem.approved_at) }}
                    </p>
                </div>

                <div
                    v-if="caseItem.versions.length && caseItem.versions[0].approved_by"
                    class="mt-3 border-t border-green-200 pt-3 dark:border-green-800"
                >
                    <p class="text-xs text-slate-500">
                        Reviewed by {{ caseItem.versions[0].approved_by.name }}
                    </p>
                </div>
            </article>
        </section>

        <p
            v-else
            class="rounded-3xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500"
        >
            No approved cases yet. Complete and submit cases to build your portfolio.
        </p>
    </main>
</template>
