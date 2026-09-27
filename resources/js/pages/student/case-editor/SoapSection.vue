<script setup lang="ts">
import {
    RefreshCw,
    Check,
    CloudOff,
    FileClock,
    AlertTriangle,
} from '@lucide/vue';
import { computed } from 'vue';
import {
    useSectionSync,
    type SyncedSection,
} from '@/composables/useSectionSync';
import DeidentificationNotice from '@/components/DeidentificationNotice.vue';

const DRUG_RELATED_PROBLEM_CATEGORIES = [
    'untreated_indication',
    'medicine_without_indication',
    'ineffective_medicine',
    'dose_too_low',
    'dose_too_high',
    'adr',
    'interaction',
    'non_adherence',
    'duplication',
    'administration_problem',
    'monitoring_required',
    'other',
];

type SoapPayload = SyncedSection & {
    subjective: string | null;
    objective: string | null;
    assessment: string | null;
    plan: string | null;
    monitoring_plan: string | null;
    monitoring_plan_not_applicable: boolean;
    monitoring_plan_not_applicable_reason: string | null;
    drug_related_problem_status: string | null;
    drug_related_problem_categories: string[] | null;
};

const props = defineProps<{
    caseId: string;
    userId: number;
    initial: SoapPayload;
}>();

const {
    payload,
    state,
    edit,
    conflict,
    resolveWithServer,
    keepDeviceCopy,
    replaceServer,
    retry,
    confirmingReplace,
} = useSectionSync<SoapPayload>({
    userId: props.userId,
    resourceId: props.caseId,
    sectionKey: 'soap',
    endpoint: `/student/cases/${props.caseId}/soap`,
    initialPayload: props.initial,
});

const statusLabel = computed(
    () =>
        ({
            saving: 'Saving…',
            server: 'Saved',
            device: 'Saved on this device',
            unsynced: 'Unsynced changes',
            failed: 'Sync failed',
            conflict: 'Conflict — review changes',
            incomplete: 'Incomplete',
        })[state.value],
);

function onMonitoringPlanInput() {
    if (
        payload.value.monitoring_plan !== null &&
        payload.value.monitoring_plan !== '' &&
        payload.value.monitoring_plan_not_applicable
    ) {
        payload.value.monitoring_plan_not_applicable = false;
    }
    edit();
}
function onMonitoringNotApplicableChange() {
    if (payload.value.monitoring_plan_not_applicable) {
        payload.value.monitoring_plan = null;
    } else {
        payload.value.monitoring_plan_not_applicable_reason = null;
    }
    edit();
}

function toggleCategory(category: string) {
    const current = payload.value.drug_related_problem_categories ?? [];
    payload.value.drug_related_problem_categories = current.includes(category)
        ? current.filter((c) => c !== category)
        : [...current, category];
    edit();
}
</script>

