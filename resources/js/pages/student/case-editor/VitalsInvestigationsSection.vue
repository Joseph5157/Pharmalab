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
    LOCAL_ROW_PREFIX,
    useRepeatableRowCreate,
} from '@/composables/useRepeatableRowCreate';
import {
    useSectionSync,
    type SyncedSection,
} from '@/composables/useSectionSync';
import InvestigationRow from './InvestigationRow.vue';
import VitalRow from './VitalRow.vue';

type RowPayload = SyncedSection & { id: string; [key: string]: unknown };
type AvailabilityPayload = SyncedSection & {
    vitals_status: string | null;
    vitals_unavailable_reason: string | null;
};
type InvestigationsAvailabilityPayload = SyncedSection & {
    investigations_status: string | null;
    investigations_unavailable_reason: string | null;
};

const props = defineProps<{
    caseId: string;
    userId: number;
    initialVitals: RowPayload[];
    initialInvestigations: RowPayload[];
    initialVitalsAvailability: AvailabilityPayload;
    initialInvestigationsAvailability: InvestigationsAvailabilityPayload;
}>();

const vitals = ref([...props.initialVitals]);
const investigations = ref([...props.initialInvestigations]);

const vitalsSync = useSectionSync<AvailabilityPayload>({
    userId: props.userId,
    resourceId: props.caseId,
    sectionKey: 'vitals_availability',
    endpoint: `/student/cases/${props.caseId}/vitals-availability`,
    initialPayload: props.initialVitalsAvailability,
});
const investigationsSync = useSectionSync<InvestigationsAvailabilityPayload>({
    userId: props.userId,
    resourceId: props.caseId,
    sectionKey: 'investigations_availability',
    endpoint: `/student/cases/${props.caseId}/investigations-availability`,
    initialPayload: props.initialInvestigationsAvailability,
});

const vitalsCreate = useRepeatableRowCreate<RowPayload>({
    userId: props.userId,
    sectionKey: 'vitals',
    endpoint: `/student/cases/${props.caseId}/vitals`,
    responseKey: 'vital',
    emptyPayload: () => ({
        observation_type: null,
        value_numeric: null,
        value_text: null,
        value_systolic: null,
        value_diastolic: null,
        unit: null,
        observed_on: null,
        observed_at_time: null,
        source: null,
        note: null,
    }),
});
const investigationsCreate = useRepeatableRowCreate<RowPayload>({
    userId: props.userId,
    sectionKey: 'investigations',
    endpoint: `/student/cases/${props.caseId}/investigations`,
    responseKey: 'investigation',
    emptyPayload: () => ({
        test_name: null,
        result_type: 'numeric',
        result_value: null,
        unit: null,
        unit_not_stated: false,
        reference_range: null,
        reference_range_not_provided: false,
        reported_flag: null,
        observed_on: null,
        observed_at_time: null,
        interpretation: null,
    }),
});

async function addVital() {
    vitals.value = [await vitalsCreate.queueCreate(), ...vitals.value];
}
async function addInvestigation() {
    investigations.value = [
        await investigationsCreate.queueCreate(),
        ...investigations.value,
    ];
}

function removeVital(id: string) {
    if (id.startsWith(LOCAL_ROW_PREFIX))
        void vitalsCreate.cancelQueuedCreate(id);
    vitals.value = vitals.value.filter((v) => v.id !== id);
}
function removeInvestigation(id: string) {
    if (id.startsWith(LOCAL_ROW_PREFIX))
        void investigationsCreate.cancelQueuedCreate(id);
    investigations.value = investigations.value.filter((i) => i.id !== id);
}

