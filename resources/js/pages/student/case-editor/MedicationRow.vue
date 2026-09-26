<script setup lang="ts">
import {
    AlertTriangle,
    Check,
    CloudOff,
    FileClock,
    RefreshCw,
    Trash2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DeidentificationNotice from '@/components/DeidentificationNotice.vue';
import {
    useSectionSync,
    type SyncedSection,
} from '@/composables/useSectionSync';

type MedicationPayload = SyncedSection & {
    id: string;
    medication_context: string | null;
    generic_name: string | null;
    brand_name: string | null;
    indication: string | null;
    indication_unclear: boolean;
    dose_amount: string | null;
    dose_unit: string | null;
    dosage_form: string | null;
    route: string | null;
    frequency: string | null;
    start_reference: string | null;
    status: string | null;
    stop_reference: string | null;
    prn_indication: string | null;
    notes: string | null;
};

const FREQUENCY_OPTIONS: { value: string; label: string }[] = [
    { value: 'OD', label: 'Once daily (OD)' },
    { value: 'BD', label: 'Twice daily (BD)' },
    { value: 'TDS', label: 'Three times daily (TDS)' },
    { value: 'QID', label: 'Four times daily (QID)' },
    { value: 'HS', label: 'At bedtime (HS)' },
    { value: 'STAT', label: 'Immediately, once (STAT)' },
];

const props = defineProps<{
    caseId: string;
    userId: number;
    initial: MedicationPayload;
}>();
const emit = defineEmits<{ removed: [id: string] }>();

const {
    payload,
    state,
    savedAt,
    edit,
    online,
    baseLockVersion,
    conflict,
    resolveWithServer,
    keepDeviceCopy,
    replaceServer,
    retry,
    confirmingReplace,
    adoptServerSnapshot,
} = useSectionSync<MedicationPayload>({
    userId: props.userId,
    resourceId: props.initial.id,
    sectionKey: 'medications',
    endpoint: `/student/cases/${props.caseId}/medications/${props.initial.id}`,
    initialPayload: props.initial,
    readonlyFields: ['id'],
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
        })[state.value],
);

const deleteConflict = ref(false);
watch(savedAt, () => {
    deleteConflict.value = false;
});

async function remove() {
    const response = await fetch(
        `/student/cases/${props.caseId}/medications/${props.initial.id}`,
        {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    document.querySelector<HTMLMetaElement>(
                        'meta[name="csrf-token"]',
                    )?.content ?? '',
            },
            body: JSON.stringify({ base_lock_version: baseLockVersion.value }),
        },
    );
    if (response.ok) {
        emit('removed', props.initial.id);
        return;
    }
    if (response.status === 409) {
        const body = (await response.json()) as {
            medication: MedicationPayload;
        };
        await adoptServerSnapshot(body.medication);
        deleteConflict.value = true;
    }
}
</script>

