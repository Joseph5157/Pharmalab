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
import MedicationRow from './MedicationRow.vue';

type RowPayload = SyncedSection & { id: string; [key: string]: unknown };
type AvailabilityPayload = SyncedSection & {
    medication_chart_status: string | null;
    medication_chart_none_reason: string | null;
};

const props = defineProps<{
    caseId: string;
    userId: number;
    initialMedications: RowPayload[];
    initialAvailability: AvailabilityPayload;
}>();

const medications = ref([...props.initialMedications]);

const availabilitySync = useSectionSync<AvailabilityPayload>({
    userId: props.userId,
    resourceId: props.caseId,
    sectionKey: 'medication_chart_availability',
    endpoint: `/student/cases/${props.caseId}/medication-chart-availability`,
    initialPayload: props.initialAvailability,
});

const medicationsCreate = useRepeatableRowCreate<RowPayload>({
    userId: props.userId,
    sectionKey: 'medications',
    endpoint: `/student/cases/${props.caseId}/medications`,
    responseKey: 'medication',
    emptyPayload: () => ({
        medication_context: null,
        generic_name: null,
        brand_name: null,
        indication: null,
        indication_unclear: false,
        dose_amount: null,
        dose_unit: null,
        dosage_form: null,
        route: null,
        frequency: null,
        start_reference: null,
        status: 'active',
        stop_reference: null,
        prn_indication: null,
        notes: null,
    }),
});

async function addMedication() {
    medications.value = [
        await medicationsCreate.queueCreate(),
        ...medications.value,
    ];
}
function removeMedication(id: string) {
    if (id.startsWith(LOCAL_ROW_PREFIX))
        void medicationsCreate.cancelQueuedCreate(id);
    medications.value = medications.value.filter((m) => m.id !== id);
}

function handleReconnect() {
    void medicationsCreate.replayPending((localId, row) => {
        medications.value = medications.value.map((m) =>
            m.id === localId ? row : m,
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

const statusIcon = computed(
    () =>
        ({
            saving: RefreshCw,
            server: Check,
            device: CloudOff,
            unsynced: FileClock,
            failed: AlertTriangle,
            conflict: AlertTriangle,
            incomplete: FileClock,
        })[availabilitySync.state.value],
);
const statusLabel = computed(
    () =>
        ({
            saving: 'Saving…',
            server: 'Saved',
            device: 'Saved on this device',
            unsynced: 'Unsynced',
            failed: 'Sync failed',
            conflict: 'Conflict',
            incomplete: 'Incomplete',
        })[availabilitySync.state.value],
);
</script>

<template>
    <section aria-labelledby="medication-chart-heading" class="space-y-6">
        <div class="flex items-center justify-between">
            <h2
                id="medication-chart-heading"
                class="font-display text-lg text-[#0b2942] dark:text-white"
            >
                Medication Chart
            </h2>
            <span
                class="flex items-center gap-1.5 text-xs font-semibold text-slate-500"
                data-test="medication-chart-status"
            >
                <component :is="statusIcon" class="size-3.5" />
                {{ statusLabel }}
            </span>
        </div>

        <fieldset>
            <legend class="sr-only">Medication chart availability</legend>
            <div class="flex flex-wrap gap-3 text-sm">
                <label class="flex items-center gap-1.5">
                    <input
                        v-model="
                            availabilitySync.payload.value
                                .medication_chart_status
                        "
                        type="radio"
                        value="documented"
                        data-test="medication-chart-status-documented"
                        @change="availabilitySync.edit"
                    />
                    Medicines documented below
                </label>
                <label class="flex items-center gap-1.5">
                    <input
                        v-model="
                            availabilitySync.payload.value
                                .medication_chart_status
                        "
                        type="radio"
                        value="none_documented"
                        data-test="medication-chart-status-none"
                        @change="availabilitySync.edit"
                    />
                    No current medicines documented
                </label>
            </div>
            <label
                v-if="
                    availabilitySync.payload.value.medication_chart_status ===
                    'none_documented'
                "
                class="mt-2 block text-sm"
            >
                <span class="sr-only">Explanation (optional)</span>
                <input
                    v-model="
                        availabilitySync.payload.value
                            .medication_chart_none_reason
                    "
                    type="text"
                    maxlength="1000"
                    placeholder="Explanation (optional)"
                    data-test="medication-chart-none-reason"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    @input="availabilitySync.edit"
                />
            </label>
        </fieldset>

        <div
            v-if="
                availabilitySync.payload.value.medication_chart_status !==
                'none_documented'
            "
            class="space-y-3"
        >
            <template v-for="medication in medications" :key="medication.id">
                <div
                    v-if="medication.id.startsWith(LOCAL_ROW_PREFIX)"
                    class="rounded-2xl border border-dashed border-slate-300 p-4 text-xs text-slate-500 dark:border-slate-600"
                    data-test="medication-row-pending"
                >
                    Waiting to sync…
                </div>
                <MedicationRow
                    v-else
                    :case-id="caseId"
                    :user-id="userId"
                    :initial="medication as any"
                    @removed="removeMedication"
                />
            </template>
            <button
                type="button"
                class="flex items-center gap-1 text-sm font-bold text-[#0b2942]"
                @click="addMedication"
            >
                <Plus class="size-4" /> Add medicine
            </button>
        </div>
    </section>
</template>
