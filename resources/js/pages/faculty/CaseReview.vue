<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CheckCircle, RotateCcw, Clock, User, MapPin } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import InputError from '@/components/InputError.vue';

type SoapNote = {
    id: string;
    subjective: string | null;
    objective: string | null;
    assessment: string | null;
    plan: string | null;
    revision_number: number;
};

type CaseVersion = {
    id: string;
    version_number: number;
    submitted_at: string;
    approved_at: string | null;
    snapshot: Record<string, unknown>;
    submitted_by: { name: string };
    approved_by: { name: string } | null;
};

type StatusTransition = {
    id: string;
    from_status: string;
    to_status: string;
    reason: string | null;
    created_at: string;
    actor: { name: string };
};

type Case = {
    id: string;
    case_number: number;
    status: string;
    encounter_date: string | null;
    case_category: string | null;
    age_value: number | null;
    age_unit: string | null;
    sex: string | null;
    submitted_at: string | null;
    approved_at: string | null;
    student: { name: string; email: string };
    clinical_site: { name: string } | null;
    department: { name: string } | null;
    ward: { name: string } | null;
    current_soap: SoapNote | null;
    versions: CaseVersion[];
    status_transitions: StatusTransition[];
};

const props = defineProps<{
    clinicalCase: Case;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Faculty', href: '/faculty' },
            { title: 'Reviews', href: '/faculty/reviews' },
        ],
    },
});

const approveForm = useForm({
    summary: '',
});
const returnForm = useForm({
    reason: '',
});

const approve = () =>
    approveForm.post(`/faculty/reviews/${props.clinicalCase.id}/approve`, {
        preserveScroll: true,
    });