<template>
    <div
        class="rounded-2xl border border-slate-200 p-4 dark:border-slate-700"
        :data-test="`medication-row-${initial.id}`"
    >
        <p
            v-if="deleteConflict"
            data-test="medication-delete-conflict"
            class="mb-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300"
        >
            This row changed on the server after this device last saw it. The
            latest version is shown — review it, then remove again.
        </p>
        <div class="flex items-center justify-between gap-2">
            <label class="flex-1 text-sm">
                <span class="sr-only">Generic name</span>
                <input
                    v-model="payload.generic_name"
                    type="text"
                    maxlength="120"
                    placeholder="Generic name"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    @input="edit"
                />
            </label>
            <component
                :is="statusIcon"
                class="size-4 shrink-0 text-slate-400"
                :class="state === 'saving' ? 'animate-spin' : ''"
            />
            <button
                type="button"
                aria-label="Remove medication"
                :disabled="!online"
                class="rounded-xl border px-2 py-2 disabled:opacity-40"
                :title="!online ? 'Reconnect to remove this row' : ''"
                @click="remove"
            >
                <Trash2 class="size-4" />
            </button>
        </div>

        <div class="mt-2 grid grid-cols-2 gap-2">
            <label class="text-sm">
                <span class="mb-1 block text-xs text-slate-500"
                    >Brand name (optional)</span
                >
                <input
                    v-model="payload.brand_name"
                    type="text"
                    maxlength="120"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    @input="edit"
                />
            </label>
            <label class="text-sm">
                <span class="mb-1 block text-xs text-slate-500"
                    >Medication context</span
                >
                <select
                    v-model="payload.medication_context"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    @change="edit"
                >
                    <option :value="null">Select…</option>
                    <option value="chart">Chart (current)</option>
                    <option value="history">History (prior)</option>
                </select>
            </label>
        </div>

        <label class="mt-2 block text-sm">
            <span class="mb-1 block text-xs text-slate-500">Indication</span>
            <input
                v-model="payload.indication"
                type="text"
                maxlength="255"
                :disabled="payload.indication_unclear"
                data-test="medication-indication"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-900"
                @input="edit"
            />
        </label>
        <label class="mt-1 flex items-center gap-1.5 text-xs">
            <input
                v-model="payload.indication_unclear"
                type="checkbox"
                data-test="medication-indication-unclear"
                @change="edit"
            />
            Indication unclear
        </label>

        <div class="mt-2 grid grid-cols-3 gap-2">
            <label class="text-sm">
                <span class="mb-1 block text-xs text-slate-500">Dose</span>
                <input
                    v-model="payload.dose_amount"
                    type="text"
                    maxlength="30"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    @input="edit"
                />
            </label>
            <label class="text-sm">
                <span class="mb-1 block text-xs text-slate-500"
                    >Dosage form</span
                >
                <input
                    v-model="payload.dosage_form"
                    type="text"
                    maxlength="30"
                    placeholder="Tablet, injection…"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    @input="edit"
                />
            </label>
            <label class="text-sm">
                <span class="mb-1 block text-xs text-slate-500">Route</span>
                <input
                    v-model="payload.route"
                    type="text"
                    maxlength="30"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    @input="edit"
                />
            </label>
        </div>

        <label class="mt-2 block text-sm">
            <span class="mb-1 block text-xs text-slate-500">Frequency</span>
            <select
                v-model="payload.frequency"
                data-test="medication-frequency"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                @change="edit"
            >
                <option :value="null">Select…</option>
                <option
                    v-for="option in FREQUENCY_OPTIONS"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
                <option value="OTHER">Other</option>
            </select>
        </label>

        <div class="mt-2 grid grid-cols-2 gap-2">
            <label class="text-sm">
                <span class="mb-1 block text-xs text-slate-500"
                    >Start day/date (optional)</span
                >
                <input
                    v-model="payload.start_reference"
                    type="text"
                    maxlength="30"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    @input="edit"
                />
            </label>
            <label class="text-sm">
                <span class="mb-1 block text-xs text-slate-500">Status</span>
                <select
                    v-model="payload.status"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    @change="edit"
                >
                    <option value="active">Active</option>
                    <option value="stopped">Stopped</option>
                    <option value="on_hold">On hold</option>
                    <option value="completed">Completed</option>
                    <option value="prn">PRN</option>
                </select>
            </label>
        </div>

        <label
            v-if="
                payload.status === 'stopped' || payload.status === 'completed'
            "
            class="mt-2 block text-sm"
        >
            <span class="mb-1 block text-xs text-slate-500">Stop day/date</span>
            <input
                v-model="payload.stop_reference"
                type="text"
                maxlength="30"
                data-test="medication-stop-reference"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                @input="edit"
            />
        </label>
        <label v-if="payload.status === 'prn'" class="mt-2 block text-sm">
            <span class="mb-1 block text-xs text-slate-500"
                >PRN indication</span
            >
            <input
                v-model="payload.prn_indication"
                type="text"
                maxlength="120"
                data-test="medication-prn-indication"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                @input="edit"
            />
        </label>

        <label class="mt-2 block text-sm">
            <span class="mb-1 block text-xs text-slate-500"
                >Administration instructions/notes</span
            >
            <textarea
                v-model="payload.notes"
                rows="2"
                maxlength="1000"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                @input="edit"
            />
            <DeidentificationNotice :text="payload.notes" />
        </label>

        <section
            v-if="conflict"
            data-test="medication-conflict"
            class="mt-3 rounded-xl border border-rose-200 bg-white p-3 dark:border-rose-900 dark:bg-slate-900"
        >
            <p class="text-xs font-bold text-rose-700">
                The server changed after this device began editing.
            </p>
            <div class="mt-2 grid gap-1.5">
                <button
                    type="button"
                    class="rounded-lg border px-2 py-1.5 text-left text-xs font-bold"
                    @click="resolveWithServer"
                >
                    Use server version
                </button>
                <button
                    type="button"
                    class="rounded-lg border px-2 py-1.5 text-left text-xs font-bold"
                    @click="keepDeviceCopy"
                >
                    Keep local draft as a copy
                </button>
                <button
                    v-if="!confirmingReplace"
                    type="button"
                    class="rounded-lg border border-rose-200 px-2 py-1.5 text-left text-xs font-bold text-rose-700"
                    @click="confirmingReplace = true"
                >
                    Replace server version
                </button>
                <button
                    v-else
                    type="button"
                    class="rounded-lg bg-rose-700 px-2 py-1.5 text-xs font-bold text-white"
                    @click="replaceServer"
                >
                    Yes, replace it
                </button>
            </div>
        </section>
        <button
            v-if="state === 'failed'"
            type="button"
            class="mt-2 rounded-lg bg-[#0b2942] px-3 py-1.5 text-xs font-bold text-white"
            @click="retry"
        >
            Retry
        </button>
    </div>
</template>
