<script setup lang="ts">
import {
    AlertTriangle,
    Check,
    CloudOff,
    FileClock,
    Plus,
    RefreshCw,
    Trash2,
} from '@lucide/vue';
import { computed } from 'vue';
import DeidentificationNotice from '@/components/DeidentificationNotice.vue';
import {
    useSectionSync,
    type SyncedSection,
} from '@/composables/useSectionSync';

type ChiefComplaint = { complaint: string; duration: string | null };
type Diagnosis = {
    label: string;
    type: 'provisional' | 'confirmed' | 'comorbidity' | null;
};
type ClinicalProfilePayload = SyncedSection & {
    chief_complaints: ChiefComplaint[] | null;
    history_present_illness: string | null;
    diagnoses: Diagnosis[] | null;
    past_medical_history: string | null;
    past_medical_history_none: boolean;
    past_surgical_history: string | null;
    adherence_status: string | null;
    family_history: string | null;
    substance_history: string | null;
    examination_findings: string | null;
    allergy_status: string | null;
    allergy_substance: string | null;
    allergy_reaction: string | null;
};

const props = defineProps<{
    caseId: string;
    userId: number;
    initial: ClinicalProfilePayload;
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
} = useSectionSync<ClinicalProfilePayload>({
    userId: props.userId,
    resourceId: props.caseId,
    sectionKey: 'clinical_profile',
    endpoint: `/student/cases/${props.caseId}/clinical-profile`,
    initialPayload: props.initial,
});

const statusLabel = computed(
    () =>
        ({
            saving: 'Saving...',
            server: 'Saved',
            device: 'Saved on this device',
            unsynced: 'Unsynced changes',
            failed: 'Sync failed',
            conflict: 'Conflict - review changes',
        })[state.value],
);
const statusIcon = computed(() =>
    state.value === 'saving'
        ? RefreshCw
        : state.value === 'server'
          ? Check
          : state.value === 'device'
            ? CloudOff
            : state.value === 'unsynced'
              ? FileClock
              : AlertTriangle,
);

function addComplaint(): void {
    payload.value.chief_complaints = [
        ...(payload.value.chief_complaints ?? []),
        { complaint: '', duration: null },
    ];
    void edit();
}
function removeComplaint(index: number): void {
    payload.value.chief_complaints = (
        payload.value.chief_complaints ?? []
    ).filter((_, currentIndex) => currentIndex !== index);
    void edit();
}
function addDiagnosis(): void {
    payload.value.diagnoses = [
        ...(payload.value.diagnoses ?? []),
        { label: '', type: 'provisional' },
    ];
    void edit();
}
function removeDiagnosis(index: number): void {
    payload.value.diagnoses = (payload.value.diagnoses ?? []).filter(
        (_, currentIndex) => currentIndex !== index,
    );
    void edit();
}
</script>