function handleReconnect() {
    void vitalsCreate.replayPending((localId, row) => {
        vitals.value = vitals.value.map((v) => (v.id === localId ? row : v));
    });
    void investigationsCreate.replayPending((localId, row) => {
        investigations.value = investigations.value.map((i) =>
            i.id === localId ? row : i,
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

const syncStatusIcon = (state: string) =>
    ({
        saving: RefreshCw,
        server: Check,
        device: CloudOff,
        unsynced: FileClock,
        failed: AlertTriangle,
        conflict: AlertTriangle,
        incomplete: FileClock,
    })[state] ?? FileClock;
const syncStatusLabel = (state: string) =>
    ({
        saving: 'Saving…',
        server: 'Saved',
        device: 'Saved on this device',
        unsynced: 'Unsynced',
        failed: 'Sync failed',
        conflict: 'Conflict',
        incomplete: 'Incomplete',
    })[state] ?? state;

const vitalsStatusLabel = computed(() =>
    syncStatusLabel(vitalsSync.state.value),
);
const investigationsStatusLabel = computed(() =>
    syncStatusLabel(investigationsSync.state.value),
);
</script>

<template>
    <section aria-labelledby="vitals-investigations-heading" class="space-y-8">
        <h2
            id="vitals-investigations-heading"
            class="font-display text-lg text-[#0b2942] dark:text-white"
        >
            Vitals &amp; Investigations
        </h2>

        <div>
            <div class="mb-3 flex items-center justify-between">
                <h3
                    class="text-sm font-bold text-slate-700 dark:text-slate-200"
                >
                    Vitals
                </h3>
                <span
                    class="flex items-center gap-1.5 text-xs font-semibold text-slate-500"
                    data-test="vitals-availability-status"
                >
                    <component
                        :is="syncStatusIcon(vitalsSync.state.value)"
                        class="size-3.5"
                    />
                    {{ vitalsStatusLabel }}
                </span>
            </div>

            <fieldset class="mb-3">
                <legend class="sr-only">Vitals availability</legend>
                <div class="flex flex-wrap gap-3 text-sm">
                    <label class="flex items-center gap-1.5">
                        <input
                            v-model="vitalsSync.payload.value.vitals_status"
                            type="radio"
                            value="recorded"
                            data-test="vitals-status-recorded"
                            @change="vitalsSync.edit"
                        />
                        Recorded below
                    </label>
                    <label class="flex items-center gap-1.5">
                        <input
                            v-model="vitalsSync.payload.value.vitals_status"
                            type="radio"
                            value="unavailable"
                            data-test="vitals-status-unavailable"
                            @change="vitalsSync.edit"
                        />
                        Unavailable / not clinically relevant
                    </label>
                </div>
                <label
                    v-if="
                        vitalsSync.payload.value.vitals_status === 'unavailable'
                    "
                    class="mt-2 block text-sm"
                >
                    <span class="sr-only">Reason vitals are unavailable</span>
                    <input
                        v-model="
                            vitalsSync.payload.value.vitals_unavailable_reason
                        "
                        type="text"
                        maxlength="1000"
                        placeholder="Reason"
                        data-test="vitals-unavailable-reason"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                        @input="vitalsSync.edit"
                    />
                </label>
            </fieldset>

            <div
                v-if="vitalsSync.payload.value.vitals_status !== 'unavailable'"
                class="space-y-3"
            >
                <template v-for="vital in vitals" :key="vital.id">
                    <div
                        v-if="vital.id.startsWith(LOCAL_ROW_PREFIX)"
                        class="rounded-2xl border border-dashed border-slate-300 p-4 text-xs text-slate-500 dark:border-slate-600"
                        data-test="vital-row-pending"
                    >
                        Waiting to sync…
                    </div>
                    <VitalRow
                        v-else
                        :case-id="caseId"
                        :user-id="userId"
                        :initial="vital as any"
                        @removed="removeVital"
                    />
                </template>
                <button
                    type="button"
                    class="flex items-center gap-1 text-sm font-bold text-[#0b2942]"
                    @click="addVital"
                >
                    <Plus class="size-4" /> Add vital
                </button>
            </div>
        </div>

        <div>
            <h3
                class="mb-3 text-sm font-bold text-slate-700 dark:text-slate-200"
            >
                Investigations
            </h3>
            <div class="mb-3 flex items-center justify-between">
                <span class="sr-only">Investigations sync status</span>
                <span
                    class="flex items-center gap-1.5 text-xs font-semibold text-slate-500"
                    data-test="investigations-availability-status"
                >
                    <component
                        :is="syncStatusIcon(investigationsSync.state.value)"
                        class="size-3.5"
                    />
                    {{ investigationsStatusLabel }}
                </span>
            </div>
            <fieldset class="mb-3">
                <legend class="sr-only">Investigations availability</legend>
                <div class="flex flex-wrap gap-3 text-sm">
                    <label class="flex items-center gap-1.5">
                        <input
                            v-model="
                                investigationsSync.payload.value
                                    .investigations_status
                            "
                            type="radio"
                            value="recorded"
                            data-test="investigations-status-recorded"
                            @change="investigationsSync.edit"
                        />
                        Recorded below
                    </label>
                    <label class="flex items-center gap-1.5">
                        <input
                            v-model="
                                investigationsSync.payload.value
                                    .investigations_status
                            "
                            type="radio"
                            value="unavailable"
                            data-test="investigations-status-unavailable"
                            @change="investigationsSync.edit"
                        />
                        Unavailable / not clinically relevant
                    </label>
                </div>
                <label
                    v-if="
                        investigationsSync.payload.value
                            .investigations_status === 'unavailable'
                    "
                    class="mt-2 block text-sm"
                >
                    <span class="sr-only"
                        >Reason investigations are unavailable</span
                    >
                    <input
                        v-model="
                            investigationsSync.payload.value
                                .investigations_unavailable_reason
                        "
                        type="text"
                        maxlength="1000"
                        placeholder="Reason"
                        data-test="investigations-unavailable-reason"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                        @input="investigationsSync.edit"
                    />
                </label>
            </fieldset>

            <div
                v-if="
                    investigationsSync.payload.value.investigations_status !==
                    'unavailable'
                "
                class="space-y-3"
            >
                <template
                    v-for="investigation in investigations"
                    :key="investigation.id"
                >
                    <div
                        v-if="investigation.id.startsWith(LOCAL_ROW_PREFIX)"
                        class="rounded-2xl border border-dashed border-slate-300 p-4 text-xs text-slate-500 dark:border-slate-600"
                        data-test="investigation-row-pending"
                    >
                        Waiting to sync…
                    </div>
                    <InvestigationRow
                        v-else
                        :case-id="caseId"
                        :user-id="userId"
                        :initial="investigation as any"
                        @removed="removeInvestigation"
                    />
                </template>
                <button
                    type="button"
                    class="flex items-center gap-1 text-sm font-bold text-[#0b2942]"
                    @click="addInvestigation"
                >
                    <Plus class="size-4" /> Add investigation
                </button>
            </div>
        </div>
    </section>
</template>
