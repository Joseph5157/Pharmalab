<script setup lang="ts">
import {
    Trash2,
    RefreshCw,
    Check,
    CloudOff,
    FileClock,
    AlertTriangle,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import {
    useSectionSync,
    type SyncedSection,
} from '@/composables/useSectionSync';
import DeidentificationNotice from '@/components/DeidentificationNotice.vue';

type ActivityPayload = SyncedSection & {
    id: string;
    activity_type: 'intervention' | 'monitoring';
    status: string | null;
    details: Record<string, unknown> | null;
};

const props = defineProps<{
    caseId: string;
    userId: number;
    initial: ActivityPayload;
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
} = useSectionSync<ActivityPayload>({
    userId: props.userId,
    caseId: props.caseId,
    resourceId: props.initial.id,
    sectionKey: 'clinical_activities',
    endpoint: `/student/cases/${props.caseId}/clinical-activities/${props.initial.id}`,
    initialPayload: props.initial,
    readonlyFields: ['id', 'activity_type'],
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

function detail(key: string): string {
    return (payload.value.details?.[key] as string) ?? '';
}
function updateDetail(key: string, value: unknown) {
    payload.value.details = { ...payload.value.details, [key]: value };
    edit();
}

const deleteConflict = ref(false);
// Snapshot adoption changes savedAt before setting the warning; a later
// successful edit changes it again and dismisses that warning.
watch(savedAt, () => {
    deleteConflict.value = false;
});

async function remove() {
    const response = await fetch(
        `/student/cases/${props.caseId}/clinical-activities/${props.initial.id}`,
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
        const body = (await response.json()) as { activity: ActivityPayload };
        await adoptServerSnapshot(body.activity);
        deleteConflict.value = true;
    }
}
</script>

<template>
    <div
        class="rounded-2xl border border-slate-200 p-4 dark:border-slate-700"
        :data-test="`activity-row-${initial.id}`"
    >
        <div class="mb-2 flex items-center justify-end gap-2">
            <component
                :is="statusIcon"
                class="size-4 shrink-0 text-slate-400"
                :class="state === 'saving' ? 'animate-spin' : ''"
            />
            <button
                type="button"
                aria-label="Remove activity"
                :disabled="!online"
                class="rounded-xl border px-2 py-2 disabled:opacity-40"
                :title="!online ? 'Reconnect to remove this row' : ''"
                @click="remove"
            >
                <Trash2 class="size-4" />
            </button>
        </div>

        <p
            v-if="deleteConflict"
            data-test="activity-delete-conflict"
            class="mb-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300"
        >
            This row changed on the server after this device last saw it. The
            latest version is shown — review it, then remove again.
        </p>

        <template v-if="initial.activity_type === 'intervention'">
            <label class="mb-2 block text-sm">
                <span class="mb-1 block text-xs text-slate-500">Problem</span>
                <textarea
                    :value="detail('problem')"
                    rows="2"
                    maxlength="1000"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    @input="
                        updateDetail(
                            'problem',
                            ($event.target as HTMLTextAreaElement).value,
                        )
                    "
                />
            </label>
            <label class="mb-2 block text-sm">
                <span class="mb-1 block text-xs text-slate-500"
                    >Recommendation</span
                >
                <textarea
                    :value="detail('recommendation')"
                    rows="2"
                    maxlength="1000"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    @input="
                        updateDetail(
                            'recommendation',
                            ($event.target as HTMLTextAreaElement).value,
                        )
                    "
                />
                <DeidentificationNotice :text="detail('recommendation')" />
            </label>
            <div class="grid grid-cols-2 gap-2">
                <label class="text-sm">
                    <span class="mb-1 block text-xs text-slate-500"
                        >Recipient</span
                    >
                    <input
                        :value="detail('recipient')"
                        type="text"
                        maxlength="120"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                        @input="
                            updateDetail(
                                'recipient',
                                ($event.target as HTMLInputElement).value,
                            )
                        "
                    />
                </label>
                <label class="text-sm">
                    <span class="mb-1 block text-xs text-slate-500"
                        >Communication method</span
                    >
                    <input
                        :value="detail('communication_method')"
                        type="text"
                        maxlength="60"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                        @input="
                            updateDetail(
                                'communication_method',
                                ($event.target as HTMLInputElement).value,
                            )
                        "
                    />
                </label>
                <label class="text-sm">
                    <span class="mb-1 block text-xs text-slate-500"
                        >Case date</span
                    >
                    <input
                        :value="detail('case_date')"
                        type="date"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                        @input="
                            updateDetail(
                                'case_date',
                                ($event.target as HTMLInputElement).value,
                            )
                        "
                    />
                </label>
                <label class="text-sm">
                    <span class="mb-1 block text-xs text-slate-500"
                        >Outcome</span
                    >
                    <select
                        :value="detail('outcome')"
                        class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                        @change="
                            updateDetail(
                                'outcome',
                                ($event.target as HTMLSelectElement).value,
                            )
                        "
                    >
                        <option value="">Outcome…</option>
                        <option value="accepted">Accepted</option>
                        <option value="partially_accepted">
                            Partially accepted
                        </option>
                        <option value="not_accepted">Not accepted</option>
                        <option value="pending">Pending</option>
                        <option value="not_communicated">
                            Not communicated
                        </option>
                    </select>
                </label>
            </div>
            <label class="mt-2 block text-sm">
                <span class="mb-1 block text-xs text-slate-500">Follow-up</span>
                <textarea
                    :value="detail('follow_up')"
                    rows="2"
                    maxlength="1000"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    @input="
                        updateDetail(
                            'follow_up',
                            ($event.target as HTMLTextAreaElement).value,
                        )
                    "
                />
            </label>
        </template>

        <template v-else>
            <label class="mb-2 block text-sm">
                <span class="mb-1 block text-xs text-slate-500">Parameter</span>
                <input
                    :value="detail('parameter')"
                    type="text"
                    maxlength="255"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    @input="
                        updateDetail(
                            'parameter',
                            ($event.target as HTMLInputElement).value,
                        )
                    "
                />
            </label>
            <label class="mb-2 block text-sm">
                <span class="mb-1 block text-xs text-slate-500">Case date</span>
                <input
                    :value="detail('observed_on')"
                    type="date"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    @input="
                        updateDetail(
                            'observed_on',
                            ($event.target as HTMLInputElement).value,
                        )
                    "
                />
            </label>
            <label class="mb-2 block text-sm">
                <span class="mb-1 block text-xs text-slate-500">Result</span>
                <textarea
                    :value="detail('result')"
                    rows="2"
                    maxlength="1000"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    @input="
                        updateDetail(
                            'result',
                            ($event.target as HTMLTextAreaElement).value,
                        )
                    "
                />
            </label>
            <label class="block text-sm">
                <span class="mb-1 block text-xs text-slate-500">Notes</span>
                <textarea
                    :value="detail('notes')"
                    rows="2"
                    maxlength="1000"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    @input="
                        updateDetail(
                            'notes',
                            ($event.target as HTMLTextAreaElement).value,
                        )
                    "
                />
                <DeidentificationNotice :text="detail('notes')" />
            </label>
        </template>

        <section
            v-if="conflict"
            data-test="activity-conflict"
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
