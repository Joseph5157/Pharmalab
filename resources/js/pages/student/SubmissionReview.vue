<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { AlertCircle, AlertTriangle, CheckCircle2, WifiOff } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { listCaseOutbox } from '@/lib/outboxStore';

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

type Row = Record<string, unknown> & { id: string };

const props = defineProps<{
    clinicalCase: { id: string; case_number: number; status: string };
    sectionCompletion: Record<SectionId, boolean>;
    submissionErrors: { section: SectionId; message: string }[];
    context: Record<string, unknown>;
    clinicalProfile: Record<string, unknown> | null;
    vitals: Row[];
    investigations: Row[];
    medications: Row[];
    soap: Record<string, unknown> | null;
    adr: Record<string, unknown> | null;
    counselling: Record<string, unknown> | null;
    interventions: Row[];
    monitoringFollowUps: Row[];
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
const casePendingCount = ref(0);
const legacyPendingCount = ref(0);

const checkPendingWork = async () => {
    try {
        const outbox = await listCaseOutbox(props.clinicalCase.id);
        casePendingCount.value = outbox.caseSections.length;
        legacyPendingCount.value = outbox.ambiguousSections.length;
    } catch {
        // IndexedDB may be unavailable (e.g. some private-browsing modes).
        // If it cannot be read we do not know of any pending work, so do not
        // block submission on a storage error.
        casePendingCount.value = 0;
        legacyPendingCount.value = 0;
    }
};

const handleOnline = () => {
    online.value = true;
    void checkPendingWork();
};
const handleOffline = () => {
    online.value = false;
};
const handleVisibility = () => {
    if (document.visibilityState === 'visible') {
        void checkPendingWork();
    }
};
onMounted(() => {
    void checkPendingWork();
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);
    window.addEventListener('focus', checkPendingWork);
    document.addEventListener('visibilitychange', handleVisibility);
});
onUnmounted(() => {
    window.removeEventListener('online', handleOnline);
    window.removeEventListener('offline', handleOffline);
    window.removeEventListener('focus', checkPendingWork);
    document.removeEventListener('visibilitychange', handleVisibility);
});

const attested = ref(false);
const isReady = computed(() => props.submissionErrors.length === 0);
const hasCaseOutboxWork = computed(() => casePendingCount.value > 0);
const hasLegacyOutboxWork = computed(() => legacyPendingCount.value > 0);
const hasPendingOutboxWork = computed(
    () => hasCaseOutboxWork.value || hasLegacyOutboxWork.value,
);
const canSubmit = computed(
    () =>
        isReady.value &&
        attested.value &&
        online.value &&
        !hasPendingOutboxWork.value,
);

const form = useForm({ deidentification_attested: false });
const submit = () => {
    form.deidentification_attested = true;
    form.post(`/student/cases/${props.clinicalCase.id}/submit`, {
        preserveScroll: true,
    });
};

// The submit endpoint can reject with validation/422 errors (for example a
// missing attestation or a server-side completeness re-check). Inertia
// delivers these both on the form instance and on the shared page props;
// merge and de-duplicate them so none are silently swallowed.
const page = usePage();
const serverErrorMessages = computed<string[]>(() => {
    const messages = new Set<string>();
    const shared = page.props.errors as
        | Record<string, string | string[]>
        | undefined;
    for (const value of Object.values(shared ?? {})) {
        for (const message of Array.isArray(value) ? value : [value]) {
            if (typeof message === 'string' && message.trim() !== '') {
                messages.add(message);
            }
        }
    }
    for (const message of Object.values(form.errors)) {
        if (typeof message === 'string' && message.trim() !== '') {
            messages.add(message);
        }
    }
    return [...messages];
});

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

const vitalDisplay = (vital: Row): string => {
    if (vital.value_systolic !== null && vital.value_diastolic !== null) {
        return `${vital.observation_type}: ${vital.value_systolic}/${vital.value_diastolic}`;
    }
    const value = vital.value_numeric ?? vital.value_text;
    return `${vital.observation_type}: ${value ?? '—'}${vital.unit ? ' ' + vital.unit : ''}`;
};

const investigationDisplay = (investigation: Row): string => {
    const unit = investigation.unit_not_stated
        ? 'unit not stated'
        : (investigation.unit ?? '');
    return `${investigation.test_name}: ${investigation.result_value ?? '—'} ${unit}`.trim();
};

