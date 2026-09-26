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

type InvestigationPayload = SyncedSection & {
    id: string;
    test_name: string | null;
    result_type: string | null;
    result_value: string | null;
    unit: string | null;
    unit_not_stated: boolean;
    reference_range: string | null;
    reference_range_not_provided: boolean;
    reported_flag: string | null;
    observed_on: string | null;
    observed_at_time: string | null;
    interpretation: string | null;
};

const props = defineProps<{
    caseId: string;
    userId: number;
    initial: InvestigationPayload;
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
} = useSectionSync<InvestigationPayload>({
    userId: props.userId,
    resourceId: props.initial.id,
    sectionKey: 'investigations',
    endpoint: `/student/cases/${props.caseId}/investigations/${props.initial.id}`,
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
        `/student/cases/${props.caseId}/investigations/${props.initial.id}`,
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
            investigation: InvestigationPayload;
        };
        await adoptServerSnapshot(body.investigation);
        deleteConflict.value = true;
    }
}
</script>

<template>
    <div
        class="rounded-2xl border border-slate-200 p-4 dark:border-slate-700"
        :data-test="`investigation-row-${initial.id}`"
    >
        <p
            v-if="deleteConflict"
            data-test="investigation-delete-conflict"
            class="mb-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300"
        >
            This row changed on the server after this device last saw it. The
            latest version is shown — review it, then remove again.
        </p>
        <div class="flex items-center justify-between gap-2">
            <label class="flex-1 text-sm">
                <span class="sr-only">Test name</span>
                <input
                    v-model="payload.test_name"
                    type="text"
                    maxlength="120"
                    placeholder="Test name"
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
                aria-label="Remove investigation"
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
                <span class="sr-only">Result type</span>
                <select
                    v-model="payload.result_type"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    @change="edit"
                >
                    <option value="numeric">Numeric</option>
                    <option value="qualitative">Qualitative</option>
                    <option value="narrative">Narrative/report</option>
                </select>
            </label>
            <label class="text-sm">
                <span class="sr-only">Result value</span>
                <input
                    v-model="payload.result_value"
                    type="text"
                    maxlength="255"
                    placeholder="Result"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    @input="edit"
                />
            </label>
            <label class="text-sm">
                <span class="sr-only">Case date</span>
                <input
                    v-model="payload.observed_on"
                    type="date"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    @input="edit"
                />
            </label>
            <label class="text-sm">
                <span class="sr-only">Time (optional)</span>
                <input
                    v-model="payload.observed_at_time"
                    type="time"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    @input="edit"
                />
            </label>
        </div>

        <div class="mt-2 grid grid-cols-2 gap-2">
            <label class="text-sm">
                <span class="mb-1 block text-xs text-slate-500">Unit</span>
                <input
                    v-model="payload.unit"
                    type="text"
                    maxlength="20"
                    :disabled="payload.unit_not_stated"
                    data-test="investigation-unit"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-900"
                    @input="edit"
                />
            </label>
            <label class="flex items-center gap-1.5 self-end text-xs">
                <input
                    v-model="payload.unit_not_stated"
                    type="checkbox"
                    data-test="investigation-unit-not-stated"
                    @change="edit"
                />
                Unit not stated
            </label>
            <label class="text-sm">
                <span class="mb-1 block text-xs text-slate-500"
                    >Reference range</span
                >
                <input
                    v-model="payload.reference_range"
                    type="text"
                    maxlength="120"
                    :disabled="payload.reference_range_not_provided"
                    data-test="investigation-reference-range"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-900"
                    @input="edit"
                />
            </label>
            <label class="flex items-center gap-1.5 self-end text-xs">
                <input
                    v-model="payload.reference_range_not_provided"
                    type="checkbox"
                    data-test="investigation-reference-range-not-provided"
                    @change="edit"
                />
                Reference range not provided
            </label>
        </div>

        <label class="mt-2 block text-sm">
            <span class="mb-1 block text-xs text-slate-500"
                >Hospital-reported flag</span
            >
            <select
                v-model="payload.reported_flag"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                @change="edit"
            >
                <option :value="null">Not stated</option>
                <option value="low">Low</option>
                <option value="normal">Normal</option>
                <option value="high">High</option>
                <option value="critical">Critical</option>
            </select>
        </label>

        <label class="mt-2 block text-sm">
            <span class="mb-1 block text-xs text-slate-500"
                >Student clinical interpretation (optional)</span
            >
            <textarea
                v-model="payload.interpretation"
                rows="2"
                maxlength="2000"
                data-test="investigation-interpretation"
                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                @input="edit"
            />
            <DeidentificationNotice :text="payload.interpretation" />
        </label>

        <section
            v-if="conflict"
            data-test="investigation-conflict"
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
