<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { AlertCircle, AlertTriangle, CheckCircle2, WifiOff } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';

type SectionId =
    | 'case_profile'
    | 'history_diagnosis'
    | 'vitals_investigations'
    | 'medication_chart'
    | 'soap'
    | 'clinical_activities';

const sectionLabels: Record<SectionId, string> = {
    case_profile: 'Case Profile',
    history_diagnosis: 'History & Diagnosis',
    vitals_investigations: 'Vitals & Investigations',
    medication_chart: 'Medication Chart',
    soap: 'SOAP',
    clinical_activities: 'Conditional Clinical Activities',
};

const props = defineProps<{
    clinicalCase: { id: string; case_number: number; status: string };
    sectionCompletion: Record<SectionId, boolean>;
    submissionErrors: { section: SectionId; message: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Student', href: '/student' },
            { title: 'Clinical Cases', href: '/student/cases' },
        ],
    },
});

const online = ref(navigator.onLine);
const handleOnline = () => {
    online.value = true;
};
const handleOffline = () => {
    online.value = false;
};
onMounted(() => {
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);
});
onUnmounted(() => {
    window.removeEventListener('online', handleOnline);
    window.removeEventListener('offline', handleOffline);
});

const attested = ref(false);
const isReady = computed(() => props.submissionErrors.length === 0);
const canSubmit = computed(
    () => isReady.value && attested.value && online.value,
);

const form = useForm({ deidentification_attested: false });
const submit = () => {
    form.deidentification_attested = true;
    form.post(`/student/cases/${props.clinicalCase.id}/submit`, {
        preserveScroll: true,
    });
};

const goToSection = (section: SectionId) =>
    router.get(
        `/student/cases/${props.clinicalCase.id}/edit?section=${section}`,
    );

const sectionIds: SectionId[] = [
    'case_profile',
    'history_diagnosis',
    'vitals_investigations',
    'medication_chart',
    'soap',
    'clinical_activities',
];
</script>

<template>
    <Head title="Submission review" />
    <main
        class="mx-auto w-full max-w-3xl space-y-6 px-4 pt-5 pb-28 sm:px-6 md:pt-8 md:pb-10"
    >
        <div>
            <p
                class="text-xs font-bold tracking-[0.16em] text-amber-700 uppercase"
            >
                Case #{{ clinicalCase.case_number }}
            </p>
            <h1
                class="font-display mt-1 text-2xl font-semibold text-[#0b2942] dark:text-white"
            >
                Submission review
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                Review every section before submitting. Nothing here has been
                sent to your faculty reviewer yet.
            </p>
        </div>

        <section
            class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-7 dark:border-slate-700 dark:bg-slate-900"
        >
            <h2
                class="font-display text-lg font-semibold text-[#0b2942] dark:text-white"
            >
                Section status
            </h2>
            <ul class="mt-4 space-y-2">
                <li
                    v-for="section in sectionIds"
                    :key="section"
                    class="flex items-center justify-between rounded-xl border border-slate-200 p-3 dark:border-slate-700"
                >
                    <span class="flex items-center gap-2 text-sm font-medium">
                        <CheckCircle2
                            v-if="sectionCompletion[section]"
                            class="size-4 text-green-600"
                        />
                        <AlertCircle v-else class="size-4 text-amber-600" />
                        {{ sectionLabels[section] }}
                    </span>
                    <Button
                        variant="link"
                        class="h-auto p-0"
                        @click="goToSection(section)"
                    >
                        {{ sectionCompletion[section] ? 'Review' : 'Complete' }}
                    </Button>
                </li>
            </ul>
        </section>

        <section
            v-if="submissionErrors.length"
            class="rounded-3xl border border-amber-300 bg-amber-50 p-5 sm:p-7 dark:border-amber-700 dark:bg-amber-950"
        >
            <h2
                class="font-display flex items-center gap-2 text-lg font-semibold text-amber-800 dark:text-amber-200"
            >
                <AlertTriangle class="size-5" /> Before you can submit
            </h2>
            <ul
                class="mt-3 list-inside list-disc space-y-1 text-sm text-amber-800 dark:text-amber-200"
            >
                <li v-for="(error, index) in submissionErrors" :key="index">
                    <button
                        type="button"
                        class="underline underline-offset-2"
                        @click="goToSection(error.section)"
                    >
                        {{ error.message }}
                    </button>
                </li>
            </ul>
        </section>

        <section
            v-else
            class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-7 dark:border-slate-700 dark:bg-slate-900"
        >
            <h2
                class="font-display text-lg font-semibold text-[#0b2942] dark:text-white"
            >
                De-identification attestation
            </h2>
            <label class="mt-4 flex items-start gap-3 text-sm">
                <Checkbox v-model="attested" />
                <span>
                    I confirm this case contains no patient name, initials,
                    UHID/MRN/IP/OP number, bed number, Aadhaar, phone, address,
                    email, full date of birth or photograph, and is ready for
                    faculty review.
                </span>
            </label>

            <p
                v-if="!online"
                class="mt-4 flex items-center gap-2 rounded-xl bg-slate-100 p-3 text-sm text-slate-600 dark:bg-slate-800 dark:text-slate-300"
            >
                <WifiOff class="size-4" /> You are offline. Reconnect to submit
                this case.
            </p>

            <Button
                class="mt-5 w-full bg-[#0b2942] text-white sm:w-auto"
                :disabled="!canSubmit || form.processing"
                @click="submit"
            >
                Submit for review
            </Button>
        </section>
    </main>
</template>