const medicationDisplay = (medication: Row): string => {
    const parts = [
        medication.dose_amount,
        medication.dose_unit,
        medication.route,
        medication.frequency,
    ]
        .filter((part) => part !== null && part !== undefined && part !== '')
        .join(' ');
    return `${medication.generic_name}${parts ? ' — ' + parts : ''}`;
};

const activityDisplay = (activity: Record<string, unknown>): string =>
    Object.entries((activity.details as Record<string, unknown> | null) ?? {})
        .filter(([, value]) => value !== null && value !== '')
        .map(([key, value]) => `${key.replace(/_/g, ' ')}: ${value}`)
        .join(' · ');
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
            class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-7 dark:border-slate-700 dark:bg-slate-900"
        >
            <h2
                class="font-display text-lg font-semibold text-[#0b2942] dark:text-white"
            >
                What you are about to submit
            </h2>
            <div class="mt-4 space-y-4 text-sm">
                <div>
                    <h3 class="text-xs font-bold text-slate-500 uppercase">
                        Case profile
                    </h3>
                    <p class="mt-1">
                        {{ context.care_setting ?? '—' }} ·
                        {{ context.encounter_date ?? '—' }} ·
                        {{ context.age_value ?? '—' }}
                        {{ context.age_unit ?? '' }} · {{ context.sex ?? '—' }}
                    </p>
                </div>

                <div v-if="clinicalProfile">
                    <h3 class="text-xs font-bold text-slate-500 uppercase">
                        History &amp; diagnosis
                    </h3>
                    <p
                        v-if="clinicalProfile.history_present_illness"
                        class="mt-1 whitespace-pre-wrap"
                    >
                        {{ clinicalProfile.history_present_illness }}
                    </p>
                    <p class="mt-1">
                        Allergy status:
                        {{ clinicalProfile.allergy_status ?? 'not answered' }}
                        <template
                            v-if="
                                clinicalProfile.allergy_status ===
                                'known_allergy'
                            "
                        >
                            ({{ clinicalProfile.allergy_substance }})
                        </template>
                    </p>
                </div>

                <div>
                    <h3 class="text-xs font-bold text-slate-500 uppercase">
                        Vitals
                    </h3>
                    <p
                        v-if="context.vitals_status === 'unavailable'"
                        class="mt-1 text-slate-500"
                    >
                        Marked unavailable:
                        {{ context.vitals_unavailable_reason }}
                    </p>
                    <ul
                        v-else-if="vitals.length"
                        class="mt-1 list-inside list-disc"
                    >
                        <li v-for="vital in vitals" :key="vital.id">
                            {{ vitalDisplay(vital) }}
                        </li>
                    </ul>
                    <p v-else class="mt-1 text-slate-500">
                        No vitals recorded.
                    </p>
                </div>

                <div>
                    <h3 class="text-xs font-bold text-slate-500 uppercase">
                        Investigations
                    </h3>
                    <p
                        v-if="context.investigations_status === 'unavailable'"
                        class="mt-1 text-slate-500"
                    >
                        Marked unavailable:
                        {{ context.investigations_unavailable_reason }}
                    </p>
                    <ul
                        v-else-if="investigations.length"
                        class="mt-1 list-inside list-disc"
                    >
                        <li
                            v-for="investigation in investigations"
                            :key="investigation.id"
                        >
                            {{ investigationDisplay(investigation) }}
                        </li>
                    </ul>
                    <p v-else class="mt-1 text-slate-500">
                        No investigations recorded.
                    </p>
                </div>

                <div>
                    <h3 class="text-xs font-bold text-slate-500 uppercase">
                        Medication chart
                    </h3>
                    <p
                        v-if="
                            context.medication_chart_status ===
                            'none_documented'
                        "
                        class="mt-1 text-slate-500"
                    >
                        No current medicines documented:
                        {{ context.medication_chart_none_reason }}
                    </p>
                    <ul
                        v-else-if="medications.length"
                        class="mt-1 list-inside list-disc"
                    >
                        <li
                            v-for="medication in medications"
                            :key="medication.id"
                        >
                            {{ medicationDisplay(medication) }}
                        </li>
                    </ul>
                    <p v-else class="mt-1 text-slate-500">
                        No medicines recorded.
                    </p>
                </div>

                <div v-if="soap">
                    <h3 class="text-xs font-bold text-slate-500 uppercase">
                        SOAP
                    </h3>
                    <dl class="mt-1 space-y-1">
                        <div>
                            <dt class="inline font-medium">S:</dt>
                            <dd class="inline">{{ soap.subjective ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="inline font-medium">O:</dt>
                            <dd class="inline">{{ soap.objective ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="inline font-medium">A:</dt>
                            <dd class="inline">{{ soap.assessment ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="inline font-medium">P:</dt>
                            <dd class="inline">{{ soap.plan ?? '—' }}</dd>
                        </div>
                    </dl>
                    <p class="mt-1">
                        Monitoring plan:
                        {{
                            soap.monitoring_plan_not_applicable
                                ? `Not applicable — ${soap.monitoring_plan_not_applicable_reason}`
                                : (soap.monitoring_plan ?? '—')
                        }}
                    </p>
                    <p class="mt-1">
                        Drug-related problem:
                        {{ soap.drug_related_problem_status ?? 'not answered' }}
                    </p>
                </div>

                <div>
                    <h3 class="text-xs font-bold text-slate-500 uppercase">
                        Conditional clinical activities
                    </h3>
                    <p class="mt-1">
                        Suspected ADR: {{ adr?.status ?? 'not answered' }}
                    </p>
                    <p
                        v-if="adr?.status === 'yes'"
                        class="mt-1 text-slate-600 dark:text-slate-300"
                    >
                        {{ activityDisplay(adr) }}
                    </p>
                    <p class="mt-1">
                        Patient counselling:
                        {{ counselling?.status ?? 'not answered' }}
                    </p>
                    <p v-if="interventions.length" class="mt-1">
                        {{ interventions.length }} pharmacist intervention(s)
                        recorded.
                    </p>
                    <p v-if="monitoringFollowUps.length" class="mt-1">
                        {{ monitoringFollowUps.length }} monitoring follow-up(s)
                        recorded.
                    </p>
                </div>
            </div>
        </section>

        <section
            v-if="hasPendingOutboxWork"
            data-test="pending-outbox-warning"
            class="rounded-3xl border border-rose-300 bg-rose-50 p-5 sm:p-7 dark:border-rose-800 dark:bg-rose-950"
        >
            <h2
                class="font-display flex items-center gap-2 text-lg font-semibold text-rose-800 dark:text-rose-200"
            >
                <AlertTriangle class="size-5" /> Unsynced changes on this device
            </h2>
            <p
                v-if="hasCaseOutboxWork"
                class="mt-3 text-sm text-rose-800 dark:text-rose-200"
            >
                {{ casePendingCount }} change(s) to this case have not been
                saved to the server yet. Submitting now would send an older
                version of the case.
            </p>
            <p
                v-if="hasLegacyOutboxWork"
                data-test="legacy-outbox-warning"
                class="mt-3 text-sm text-rose-800 dark:text-rose-200"
            >
                Older unsynced changes on this device must be resolved before
                submission.
            </p>
            <p class="mt-2 text-sm text-rose-800 dark:text-rose-200">
                Open the case editor to finish syncing, or reconnect, then
                return here to submit.
            </p>
            <Button
                class="mt-4 bg-rose-700 text-white"
                @click="goToSection('case_profile')"
            >
                Open case editor
            </Button>
        </section>

        <section
            v-if="serverErrorMessages.length"
            data-test="server-submission-errors"
            class="rounded-3xl border border-rose-300 bg-rose-50 p-5 sm:p-7 dark:border-rose-800 dark:bg-rose-950"
        >
            <h2
                class="font-display flex items-center gap-2 text-lg font-semibold text-rose-800 dark:text-rose-200"
            >
                <AlertTriangle class="size-5" /> The server did not accept this
                submission
            </h2>
            <ul
                class="mt-3 list-inside list-disc space-y-1 text-sm text-rose-800 dark:text-rose-200"
            >
                <li
                    v-for="(message, index) in serverErrorMessages"
                    :key="index"
                >
                    {{ message }}
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
            v-if="!submissionErrors.length"
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
