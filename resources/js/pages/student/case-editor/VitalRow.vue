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
import {
    useSectionSync,
    type SyncedSection,
} from '@/composables/useSectionSync';

type VitalPayload = SyncedSection & {
    id: string;
    observation_type: string | null;
    value_numeric: string | null;
    value_text: string | null;
    value_systolic: number | null;
    value_diastolic: number | null;
    unit: string | null;
    observed_on: string | null;
    observed_at_time: string | null;
    source: string | null;
    note: string | null;
};

const props = defineProps<{
    caseId: string;
    userId: number;
    initial: VitalPayload;
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
} = useSectionSync<VitalPayload>({
    userId: props.userId,
    resourceId: props.initial.id,
    sectionKey: 'vitals',
    endpoint: `/student/cases/${props.caseId}/vitals/${props.initial.id}`,
    initialPayload: props.initial,
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
        `/student/cases/${props.caseId}/vitals/${props.initial.id}`,
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
        const body = (await response.json()) as { vital: VitalPayload };
        await adoptServerSnapshot(body.vital);
        deleteConflict.value = true;
    }
}
</script>

<template>
    <div
        class="rounded-2xl border border-slate-200 p-4 dark:border-slate-700"
        :data-test="`vital-row-${initial.id}`"
    >
        <p
            v-if="deleteConflict"
            data-test="vital-delete-conflict"
            class="mb-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300"
        >
            This row changed on the server after this device last saw it. The
            latest version is shown — review it, then remove again.
        </p>
        <div class="flex items-center justify-between gap-2">
            <label class="flex-1 text-sm">
                <span class="sr-only">Observation type</span>
                <select
                    v-model="payload.observation_type"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    @change="edit"
                >
                    <option :value="null">Select observation…</option>
                    <option value="blood_pressure">Blood pressure</option>
                    <option value="pulse">Pulse/heart rate</option>
                    <option value="respiratory_rate">Respiratory rate</option>
                    <option value="temperature">Temperature</option>
                    <option value="oxygen_saturation">
                        Oxygen saturation (SpO2)
                    </option>
                    <option value="weight">Weight</option>
                    <option value="height">Height</option>
                    <option value="blood_glucose">Blood glucose</option>
                </select>
            </label>
            <component
                :is="statusIcon"
                class="size-4 shrink-0 text-slate-400"
                :class="state === 'saving' ? 'animate-spin' : ''"
            />
            <button
                type="button"
                aria-label="Remove vital"
                :disabled="!online"
                class="rounded-xl border px-2 py-2 disabled:opacity-40"
                :title="!online ? 'Reconnect to remove this row' : ''"
                @click="remove"
            >
                <Trash2 class="size-4" />
            </button>
        </div>

        <div
            v-if="payload.observation_type === 'blood_pressure'"
            class="mt-2 grid grid-cols-2 gap-2"
        >
            <label class="text-sm">
                <span class="mb-1 block text-xs text-slate-500">Systolic</span>
                <input
                    v-model.number="payload.value_systolic"
                    type="number"
                    min="40"
                    max="300"
                    data-test="vital-systolic"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    @input="edit"
                />
            </label>
            <label class="text-sm">
                <span class="mb-1 block text-xs text-slate-500">Diastolic</span>
                <input
                    v-model.number="payload.value_diastolic"
                    type="number"
                    min="20"
                    max="200"
                    data-test="vital-diastolic"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    @input="edit"
                />
            </label>
        </div>
        <label v-else class="mt-2 block text-sm">
            <span class="mb-1 block text-xs text-slate-500">Value</span>
            <input
                v-model="payload.value_numeric"
                type="text"
                data-test="vital-value"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                @input="edit"
            />
        </label>

        <div class="mt-2 grid grid-cols-2 gap-2">
            <label class="text-sm">
                <span class="mb-1 block text-xs text-slate-500">Unit</span>
                <input
                    v-model="payload.unit"
                    type="text"
                    maxlength="20"
                    data-test="vital-unit"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    @input="edit"
                />
            </label>
            <label class="text-sm">
                <span class="mb-1 block text-xs text-slate-500">Case date</span>
                <input
                    v-model="payload.observed_on"
                    type="date"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    @input="edit"
                />
            </label>
            <label class="text-sm">
                <span class="mb-1 block text-xs text-slate-500"
                    >Time (optional)</span
                >
                <input
                    v-model="payload.observed_at_time"
                    type="time"
                    data-test="vital-time"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    @input="edit"
                />
            </label>
            <label class="text-sm">
                <span class="mb-1 block text-xs text-slate-500">Source</span>
                <input
                    v-model="payload.source"
                    type="text"
                    maxlength="60"
                    data-test="vital-source"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    @input="edit"
                />
            </label>
        </div>
        <label class="mt-2 block text-sm">
            <span class="mb-1 block text-xs text-slate-500">Note</span>
            <input
                v-model="payload.note"
                type="text"
                maxlength="1000"
                data-test="vital-note"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                @input="edit"
            />
        </label>

        <section
            v-if="conflict"
            data-test="vital-conflict"
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