<template>
    <section aria-labelledby="soap-heading" class="space-y-5">
        <div class="flex items-center justify-between">
            <h2
                id="soap-heading"
                class="font-display text-lg text-[#0b2942] dark:text-white"
            >
                SOAP
            </h2>
            <span
                class="flex items-center gap-1.5 text-xs font-semibold text-slate-500"
                data-test="soap-status"
            >
                <component
                    :is="
                        state === 'saving'
                            ? RefreshCw
                            : state === 'server'
                              ? Check
                              : state === 'device'
                                ? CloudOff
                                : state === 'unsynced'
                                  ? FileClock
                                  : AlertTriangle
                    "
                    class="size-3.5"
                    :class="state === 'saving' ? 'animate-spin' : ''"
                />
                {{ statusLabel }}
            </span>
        </div>

        <label class="block text-sm">
            <span
                class="mb-1 block font-medium text-slate-700 dark:text-slate-200"
                >Subjective</span
            >
            <textarea
                v-model="payload.subjective"
                rows="4"
                maxlength="5000"
                data-test="soap-subjective"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                @input="edit"
            />
            <DeidentificationNotice :text="payload.subjective" />
        </label>

        <label class="block text-sm">
            <span
                class="mb-1 block font-medium text-slate-700 dark:text-slate-200"
                >Objective</span
            >
            <textarea
                v-model="payload.objective"
                rows="4"
                maxlength="5000"
                data-test="soap-objective"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                @input="edit"
            />
            <DeidentificationNotice :text="payload.objective" />
        </label>

        <label class="block text-sm">
            <span
                class="mb-1 block font-medium text-slate-700 dark:text-slate-200"
                >Assessment</span
            >
            <textarea
                v-model="payload.assessment"
                rows="4"
                maxlength="5000"
                data-test="soap-assessment"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                @input="edit"
            />
            <DeidentificationNotice :text="payload.assessment" />
        </label>

        <fieldset>
            <legend
                class="mb-2 text-sm font-bold text-slate-700 dark:text-slate-200"
            >
                Drug-related problem status
            </legend>
            <div class="flex flex-wrap gap-3 text-sm">
                <label
                    v-for="option in [
                        'none_identified',
                        'identified',
                        'unable_to_assess',
                    ]"
                    :key="option"
                    class="flex items-center gap-1.5"
                >
                    <input
                        v-model="payload.drug_related_problem_status"
                        type="radio"
                        :value="option"
                        :data-test="`drp-status-${option}`"
                        @change="edit"
                    />
                    {{ option.replace(/_/g, ' ') }}
                </label>
            </div>
        </fieldset>

        <div
            v-if="payload.drug_related_problem_status === 'identified'"
            class="flex flex-wrap gap-2"
        >
            <button
                v-for="category in DRUG_RELATED_PROBLEM_CATEGORIES"
                :key="category"
                type="button"
                :data-test="`drp-category-${category}`"
                class="rounded-full border px-3 py-1 text-xs font-semibold"
                :class="
                    (payload.drug_related_problem_categories ?? []).includes(
                        category,
                    )
                        ? 'border-[#0b2942] bg-[#0b2942] text-white'
                        : 'border-slate-200 text-slate-500'
                "
                @click="toggleCategory(category)"
            >
                {{ category.replace(/_/g, ' ') }}
            </button>
        </div>

        <label class="block text-sm">
            <span
                class="mb-1 block font-medium text-slate-700 dark:text-slate-200"
                >Plan</span
            >
            <textarea
                v-model="payload.plan"
                rows="4"
                maxlength="5000"
                data-test="soap-plan"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                @input="edit"
            />
            <DeidentificationNotice :text="payload.plan" />
        </label>

        <label class="block text-sm">
            <span
                class="mb-1 block font-medium text-slate-700 dark:text-slate-200"
                >Monitoring plan</span
            >
            <textarea
                v-model="payload.monitoring_plan"
                rows="3"
                maxlength="2000"
                :disabled="payload.monitoring_plan_not_applicable"
                data-test="soap-monitoring-plan"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-900"
                @input="onMonitoringPlanInput"
            />
        </label>
        <label class="flex items-center gap-2 text-sm">
            <input
                v-model="payload.monitoring_plan_not_applicable"
                type="checkbox"
                data-test="soap-monitoring-not-applicable"
                @change="onMonitoringNotApplicableChange"
            />
            Not applicable
        </label>
        <label
            v-if="payload.monitoring_plan_not_applicable"
            class="block text-sm"
        >
            <span
                class="mb-1 block font-medium text-slate-700 dark:text-slate-200"
                >Reason monitoring is not applicable</span
            >
            <input
                v-model="payload.monitoring_plan_not_applicable_reason"
                type="text"
                maxlength="1000"
                data-test="soap-monitoring-not-applicable-reason"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                @input="edit"
            />
        </label>

        <section
            v-if="conflict"
            data-test="soap-conflict"
            class="rounded-2xl border border-rose-200 bg-white p-4 dark:border-rose-900 dark:bg-slate-900"
        >
            <p class="text-sm font-bold text-rose-700">
                The server changed after this device began editing.
            </p>
            <div class="mt-3 grid gap-2">
                <button
                    type="button"
                    class="rounded-xl border px-3 py-2 text-left text-sm font-bold"
                    @click="resolveWithServer"
                >
                    Use server version
                </button>
                <button
                    type="button"
                    class="rounded-xl border px-3 py-2 text-left text-sm font-bold"
                    @click="keepDeviceCopy"
                >
                    Keep local draft as a copy
                </button>
                <button
                    v-if="!confirmingReplace"
                    type="button"
                    class="rounded-xl border border-rose-200 px-3 py-2 text-left text-sm font-bold text-rose-700"
                    @click="confirmingReplace = true"
                >
                    Replace server version
                </button>
                <button
                    v-else
                    type="button"
                    class="rounded-xl bg-rose-700 px-3 py-2 text-sm font-bold text-white"
                    @click="replaceServer"
                >
                    Yes, replace it
                </button>
            </div>
        </section>

        <button
            v-if="state === 'failed'"
            type="button"
            class="rounded-xl bg-[#0b2942] px-4 py-2.5 text-sm font-bold text-white"
            @click="retry"
        >
            Retry
        </button>
    </section>
</template>
