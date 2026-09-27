<script setup lang="ts">
import {
    AlertTriangle,
    Check,
    CloudOff,
    FileClock,
    Plus,
    RefreshCw,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import {
    useSectionSync,
    type SyncedSection,
} from '@/composables/useSectionSync';
import {
    LOCAL_ROW_PREFIX,
    useRepeatableRowCreate,
} from '@/composables/useRepeatableRowCreate';
import DeidentificationNotice from '@/components/DeidentificationNotice.vue';
import ActivityRow from './ActivityRow.vue';

type RowPayload = SyncedSection & {
    id: string;
    activity_type: 'intervention' | 'monitoring';
    status: string | null;
    details: Record<string, unknown> | null;
};
type AdrPayload = SyncedSection & {
    // CaseEditorController's activityPayload() is shared with the repeatable
    // rows, so a singleton that already exists on page load carries these two
    // extra keys too — never sent back (see the readonlyFields below).
    id?: string;
    activity_type?: string;
    status: string | null;
    details: Record<string, unknown> | null;
};
type CounsellingPayload = SyncedSection & {
    id?: string;
    activity_type?: string;
    status: string | null;
    details: Record<string, unknown> | null;
};

const emptyAdr: AdrPayload = {
    status: null,
    details: null,
    lock_version: 0,
    updated_at: new Date().toISOString(),
};
const emptyCounselling: CounsellingPayload = {
    status: null,
    details: null,
    lock_version: 0,
    updated_at: new Date().toISOString(),
};

const props = defineProps<{
    caseId: string;
    userId: number;
    initialAdr: AdrPayload | null;
    initialCounselling: CounsellingPayload | null;
    initialInterventions: RowPayload[];
    initialMonitoringFollowUps: RowPayload[];
}>();

const interventions = ref([...props.initialInterventions]);
const monitoringFollowUps = ref([...props.initialMonitoringFollowUps]);

const adrSync = useSectionSync<AdrPayload>({
    userId: props.userId,
    resourceId: props.caseId,
    sectionKey: 'clinical_activity_adr',
    endpoint: `/student/cases/${props.caseId}/clinical-activities/adr`,
    initialPayload: props.initialAdr ?? emptyAdr,
    // initialAdr comes straight from CaseEditorController's activityPayload(),
    // which also carries id/activity_type for the repeatable rows' sake — once
    // the row already exists on page load, those two keys ride along into
    // every edit unless excluded here, and UpdateAdrActivityRequest doesn't
    // recognize either as a top-level field (Task 9 live device verification).
    readonlyFields: ['id', 'activity_type'],
});
const counsellingSync = useSectionSync<CounsellingPayload>({
    userId: props.userId,
    resourceId: props.caseId,
    sectionKey: 'clinical_activity_counselling',
    endpoint: `/student/cases/${props.caseId}/clinical-activities/counselling`,
    initialPayload: props.initialCounselling ?? emptyCounselling,
    readonlyFields: ['id', 'activity_type'],
});

const interventionCreate = useRepeatableRowCreate<RowPayload>({
    userId: props.userId,
    sectionKey: 'clinical_activities_intervention',
    endpoint: `/student/cases/${props.caseId}/clinical-activities`,
    responseKey: 'activity',
    emptyPayload: () => ({
        activity_type: 'intervention',
        status: null,
        details: null,
    }),
});
const monitoringCreate = useRepeatableRowCreate<RowPayload>({
    userId: props.userId,
    sectionKey: 'clinical_activities_monitoring',
    endpoint: `/student/cases/${props.caseId}/clinical-activities`,
    responseKey: 'activity',
    emptyPayload: () => ({
        activity_type: 'monitoring',
        status: null,
        details: null,
    }),
});

async function addIntervention() {
    interventions.value = [
        await interventionCreate.queueCreate(),
        ...interventions.value,
    ];
}
async function addMonitoring() {
    monitoringFollowUps.value = [
        await monitoringCreate.queueCreate(),
        ...monitoringFollowUps.value,
    ];
}
function removeIntervention(id: string) {
    if (id.startsWith(LOCAL_ROW_PREFIX))
        void interventionCreate.cancelQueuedCreate(id);
    interventions.value = interventions.value.filter((r) => r.id !== id);
}
function removeMonitoring(id: string) {
    if (id.startsWith(LOCAL_ROW_PREFIX))
        void monitoringCreate.cancelQueuedCreate(id);
    monitoringFollowUps.value = monitoringFollowUps.value.filter(
        (r) => r.id !== id,
    );
}

async function retryInterventionCreate(localId: string) {
    const row = await interventionCreate.retryFailedCreate(localId);
    if (row)
        interventions.value = interventions.value.map((r) =>
            r.id === localId ? row : r,
        );
}
async function retryMonitoringCreate(localId: string) {
    const row = await monitoringCreate.retryFailedCreate(localId);
    if (row)
        monitoringFollowUps.value = monitoringFollowUps.value.map((r) =>
            r.id === localId ? row : r,
        );
}

function handleReconnect() {
    void interventionCreate.replayPending((localId, row) => {
        interventions.value = interventions.value.map((r) =>
            r.id === localId ? row : r,
        );
    });
    void monitoringCreate.replayPending((localId, row) => {
        monitoringFollowUps.value = monitoringFollowUps.value.map((r) =>
            r.id === localId ? row : r,
        );
    });
}
onMounted(() => {
    handleReconnect();
    window.addEventListener('online', handleReconnect);
});
onBeforeUnmount(() => {
    window.removeEventListener('online', handleReconnect);
});

function updateAdrDetail(key: string, value: unknown) {
    adrSync.payload.value.details = {
        ...adrSync.payload.value.details,
        [key]: value,
    };
    adrSync.edit();
}
function updateCounsellingDetail(key: string, value: unknown) {
    counsellingSync.payload.value.details = {
        ...counsellingSync.payload.value.details,
        [key]: value,
    };
    counsellingSync.edit();
}
function adrDetail(key: string): string {
    return (adrSync.payload.value.details?.[key] as string) ?? '';
}
function counsellingDetail(key: string): string {
    return (counsellingSync.payload.value.details?.[key] as string) ?? '';
}

const statusIcon = (state: string) =>
    ({
        saving: RefreshCw,
        server: Check,
        device: CloudOff,
        unsynced: FileClock,
        failed: AlertTriangle,
        conflict: AlertTriangle,
        incomplete: FileClock,
    })[state] ?? FileClock;
const statusLabel = (state: string) =>
    ({
        saving: 'Saving…',
        server: 'Saved',
        device: 'Saved on this device',
        unsynced: 'Unsynced changes',
        failed: 'Sync failed',
        conflict: 'Conflict — review changes',
        incomplete: 'Incomplete',
    })[state] ?? state;
const adrStatusLabel = computed(() => statusLabel(adrSync.state.value));
const counsellingStatusLabel = computed(() =>
    statusLabel(counsellingSync.state.value),
);
</script>

<template>
    <section aria-labelledby="clinical-activities-heading" class="space-y-8">
        <h2
            id="clinical-activities-heading"
            class="font-display text-lg text-[#0b2942] dark:text-white"
        >
            Conditional Clinical Activities
        </h2>

        <div>
            <h3
                class="mb-2 text-sm font-bold text-slate-700 dark:text-slate-200"
            >
                Pharmacist intervention
            </h3>
            <div class="space-y-3">
                <template v-for="row in interventions" :key="row.id">
                    <div
                        v-if="row.id.startsWith(LOCAL_ROW_PREFIX)"
                        class="rounded-2xl border p-4 text-xs"
                        :class="
                            interventionCreate.failedCreateErrors.value[row.id]
                                ? 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300'
                                : 'border-dashed border-slate-300 text-slate-500 dark:border-slate-600'
                        "
                        :data-test="
                            interventionCreate.failedCreateErrors.value[row.id]
                                ? 'intervention-row-create-failed'
                                : 'intervention-row-pending'
                        "
                    >
                        <template
                            v-if="
                                interventionCreate.failedCreateErrors.value[
                                    row.id
                                ]
                            "
                        >
                            <p role="alert" class="font-bold">
                                {{
                                    interventionCreate.failedCreateErrors.value[
                                        row.id
                                    ][0]
                                }}
                            </p>
                            <div class="mt-2 flex gap-3 font-bold">
                                <button
                                    type="button"
                                    class="underline"
                                    @click="retryInterventionCreate(row.id)"
                                >
                                    Retry
                                </button>
                                <button
                                    type="button"
                                    class="underline"
                                    @click="removeIntervention(row.id)"
                                >
                                    Discard
                                </button>
                            </div>
                        </template>
                        <template v-else>Waiting to sync…</template>
                    </div>
                    <ActivityRow
                        v-else
                        :case-id="caseId"
                        :user-id="userId"
                        :initial="row"
                        @removed="removeIntervention"
                    />
                </template>
                <button
                    type="button"
                    class="flex items-center gap-1 text-sm font-bold text-[#0b2942]"
                    @click="addIntervention"
                >
                    <Plus class="size-4" /> Add intervention
                </button>
            </div>
        </div>

        <div>
            <div class="mb-2 flex items-center justify-between">
                <h3
                    class="text-sm font-bold text-slate-700 dark:text-slate-200"
                >
                    Suspected ADR
                </h3>
                <span
                    class="flex items-center gap-1.5 text-xs font-semibold text-slate-500"
                    data-test="adr-status"
                >
                    <component
                        :is="statusIcon(adrSync.state.value)"
                        class="size-3.5"
                        :class="
                            adrSync.state.value === 'saving'
                                ? 'animate-spin'
                                : ''
                        "
                    />
                    {{ adrStatusLabel }}
                </span>
            </div>
            <fieldset class="mb-3">
                <legend class="sr-only">Suspected ADR</legend>
                <div class="flex flex-wrap gap-3 text-sm">
                    <label
                        v-for="option in ['yes', 'no', 'unable_to_assess']"
                        :key="option"
                        class="flex items-center gap-1.5"
                    >
                        <input
                            v-model="adrSync.payload.value.status"
                            type="radio"
                            :value="option"
                            :data-test="`adr-status-${option}`"
                            @change="adrSync.edit"
                        />
                        {{ option.replace(/_/g, ' ') }}
                    </label>
                </div>
            </fieldset>
            <div
                v-if="adrSync.state.value === 'failed'"
                data-test="adr-errors"
                role="alert"
                class="mb-3 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300"
            >
                <p v-if="adrSync.validationErrors.value.length === 0">
                    This section could not be saved. Check your connection and
                    try again.
                </p>
                <ul v-else class="list-disc space-y-1 pl-5">
                    <li
                        v-for="(message, index) in adrSync.validationErrors
                            .value"
                        :key="index"
                    >
                        {{ message }}
                    </li>
                </ul>
                <button
                    type="button"
                    class="mt-2 font-bold underline"
                    @click="adrSync.retry"
                >
                    Retry
                </button>
            </div>
            <div
                v-if="adrSync.payload.value.status === 'yes'"
                class="space-y-2"
            >
                <label class="block text-sm">
                    <span class="mb-1 block text-xs text-slate-500"
                        >Event / reaction</span
                    >
                    <input
                        :value="adrDetail('event')"
                        type="text"
                        maxlength="1000"
                        data-test="adr-event"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                        @input="
                            updateAdrDetail(
                                'event',
                                ($event.target as HTMLInputElement).value,
                            )
                        "
                    />
                    <DeidentificationNotice :text="adrDetail('event')" />
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-xs text-slate-500"
                        >Suspected medicine</span
                    >
                    <input
                        :value="adrDetail('suspected_medicine')"
                        type="text"
                        maxlength="255"
                        data-test="adr-suspected-medicine"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                        @input="
                            updateAdrDetail(
                                'suspected_medicine',
                                ($event.target as HTMLInputElement).value,
                            )
                        "
                    />
                </label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="text-sm">
                        <span class="mb-1 block text-xs text-slate-500"
                            >Onset</span
                        >
                        <input
                            :value="adrDetail('onset_reference')"
                            type="text"
                            maxlength="30"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                            @input="
                                updateAdrDetail(
                                    'onset_reference',
                                    ($event.target as HTMLInputElement).value,
                                )
                            "
                        />
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-xs text-slate-500"
                            >Stop</span
                        >
                        <input
                            :value="adrDetail('stop_reference')"
                            type="text"
                            maxlength="30"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                            @input="
                                updateAdrDetail(
                                    'stop_reference',
                                    ($event.target as HTMLInputElement).value,
                                )
                            "
                        />
                    </label>
                </div>
                <label class="block text-sm">
                    <span class="mb-1 block text-xs text-slate-500"
                        >Dose/route/frequency</span
                    >
                    <input
                        :value="adrDetail('dose_route_frequency')"
                        type="text"
                        maxlength="255"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                        @input="
                            updateAdrDetail(
                                'dose_route_frequency',
                                ($event.target as HTMLInputElement).value,
                            )
                        "
                    />
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-xs text-slate-500"
                        >Concomitant medicines</span
                    >
                    <textarea
                        :value="adrDetail('concomitant_medicines')"
                        rows="2"
                        maxlength="1000"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                        @input="
                            updateAdrDetail(
                                'concomitant_medicines',
                                ($event.target as HTMLTextAreaElement).value,
                            )
                        "
                    />
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-xs text-slate-500"
                        >Relevant tests</span
                    >
                    <textarea
                        :value="adrDetail('relevant_tests')"
                        rows="2"
                        maxlength="1000"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                        @input="
                            updateAdrDetail(
                                'relevant_tests',
                                ($event.target as HTMLTextAreaElement).value,
                            )
                        "
                    />
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-xs text-slate-500"
                        >Action taken</span
                    >
                    <textarea
                        :value="adrDetail('action_taken')"
                        rows="2"
                        maxlength="1000"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                        @input="
                            updateAdrDetail(
                                'action_taken',
                                ($event.target as HTMLTextAreaElement).value,
                            )
                        "
                    />
                </label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="text-sm">
                        <span class="mb-1 block text-xs text-slate-500"
                            >Seriousness</span
                        >
                        <select
                            :value="adrDetail('seriousness')"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                            @change="
                                updateAdrDetail(
                                    'seriousness',
                                    ($event.target as HTMLSelectElement).value,
                                )
                            "
                        >
                            <option value="">Select…</option>
                            <option value="serious">Serious</option>
                            <option value="non_serious">Non-serious</option>
                        </select>
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-xs text-slate-500"
                            >Outcome</span
                        >
                        <input
                            :value="adrDetail('outcome')"
                            type="text"
                            maxlength="255"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                            @input="
                                updateAdrDetail(
                                    'outcome',
                                    ($event.target as HTMLInputElement).value,
                                )
                            "
                        />
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-xs text-slate-500"
                            >Dechallenge</span
                        >
                        <input
                            :value="adrDetail('dechallenge')"
                            type="text"
                            maxlength="255"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                            @input="
                                updateAdrDetail(
                                    'dechallenge',
                                    ($event.target as HTMLInputElement).value,
                                )
                            "
                        />
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-xs text-slate-500"
                            >Rechallenge</span
                        >
                        <input
                            :value="adrDetail('rechallenge')"
                            type="text"
                            maxlength="255"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                            @input="
                                updateAdrDetail(
                                    'rechallenge',
                                    ($event.target as HTMLInputElement).value,
                                )
                            "
                        />
                    </label>
                </div>
            </div>
            <section
                v-if="adrSync.conflict.value"
                data-test="adr-conflict"
                class="mt-3 rounded-xl border border-rose-200 bg-white p-3 dark:border-rose-900 dark:bg-slate-900"
            >
                <p class="text-xs font-bold text-rose-700">
                    The server changed after this device began editing.
                </p>
                <div class="mt-2 grid gap-1.5">
                    <button
                        type="button"
                        class="rounded-lg border px-2 py-1.5 text-left text-xs font-bold"
                        @click="adrSync.resolveWithServer"
                    >
                        Use server version
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border px-2 py-1.5 text-left text-xs font-bold"
                        @click="adrSync.keepDeviceCopy"
                    >
                        Keep local draft as a copy
                    </button>
                    <button
                        v-if="!adrSync.confirmingReplace.value"
                        type="button"
                        class="rounded-lg border border-rose-200 px-2 py-1.5 text-left text-xs font-bold text-rose-700"
                        @click="adrSync.confirmingReplace.value = true"
                    >
                        Replace server version
                    </button>
                    <button
                        v-else
                        type="button"
                        class="rounded-lg bg-rose-700 px-2 py-1.5 text-xs font-bold text-white"
                        @click="adrSync.replaceServer"
                    >
                        Yes, replace it
                    </button>
                </div>
            </section>
        </div>

        <div>
            <div class="mb-2 flex items-center justify-between">
                <h3
                    class="text-sm font-bold text-slate-700 dark:text-slate-200"
                >
                    Patient counselling
                </h3>
                <span
                    class="flex items-center gap-1.5 text-xs font-semibold text-slate-500"
                    data-test="counselling-status"
                >
                    <component
                        :is="statusIcon(counsellingSync.state.value)"
                        class="size-3.5"
                        :class="
                            counsellingSync.state.value === 'saving'
                                ? 'animate-spin'
                                : ''
                        "
                    />
                    {{ counsellingStatusLabel }}
                </span>
            </div>
            <fieldset class="mb-3">
                <legend class="sr-only">Patient counselling</legend>
                <div class="flex flex-wrap gap-3 text-sm">
                    <label
                        v-for="option in [
                            'performed',
                            'planned',
                            'not_indicated',
                            'unable_to_perform',
                        ]"
                        :key="option"
                        class="flex items-center gap-1.5"
                    >
                        <input
                            v-model="counsellingSync.payload.value.status"
                            type="radio"
                            :value="option"
                            :data-test="`counselling-status-${option}`"
                            @change="counsellingSync.edit"
                        />
                        {{ option.replace(/_/g, ' ') }}
                    </label>
                </div>
            </fieldset>
            <div
                v-if="counsellingSync.state.value === 'failed'"
                data-test="counselling-errors"
                role="alert"
                class="mb-3 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300"
            >
                <p v-if="counsellingSync.validationErrors.value.length === 0">
                    This section could not be saved. Check your connection and
                    try again.
                </p>
                <ul v-else class="list-disc space-y-1 pl-5">
                    <li
                        v-for="(message, index) in counsellingSync
                            .validationErrors.value"
                        :key="index"
                    >
                        {{ message }}
                    </li>
                </ul>
                <button
                    type="button"
                    class="mt-2 font-bold underline"
                    @click="counsellingSync.retry"
                >
                    Retry
                </button>
            </div>
            <div
                v-if="
                    counsellingSync.payload.value.status === 'performed' ||
                    counsellingSync.payload.value.status === 'planned'
                "
                class="space-y-2"
            >
                <label class="block text-sm">
                    <span class="mb-1 block text-xs text-slate-500"
                        >Topics covered</span
                    >
                    <textarea
                        :value="counsellingDetail('topics')"
                        rows="3"
                        maxlength="2000"
                        data-test="counselling-topics"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                        @input="
                            updateCounsellingDetail(
                                'topics',
                                ($event.target as HTMLTextAreaElement).value,
                            )
                        "
                    />
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-xs text-slate-500"
                        >Medicine purpose</span
                    >
                    <textarea
                        :value="counsellingDetail('medicine_purpose')"
                        rows="2"
                        maxlength="1000"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                        @input="
                            updateCounsellingDetail(
                                'medicine_purpose',
                                ($event.target as HTMLTextAreaElement).value,
                            )
                        "
                    />
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-xs text-slate-500"
                        >Administration</span
                    >
                    <textarea
                        :value="counsellingDetail('administration')"
                        rows="2"
                        maxlength="1000"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                        @input="
                            updateCounsellingDetail(
                                'administration',
                                ($event.target as HTMLTextAreaElement).value,
                            )
                        "
                    />
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-xs text-slate-500"
                        >Adherence</span
                    >
                    <textarea
                        :value="counsellingDetail('adherence')"
                        rows="2"
                        maxlength="1000"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                        @input="
                            updateCounsellingDetail(
                                'adherence',
                                ($event.target as HTMLTextAreaElement).value,
                            )
                        "
                    />
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-xs text-slate-500"
                        >Precautions</span
                    >
                    <textarea
                        :value="counsellingDetail('precautions')"
                        rows="2"
                        maxlength="1000"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                        @input="
                            updateCounsellingDetail(
                                'precautions',
                                ($event.target as HTMLTextAreaElement).value,
                            )
                        "
                    />
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-xs text-slate-500"
                        >Important adverse effects</span
                    >
                    <textarea
                        :value="counsellingDetail('adverse_effects')"
                        rows="2"
                        maxlength="1000"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                        @input="
                            updateCounsellingDetail(
                                'adverse_effects',
                                ($event.target as HTMLTextAreaElement).value,
                            )
                        "
                    />
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-xs text-slate-500"
                        >Storage</span
                    >
                    <input
                        :value="counsellingDetail('storage')"
                        type="text"
                        maxlength="500"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                        @input="
                            updateCounsellingDetail(
                                'storage',
                                ($event.target as HTMLInputElement).value,
                            )
                        "
                    />
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-xs text-slate-500"
                        >Lifestyle / follow-up</span
                    >
                    <textarea
                        :value="counsellingDetail('lifestyle_follow_up')"
                        rows="2"
                        maxlength="1000"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                        @input="
                            updateCounsellingDetail(
                                'lifestyle_follow_up',
                                ($event.target as HTMLTextAreaElement).value,
                            )
                        "
                    />
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input
                        :checked="
                            (counsellingSync.payload.value.details
                                ?.understanding_checked as boolean) ?? false
                        "
                        type="checkbox"
                        data-test="counselling-understanding-checked"
                        @change="
                            updateCounsellingDetail(
                                'understanding_checked',
                                ($event.target as HTMLInputElement).checked,
                            )
                        "
                    />
                    Understanding checked
                </label>
            </div>
            <section
                v-if="counsellingSync.conflict.value"
                data-test="counselling-conflict"
                class="mt-3 rounded-xl border border-rose-200 bg-white p-3 dark:border-rose-900 dark:bg-slate-900"
            >
                <p class="text-xs font-bold text-rose-700">
                    The server changed after this device began editing.
                </p>
                <div class="mt-2 grid gap-1.5">
                    <button
                        type="button"
                        class="rounded-lg border px-2 py-1.5 text-left text-xs font-bold"
                        @click="counsellingSync.resolveWithServer"
                    >
                        Use server version
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border px-2 py-1.5 text-left text-xs font-bold"
                        @click="counsellingSync.keepDeviceCopy"
                    >
                        Keep local draft as a copy
                    </button>
                    <button
                        v-if="!counsellingSync.confirmingReplace.value"
                        type="button"
                        class="rounded-lg border border-rose-200 px-2 py-1.5 text-left text-xs font-bold text-rose-700"
                        @click="counsellingSync.confirmingReplace.value = true"
                    >
                        Replace server version
                    </button>
                    <button
                        v-else
                        type="button"
                        class="rounded-lg bg-rose-700 px-2 py-1.5 text-xs font-bold text-white"
                        @click="counsellingSync.replaceServer"
                    >
                        Yes, replace it
                    </button>
                </div>
            </section>
        </div>

        <div>
            <h3
                class="mb-2 text-sm font-bold text-slate-700 dark:text-slate-200"
            >
                Monitoring follow-up
            </h3>
            <div class="space-y-3">
                <template v-for="row in monitoringFollowUps" :key="row.id">
                    <div
                        v-if="row.id.startsWith(LOCAL_ROW_PREFIX)"
                        class="rounded-2xl border p-4 text-xs"
                        :class="
                            monitoringCreate.failedCreateErrors.value[row.id]
                                ? 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300'
                                : 'border-dashed border-slate-300 text-slate-500 dark:border-slate-600'
                        "
                        :data-test="
                            monitoringCreate.failedCreateErrors.value[row.id]
                                ? 'monitoring-row-create-failed'
                                : 'monitoring-row-pending'
                        "
                    >
                        <template
                            v-if="
                                monitoringCreate.failedCreateErrors.value[
                                    row.id
                                ]
                            "
                        >
                            <p role="alert" class="font-bold">
                                {{
                                    monitoringCreate.failedCreateErrors.value[
                                        row.id
                                    ][0]
                                }}
                            </p>
                            <div class="mt-2 flex gap-3 font-bold">
                                <button
                                    type="button"
                                    class="underline"
                                    @click="retryMonitoringCreate(row.id)"
                                >
                                    Retry
                                </button>
                                <button
                                    type="button"
                                    class="underline"
                                    @click="removeMonitoring(row.id)"
                                >
                                    Discard
                                </button>
                            </div>
                        </template>
                        <template v-else>Waiting to sync…</template>
                    </div>
                    <ActivityRow
                        v-else
                        :case-id="caseId"
                        :user-id="userId"
                        :initial="row"
                        @removed="removeMonitoring"
                    />
                </template>
                <button
                    type="button"
                    class="flex items-center gap-1 text-sm font-bold text-[#0b2942]"
                    @click="addMonitoring"
                >
                    <Plus class="size-4" /> Add follow-up result
                </button>
            </div>
        </div>
    </section>
</template>