<template>
    <section aria-labelledby="history-diagnosis-heading" class="space-y-6">
        <div class="flex items-center justify-between gap-3">
            <h2
                id="history-diagnosis-heading"
                class="font-display text-lg text-[#0b2942] dark:text-white"
            >
                History &amp; Diagnosis
            </h2>
            <span
                data-test="history-diagnosis-status"
                class="flex shrink-0 items-center gap-1.5 text-xs font-semibold text-slate-500"
            >
                <component
                    :is="statusIcon"
                    class="size-3.5"
                    :class="state === 'saving' ? 'animate-spin' : ''"
                />
                {{ statusLabel }}
            </span>
        </div>

        <div
            class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/60"
        >
            <h3
                class="mb-2 text-sm font-bold text-slate-700 dark:text-slate-200"
            >
                Chief complaints
            </h3>
            <div
                v-for="(complaint, index) in payload.chief_complaints ?? []"
                :key="index"
                class="mb-2 grid grid-cols-[minmax(0,1fr)_7rem_auto] gap-2"
            >
                <label class="text-sm">
                    <span class="sr-only">Complaint {{ index + 1 }}</span>
                    <input
                        v-model="complaint.complaint"
                        type="text"
                        maxlength="255"
                        placeholder="Complaint"
                        :data-test="`chief-complaint-${index}`"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                        @input="edit"
                    />
                    <DeidentificationNotice :text="complaint.complaint" />
                </label>
                <label class="text-sm">
                    <span class="sr-only"
                        >Duration for complaint {{ index + 1 }}</span
                    >
                    <input
                        v-model="complaint.duration"
                        type="text"
                        maxlength="60"
                        placeholder="Duration"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                        @input="edit"
                    />
                </label>
                <button
                    type="button"
                    :aria-label="`Remove complaint ${index + 1}`"
                    class="rounded-xl border border-slate-200 px-2 text-slate-600 dark:border-slate-700 dark:text-slate-300"
                    @click="removeComplaint(index)"
                >
                    <Trash2 class="size-4" />
                </button>
            </div>
            <button
                type="button"
                class="flex items-center gap-1 text-sm font-bold text-[#0b2942] dark:text-sky-300"
                @click="addComplaint"
            >
                <Plus class="size-4" /> Add complaint
            </button>
        </div>

        <label class="block text-sm">
            <span
                class="mb-1 block font-medium text-slate-700 dark:text-slate-200"
                >History of present illness</span
            >
            <textarea
                v-model="payload.history_present_illness"
                rows="5"
                maxlength="5000"
                data-test="history-present-illness"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                @input="edit"
            />
            <DeidentificationNotice :text="payload.history_present_illness" />
        </label>

        <div
            class="rounded-2xl border border-slate-200 p-4 dark:border-slate-700"
        >
            <h3
                class="mb-2 text-sm font-bold text-slate-700 dark:text-slate-200"
            >
                Diagnosis / active problem
            </h3>
            <div
                v-for="(diagnosis, index) in payload.diagnoses ?? []"
                :key="index"
                class="mb-2 grid grid-cols-[minmax(0,1fr)_8rem_auto] gap-2"
            >
                <label class="text-sm">
                    <span class="sr-only">Diagnosis {{ index + 1 }}</span>
                    <input
                        v-model="diagnosis.label"
                        type="text"
                        maxlength="255"
                        placeholder="Diagnosis"
                        :data-test="`diagnosis-${index}`"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                        @input="edit"
                    />
                    <DeidentificationNotice :text="diagnosis.label" />
                </label>
                <label class="text-sm">
                    <span class="sr-only"
                        >Type for diagnosis {{ index + 1 }}</span
                    >
                    <select
                        v-model="diagnosis.type"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                        @change="edit"
                    >
                        <option value="provisional">Provisional</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="comorbidity">Comorbidity</option>
                    </select>
                </label>
                <button
                    type="button"
                    :aria-label="`Remove diagnosis ${index + 1}`"
                    class="rounded-xl border border-slate-200 px-2 text-slate-600 dark:border-slate-700 dark:text-slate-300"
                    @click="removeDiagnosis(index)"
                >
                    <Trash2 class="size-4" />
                </button>
            </div>
            <button
                type="button"
                class="flex items-center gap-1 text-sm font-bold text-[#0b2942] dark:text-sky-300"
                @click="addDiagnosis"
            >
                <Plus class="size-4" /> Add diagnosis
            </button>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <label class="block text-sm sm:col-span-2">
                <span
                    class="mb-1 block font-medium text-slate-700 dark:text-slate-200"
                    >Past medical history</span
                >
                <textarea
                    v-model="payload.past_medical_history"
                    rows="3"
                    maxlength="5000"
                    :disabled="payload.past_medical_history_none"
                    data-test="past-medical-history"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-900"
                    @input="edit"
                />
                <DeidentificationNotice :text="payload.past_medical_history" />
            </label>
            <label class="flex items-center gap-2 text-sm sm:col-span-2"
                ><input
                    v-model="payload.past_medical_history_none"
                    type="checkbox"
                    data-test="past-medical-history-none"
                    @change="edit"
                />
                None known / not available</label
            >
            <label class="block text-sm sm:col-span-2">
                <span
                    class="mb-1 block font-medium text-slate-700 dark:text-slate-200"
                    >Past surgical history (optional)</span
                >
                <textarea
                    v-model="payload.past_surgical_history"
                    rows="2"
                    maxlength="5000"
                    data-test="past-surgical-history"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                    @input="edit"
                />
                <DeidentificationNotice :text="payload.past_surgical_history" />
            </label>
            <fieldset class="sm:col-span-2">
                <legend
                    class="mb-2 text-sm font-bold text-slate-700 dark:text-slate-200"
                >
                    Adherence status
                </legend>
                <div class="flex flex-wrap gap-3 text-sm">
                    <label
                        v-for="option in [
                            'adherent',
                            'partially_adherent',
                            'non_adherent',
                            'unable_to_assess',
                        ]"
                        :key="option"
                        class="flex items-center gap-1.5"
                        ><input
                            v-model="payload.adherence_status"
                            type="radio"
                            :value="option"
                            :data-test="`adherence-status-${option}`"
                            @change="edit"
                        />
                        {{ option.replace(/_/g, ' ') }}</label
                    >
                </div>
            </fieldset>
            <label class="block text-sm"
                ><span
                    class="mb-1 block font-medium text-slate-700 dark:text-slate-200"
                    >Family history (optional)</span
                ><textarea
                    v-model="payload.family_history"
                    rows="2"
                    maxlength="5000"
                    data-test="family-history"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                    @input="edit" /><DeidentificationNotice
                    :text="payload.family_history"
            /></label>
            <label class="block text-sm"
                ><span
                    class="mb-1 block font-medium text-slate-700 dark:text-slate-200"
                    >Tobacco/alcohol/substance history (optional)</span
                ><textarea
                    v-model="payload.substance_history"
                    rows="2"
                    maxlength="5000"
                    data-test="substance-history"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                    @input="edit" /><DeidentificationNotice
                    :text="payload.substance_history"
            /></label>
            <label class="block text-sm sm:col-span-2"
                ><span
                    class="mb-1 block font-medium text-slate-700 dark:text-slate-200"
                    >Relevant examination findings (optional; note the
                    source)</span
                ><textarea
                    v-model="payload.examination_findings"
                    rows="3"
                    maxlength="5000"
                    data-test="examination-findings"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                    @input="edit" /><DeidentificationNotice
                    :text="payload.examination_findings"
            /></label>
        </div>

        <fieldset
            class="rounded-2xl border border-slate-200 p-4 dark:border-slate-700"
        >
            <legend
                class="px-1 text-sm font-bold text-slate-700 dark:text-slate-200"
            >
                Allergy status
            </legend>
            <div class="flex flex-wrap gap-3">
                <label
                    v-for="option in [
                        'no_known_allergy',
                        'known_allergy',
                        'unknown',
                    ]"
                    :key="option"
                    class="flex items-center gap-1.5 text-sm"
                    ><input
                        v-model="payload.allergy_status"
                        type="radio"
                        :value="option"
                        :data-test="`allergy-status-${option}`"
                        @change="edit"
                    />
                    {{ option.replace(/_/g, ' ') }}</label
                >
            </div>
            <div
                v-if="payload.allergy_status === 'known_allergy'"
                class="mt-4 grid gap-4 sm:grid-cols-2"
            >
                <label class="block text-sm"
                    ><span
                        class="mb-1 block font-medium text-slate-700 dark:text-slate-200"
                        >Allergy substance</span
                    ><input
                        v-model="payload.allergy_substance"
                        type="text"
                        maxlength="1000"
                        data-test="allergy-substance"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                        @input="edit" /><DeidentificationNotice
                        :text="payload.allergy_substance"
                /></label>
                <label class="block text-sm"
                    ><span
                        class="mb-1 block font-medium text-slate-700 dark:text-slate-200"
                        >Reaction</span
                    ><input
                        v-model="payload.allergy_reaction"
                        type="text"
                        maxlength="1000"
                        data-test="allergy-reaction"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                        @input="edit" /><DeidentificationNotice
                        :text="payload.allergy_reaction"
                /></label>
            </div>
        </fieldset>

        <section
            v-if="conflict"
            data-test="history-diagnosis-conflict"
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
                    Use server version</button
                ><button
                    type="button"
                    class="rounded-xl border px-3 py-2 text-left text-sm font-bold"
                    @click="keepDeviceCopy"
                >
                    Keep local draft as a copy</button
                ><button
                    v-if="!confirmingReplace"
                    type="button"
                    class="rounded-xl border border-rose-200 px-3 py-2 text-left text-sm font-bold text-rose-700"
                    @click="confirmingReplace = true"
                >
                    Replace server version</button
                ><button
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