const returnCase = () =>
    returnForm.post(`/faculty/reviews/${props.clinicalCase.id}/return`, {
        preserveScroll: true,
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

const canReview = (): boolean => {
    return (
        props.clinicalCase.status === 'submitted' ||
        props.clinicalCase.status === 'under_review'
    );
};
</script>

<template>
    <Head :title="`Review Case #${clinicalCase.case_number}`" />
    <main
        class="mx-auto w-full max-w-7xl space-y-6 px-4 pt-5 pb-28 sm:px-6 md:pt-8 md:pb-10"
    >
        <div class="flex items-center justify-between">
            <div>
                <p
                    class="text-xs font-bold tracking-[0.16em] text-amber-700 uppercase"
                >
                    Case #{{ clinicalCase.case_number }}
                </p>
                <h1
                    class="font-display mt-1 text-2xl font-semibold text-[#0b2942] dark:text-white"
                >
                    <span
                        :class="[
                            'mr-2 rounded-full px-2.5 py-0.5 text-xs font-semibold',
                            statusColor(clinicalCase.status),
                        ]"
                    >
                        {{ statusLabel(clinicalCase.status) }}
                    </span>
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Student: {{ clinicalCase.student.name }}
                </p>
            </div>
        </div>

        <section class="grid gap-5 lg:grid-cols-[1fr_1fr]">
            <div
                class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-7 dark:border-slate-700 dark:bg-slate-900"
            >
                <h2
                    class="font-display text-lg font-semibold text-[#0b2942] dark:text-white"
                >
                    Case details
                </h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div
                        v-if="clinicalCase.encounter_date"
                        class="flex justify-between"
                    >
                        <dt class="text-slate-500">Encounter date</dt>
                        <dd class="font-medium">
                            {{ formatDate(clinicalCase.encounter_date) }}
                        </dd>
                    </div>
                    <div
                        v-if="clinicalCase.case_category"
                        class="flex justify-between"
                    >
                        <dt class="text-slate-500">Category</dt>
                        <dd class="font-medium">
                            {{ clinicalCase.case_category }}
                        </dd>
                    </div>
                    <div
                        v-if="clinicalCase.age_value"
                        class="flex justify-between"
                    >
                        <dt class="text-slate-500">Age</dt>
                        <dd class="font-medium">
                            {{ clinicalCase.age_value }}
                            {{ clinicalCase.age_unit || '' }}
                        </dd>
                    </div>
                    <div v-if="clinicalCase.sex" class="flex justify-between">
                        <dt class="text-slate-500">Sex</dt>
                        <dd class="font-medium">{{ clinicalCase.sex }}</dd>
                    </div>
                    <div
                        v-if="clinicalCase.clinical_site"
                        class="flex justify-between"
                    >
                        <dt class="text-slate-500">Site</dt>
                        <dd class="font-medium">
                            {{ clinicalCase.clinical_site.name }}
                        </dd>
                    </div>
                    <div
                        v-if="clinicalCase.department"
                        class="flex justify-between"
                    >
                        <dt class="text-slate-500">Department</dt>
                        <dd class="font-medium">
                            {{ clinicalCase.department.name }}
                        </dd>
                    </div>
                    <div v-if="clinicalCase.ward" class="flex justify-between">
                        <dt class="text-slate-500">Ward</dt>
                        <dd class="font-medium">
                            {{ clinicalCase.ward.name }}
                        </dd>
                    </div>
                </dl>
            </div>

            <div
                class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-7 dark:border-slate-700 dark:bg-slate-900"
            >
                <h2
                    class="font-display text-lg font-semibold text-[#0b2942] dark:text-white"
                >
                    SOAP note
                </h2>
                <div v-if="clinicalCase.current_soap" class="mt-4 space-y-4">
                    <div v-if="clinicalCase.current_soap.subjective">
                        <h3 class="text-xs font-bold text-slate-500 uppercase">
                            Subjective
                        </h3>
                        <p class="mt-1 text-sm whitespace-pre-wrap">
                            {{ clinicalCase.current_soap.subjective }}
                        </p>
                    </div>
                    <div v-if="clinicalCase.current_soap.objective">
                        <h3 class="text-xs font-bold text-slate-500 uppercase">
                            Objective
                        </h3>
                        <p class="mt-1 text-sm whitespace-pre-wrap">
                            {{ clinicalCase.current_soap.objective }}
                        </p>
                    </div>
                    <div v-if="clinicalCase.current_soap.assessment">
                        <h3 class="text-xs font-bold text-slate-500 uppercase">
                            Assessment
                        </h3>
                        <p class="mt-1 text-sm whitespace-pre-wrap">
                            {{ clinicalCase.current_soap.assessment }}
                        </p>
                    </div>
                    <div v-if="clinicalCase.current_soap.plan">
                        <h3 class="text-xs font-bold text-slate-500 uppercase">
                            Plan
                        </h3>
                        <p class="mt-1 text-sm whitespace-pre-wrap">
                            {{ clinicalCase.current_soap.plan }}
                        </p>
                    </div>
                </div>
                <p v-else class="mt-4 text-sm text-slate-500">
                    No SOAP note submitted.
                </p>
            </div>
        </section>

        <section
            v-if="clinicalCase.versions.length"
            class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-7 dark:border-slate-700 dark:bg-slate-900"
        >
            <h2
                class="font-display text-lg font-semibold text-[#0b2942] dark:text-white"
            >
                Submission history
            </h2>
            <div class="mt-4 space-y-3">
                <div
                    v-for="version in clinicalCase.versions"
                    :key="version.id"
                    class="flex items-center justify-between rounded-xl border border-slate-200 p-3 dark:border-slate-700"
                >
                    <div>
                        <p class="text-sm font-semibold">
                            Version {{ version.version_number }}
                        </p>
                        <p class="text-xs text-slate-500">
                            Submitted by {{ version.submitted_by.name }} ·
                            {{ formatDate(version.submitted_at) }}
                        </p>
                    </div>
                    <div v-if="version.approved_by" class="text-right">
                        <p
                            class="flex items-center gap-1 text-xs text-green-600"
                        >
                            <CheckCircle class="size-3" /> Approved by
                            {{ version.approved_by.name }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section
            v-if="clinicalCase.status_transitions.length"
            class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-7 dark:border-slate-700 dark:bg-slate-900"
        >
            <h2
                class="font-display text-lg font-semibold text-[#0b2942] dark:text-white"
            >
                Status history
            </h2>
            <div class="mt-4 space-y-2">
                <div
                    v-for="transition in clinicalCase.status_transitions"
                    :key="transition.id"
                    class="flex items-center gap-3 text-sm"
                >
                    <Clock class="size-4 text-slate-400" />
                    <span class="text-slate-600">
                        {{ transition.actor.name }}
                        changed status from
                        <span class="font-semibold">{{
                            statusLabel(transition.from_status)
                        }}</span>
                        to
                        <span class="font-semibold">{{
                            statusLabel(transition.to_status)
                        }}</span>
                    </span>
                    <span class="text-xs text-slate-500">{{
                        formatDate(transition.created_at)
                    }}</span>
                </div>
            </div>
        </section>

        <section v-if="canReview()" class="grid gap-5 lg:grid-cols-2">
            <form
                class="rounded-3xl border border-green-200 bg-green-50/50 p-5 sm:p-7 dark:border-green-800 dark:bg-green-950/20"
                @submit.prevent="approve"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="grid size-10 place-items-center rounded-xl bg-green-100 text-green-700"
                    >
                        <CheckCircle class="size-5" />
                    </span>
                    <div>
                        <h2
                            class="font-display text-lg font-semibold text-[#0b2942] dark:text-white"
                        >
                            Approve
                        </h2>
                        <p class="text-xs text-slate-500">
                            Mark this case as approved.
                        </p>
                    </div>
                </div>
                <div class="mt-4">
                    <label
                        for="approve-summary"
                        class="text-sm font-semibold text-[#0b2942] dark:text-white"
                    >
                        Summary / Attestation (optional)
                    </label>
                    <textarea
                        id="approve-summary"
                        v-model="approveForm.summary"
                        class="border-input bg-background mt-2 min-h-[80px] w-full rounded-md border px-3 py-2 text-sm"
                        placeholder="Optional comment..."
                    />
                    <InputError :message="approveForm.errors.summary" />
                </div>
                <Button
                    type="submit"
                    class="mt-4 w-full bg-green-700 text-white sm:w-auto"
                    :disabled="approveForm.processing"
                >
                    <CheckCircle class="mr-1 size-4" /> Approve
                </Button>
            </form>

            <form
                class="rounded-3xl border border-orange-200 bg-orange-50/50 p-5 sm:p-7 dark:border-orange-800 dark:bg-orange-950/20"
                @submit.prevent="returnCase"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="grid size-10 place-items-center rounded-xl bg-orange-100 text-orange-700"
                    >
                        <RotateCcw class="size-5" />
                    </span>
                    <div>
                        <h2
                            class="font-display text-lg font-semibold text-[#0b2942] dark:text-white"
                        >
                            Return
                        </h2>
                        <p class="text-xs text-slate-500">
                            Return for student correction.
                        </p>
                    </div>
                </div>
                <div class="mt-4">
                    <label
                        for="return-reason"
                        class="text-sm font-semibold text-[#0b2942] dark:text-white"
                    >
                        Reason *
                    </label>
                    <textarea
                        id="return-reason"
                        v-model="returnForm.reason"
                        class="border-input bg-background mt-2 min-h-[80px] w-full rounded-md border px-3 py-2 text-sm"
                        placeholder="Describe what needs correction..."
                        required
                    />
                    <InputError :message="returnForm.errors.reason" />
                </div>
                <Button
                    type="submit"
                    class="mt-4 w-full bg-orange-600 text-white sm:w-auto"
                    :disabled="returnForm.processing || !returnForm.reason"
                >
                    <RotateCcw class="mr-1 size-4" /> Return
                </Button>
            </form>
        </section>
    </main>
</template>
