<script setup lang="ts">
import { computed } from 'vue';
import {
    useSectionSync,
    type SyncedSection,
} from '@/composables/useSectionSync';

type Payload = SyncedSection & {
    encounter_date: string | null;
    case_category: string | null;
    age_value: number | null;
    age_unit: string | null;
    sex: string | null;
    care_setting: string | null;
    hospital_day_at_first_review: number | null;
    information_source: string | null;
    weight_kg: string | null;
    height_cm: string | null;
    pregnancy_lactation_status: string | null;
    case_display: {
        case_number: number;
        rotation_name: string | null;
        clinical_site_name: string | null;
        ward_name: string | null;
    };
};
const props = defineProps<{
    caseId: string;
    userId: number;
    initial: Payload;
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
    validationErrors,
} = useSectionSync<Payload>({
    userId: props.userId,
    resourceId: props.caseId,
    sectionKey: 'case_context',
    endpoint: `/student/cases/${props.caseId}/context`,
    initialPayload: props.initial,
    readonlyFields: ['case_display'],
    isSyncReady: (p) => (p.age_value === null) === (p.age_unit === null),
});
const status = computed(
    () =>
        ({
            saving: 'Saving...',
            server: 'Saved',
            device: 'Saved on this device',
            unsynced: 'Unsynced changes',
            failed: 'Sync failed',
            conflict: 'Conflict - review changes',
            incomplete: 'Complete both age and age unit',
        })[state.value],
);
const ageMax = computed(
    () => ({ days: 364, months: 59, years: 120 })[payload.value.age_unit ?? ''],
);
function normalizeNumber(key: keyof Payload) {
    if ((payload.value[key] as unknown) === '') {
        (payload.value as Record<string, unknown>)[key] = null;
    }
}
function onNumberInput(key: keyof Payload) {
    normalizeNumber(key);
    edit();
}
</script>
<template>
    <section aria-labelledby="case-profile-heading" class="space-y-5">
        <div class="flex items-center justify-between">
            <h2
                id="case-profile-heading"
                class="text-lg font-bold text-[#0b2942] dark:text-white"
            >
                Case Profile
            </h2>
            <span
                data-test="case-profile-status"
                class="text-xs font-semibold text-slate-500"
                >{{ status }}</span
            >
        </div>
        <div
            data-test="case-display"
            class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm dark:border-slate-700 dark:bg-slate-800"
        >
            <p class="font-bold">
                Educational Case ID: #{{ payload.case_display.case_number }}
            </p>
            <p class="mt-1 text-slate-500">
                {{
                    payload.case_display.rotation_name ??
                    'Rotation not assigned'
                }}
                /
                {{
                    payload.case_display.clinical_site_name ??
                    'Site not assigned'
                }}
                / {{ payload.case_display.ward_name ?? 'Ward not assigned' }}
            </p>
            <p class="mt-1 text-xs text-slate-400">Generated and read-only.</p>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <label
                ><span>Care setting</span
                ><select
                    v-model="payload.care_setting"
                    data-test="care-setting"
                    class="mt-1 w-full rounded-xl border p-2"
                    @change="edit"
                >
                    <option :value="null">Select...</option>
                    <option value="inpatient">Inpatient</option>
                    <option value="outpatient">Outpatient</option>
                    <option value="emergency">Emergency</option>
                    <option value="other">Other</option>
                </select></label
            >
            <label
                ><span>Case documentation date</span
                ><input
                    v-model="payload.encounter_date"
                    type="date"
                    class="mt-1 w-full rounded-xl border p-2"
                    @change="edit"
            /></label>
            <label
                ><span>Information source</span
                ><input
                    v-model="payload.information_source"
                    maxlength="60"
                    class="mt-1 w-full rounded-xl border p-2"
                    @input="edit"
            /></label>
            <label
                ><span>Hospital day at first review</span
                ><input
                    v-model.number="payload.hospital_day_at_first_review"
                    type="number"
                    min="1"
                    max="999"
                    class="mt-1 w-full rounded-xl border p-2"
                    @input="onNumberInput('hospital_day_at_first_review')"
            /></label>
            <label
                ><span>Age</span
                ><input
                    v-model.number="payload.age_value"
                    type="number"
                    min="0"
                    :max="ageMax"
                    class="mt-1 w-full rounded-xl border p-2"
                    @input="onNumberInput('age_value')"
            /></label>
            <label
                ><span>Age unit</span
                ><select
                    v-model="payload.age_unit"
                    class="mt-1 w-full rounded-xl border p-2"
                    @change="edit"
                >
                    <option :value="null">None</option>
                    <option value="days">Days</option>
                    <option value="months">Months</option>
                    <option value="years">Years</option>
                </select></label
            >
            <label
                ><span>Sex as recorded</span
                ><select
                    v-model="payload.sex"
                    class="mt-1 w-full rounded-xl border p-2"
                    @change="edit"
                >
                    <option :value="null">Select...</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                    <option value="intersex">Intersex</option>
                    <option value="unknown">Unknown</option>
                </select></label
            >
            <label
                ><span>Weight (kg)</span
                ><input
                    v-model.number="payload.weight_kg"
                    type="number"
                    min="0"
                    max="500"
                    class="mt-1 w-full rounded-xl border p-2"
                    @input="onNumberInput('weight_kg')"
            /></label>
            <label
                ><span>Height (cm)</span
                ><input
                    v-model.number="payload.height_cm"
                    type="number"
                    min="0"
                    max="300"
                    class="mt-1 w-full rounded-xl border p-2"
                    @input="onNumberInput('height_cm')"
            /></label>
            <label class="sm:col-span-2"
                ><span>Pregnancy/lactation status (if relevant)</span
                ><input
                    v-model="payload.pregnancy_lactation_status"
                    maxlength="30"
                    class="mt-1 w-full rounded-xl border p-2"
                    @input="edit"
            /></label>
        </div>
        <div
            v-if="conflict"
            data-test="case-profile-conflict"
            class="rounded-xl border border-rose-200 p-4"
        >
            <p class="font-bold text-rose-700">
                The server changed after this device began editing.
            </p>
            <div class="mt-3 grid gap-2">
                <button type="button" @click="resolveWithServer">
                    Use server version</button
                ><button type="button" @click="keepDeviceCopy">
                    Keep local draft as a copy</button
                ><button
                    v-if="!confirmingReplace"
                    type="button"
                    @click="confirmingReplace = true"
                >
                    Replace server version</button
                ><button v-else type="button" @click="replaceServer">
                    Yes, replace it
                </button>
            </div>
        </div>
        <div
            v-if="state === 'failed'"
            data-test="case-profile-errors"
            role="alert"
            class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300"
        >
            <p v-if="validationErrors.length === 0">
                This section could not be saved. Check your connection and try
                again.
            </p>
            <ul v-else class="list-disc space-y-1 pl-5">
                <li v-for="(message, index) in validationErrors" :key="index">
                    {{ message }}
                </li>
            </ul>
        </div>
        <button v-if="state === 'failed'" type="button" @click="retry">
            Retry
        </button>
    </section>
</template>
