<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Check,
    Cloud,
    CloudOff,
    Copy,
    FileClock,
    RefreshCw,
    ShieldCheck,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import {
    deleteCaseDraft,
    draftKey,
    getCaseDraft,
    keepCaseDraftCopy,
    putCaseDraft,
    type StoredCaseDraft,
} from '@/lib/caseDraftStore';

type Note = {
    id: string;
    case_id: string;
    content: string;
    lock_version: number;
    updated_at: string;
};

type SaveState =
    | 'server'
    | 'unsynced'
    | 'saving'
    | 'device'
    | 'failed'
    | 'conflict';

const props = defineProps<{ note: Note }>();
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Student home', href: '/student' },
            { title: 'Sync experiment', href: '/student/sync-spike' },
        ],
    },
});

const page = usePage();
const userId = page.props.auth.user.id;
const storageKey = draftKey(userId, props.note.id);
const content = ref(props.note.content);
const baseLockVersion = ref(props.note.lock_version);
const operationId = ref<string | null>(null);
const state = ref<SaveState>('server');
const savedAt = ref(new Date(props.note.updated_at));
const online = ref(navigator.onLine);
const conflict = ref<{ server: Note; local: string } | null>(null);
const confirmingReplace = ref(false);
const deviceCopyKept = ref(false);
let debounceTimer: ReturnType<typeof setTimeout> | undefined;
let syncing = false;

const statePresentation = computed(() => {
    const states = {
        saving: {
            label: 'Saving…',
            detail: 'Sending encrypted session data',
            icon: RefreshCw,
            tone: 'blue',
        },
        server: {
            label: 'Saved on server',
            detail: `Saved at ${savedAt.value.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`,
            icon: Check,
            tone: 'green',
        },
        device: {
            label: 'Saved on this device',
            detail: 'Reconnect or retry when ready',
            icon: CloudOff,
            tone: 'amber',
        },
        unsynced: {
            label: 'Unsynced changes',
            detail: 'A device copy is safe',
            icon: FileClock,
            tone: 'amber',
        },
        failed: {
            label: 'Sync failed — Retry',
            detail: 'Your device copy is still safe',
            icon: AlertTriangle,
            tone: 'red',
        },
        conflict: {
            label: 'Conflict — Review changes',
            detail: 'Nothing has been overwritten',
            icon: AlertTriangle,
            tone: 'red',
        },
    } as const;
    return states[state.value];
});

const statusClasses = computed(
    () =>
        ({
            green: 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-100',
            amber: 'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-100',
            red: 'border-rose-200 bg-rose-50 text-rose-950 dark:border-rose-800 dark:bg-rose-950/50 dark:text-rose-100',
            blue: 'border-sky-200 bg-sky-50 text-sky-950 dark:border-sky-800 dark:bg-sky-950/50 dark:text-sky-100',
        })[statePresentation.value.tone],
);

function csrfToken() {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? ''
    );
}

function currentDraft(): StoredCaseDraft | null {
    if (!operationId.value) return null;
    return {
        key: storageKey,
        noteId: props.note.id,
        userId,
        content: content.value,
        baseLockVersion: baseLockVersion.value,
        clientOperationId: operationId.value,
        updatedAt: new Date().toISOString(),
    };
}

async function editNote() {
    conflict.value = null;
    confirmingReplace.value = false;
    operationId.value = crypto.randomUUID();
    state.value = 'unsynced';
    const draft = currentDraft();
    if (draft) await putCaseDraft(draft);

    if (!online.value) {
        state.value = 'device';
        return;
    }

    window.clearTimeout(debounceTimer);
    debounceTimer = window.setTimeout(() => void syncDraft(), 700);
}

async function syncDraft(
    resolution?: 'use_server' | 'keep_local_copy' | 'replace_server',
    confirmed = false,
) {
    if (syncing || !operationId.value) return;
    if (!online.value) {
        state.value = 'device';
        return;
    }

    syncing = true;
    state.value = 'saving';
    const sentOperation = operationId.value;
    const sentContent = content.value;
    const sentBaseVersion = baseLockVersion.value;

    try {
        const response = await fetch(`/student/sync-spike/${props.note.id}`, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                client_operation_id: sentOperation,
                base_lock_version: sentBaseVersion,
                content: sentContent,
                resolution,
                confirmed,
            }),
        });
        const payload = (await response.json()) as { note: Note };

        if (response.status === 409) {
            if (operationId.value === sentOperation) {
                conflict.value = { server: payload.note, local: sentContent };
                state.value = 'conflict';
            }
            return;
        }
        if (!response.ok)
            throw new Error(`Sync failed with ${response.status}`);

        baseLockVersion.value = payload.note.lock_version;
        savedAt.value = new Date(payload.note.updated_at);

        if (operationId.value === sentOperation) {
            if (
                resolution === 'use_server' ||
                resolution === 'keep_local_copy'
            ) {
                content.value = payload.note.content;
            }
            operationId.value = null;
            conflict.value = null;
            confirmingReplace.value = false;
            await deleteCaseDraft(storageKey);
            state.value = 'server';
        } else {
            const newerDraft = currentDraft();
            if (newerDraft) {
                newerDraft.baseLockVersion = payload.note.lock_version;
                await putCaseDraft(newerDraft);
            }
            state.value = 'unsynced';
            window.setTimeout(() => void syncDraft(), 0);
        }
    } catch {
        state.value = online.value ? 'failed' : 'device';
    } finally {
        syncing = false;
    }
}

async function resolveWithServer() {
    if (!conflict.value) return;
    operationId.value = crypto.randomUUID();
    await putCaseDraft(currentDraft()!);
    await syncDraft('use_server');
}

async function keepDeviceCopy() {
    const draft = currentDraft();
    if (!draft || !conflict.value) return;
    await keepCaseDraftCopy(draft);
    deviceCopyKept.value = true;
    operationId.value = crypto.randomUUID();
    await putCaseDraft(currentDraft()!);
    await syncDraft('keep_local_copy');
}

async function replaceServer() {
    if (!conflict.value) return;
    baseLockVersion.value = conflict.value.server.lock_version;
    operationId.value = crypto.randomUUID();
    await putCaseDraft(currentDraft()!);
    await syncDraft('replace_server', true);
}

function handleOnline() {
    online.value = true;
    if (operationId.value && state.value !== 'conflict') void syncDraft();
}

function handleOffline() {
    online.value = false;
    if (operationId.value) state.value = 'device';
}

onMounted(async () => {
    const local = await getCaseDraft(storageKey);
    if (local) {
        content.value = local.content;
        baseLockVersion.value = local.baseLockVersion;
        operationId.value = local.clientOperationId;
        state.value = online.value ? 'unsynced' : 'device';
        if (online.value) void syncDraft();
    }
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);
});

onBeforeUnmount(() => {
    window.clearTimeout(debounceTimer);
    window.removeEventListener('online', handleOnline);
    window.removeEventListener('offline', handleOffline);
});
</script>

<template>
    <Head title="Case draft sync experiment" />
    <main
        class="min-h-[calc(100vh-4rem)] bg-[radial-gradient(circle_at_top_right,rgba(201,164,59,0.10),transparent_32%)] px-4 py-6 pb-28 sm:px-6 lg:px-10 lg:py-10"
    >
        <div class="mx-auto max-w-6xl">
            <header
                class="mb-7 grid gap-5 lg:grid-cols-[1fr_auto] lg:items-end"
            >
                <div>
                    <div
                        class="mb-3 flex flex-wrap items-center gap-2 text-xs font-bold tracking-[0.16em] text-[#9b761c] uppercase"
                    >
                        <span>SYNC-SPIKE-01</span
                        ><span aria-hidden="true">/</span
                        ><span>Case {{ note.case_id.slice(-6) }}</span>
                    </div>
                    <h1
                        class="font-display max-w-3xl text-3xl leading-tight text-[#0b2942] sm:text-5xl dark:text-white"
                    >
                        A note that remembers, even when the ward Wi-Fi does
                        not.
                    </h1>
                    <p
                        class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 sm:text-base dark:text-slate-300"
                    >
                        This is a de-identified technical experiment. Do not
                        enter names, record numbers, dates of birth, phone
                        numbers, or addresses.
                    </p>
                </div>
                <div
                    class="flex items-center gap-2 rounded-full border bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm dark:bg-slate-900 dark:text-slate-200"
                >
                    <component :is="online ? Cloud : CloudOff" class="size-4" />
                    {{ online ? 'Connection available' : 'Working offline' }}
                </div>
            </header>

            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <section
                    class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-[0_24px_70px_-45px_rgba(11,41,66,0.55)] dark:border-slate-700 dark:bg-slate-900"
                >
                    <div
                        class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800"
                    >
                        <div>
                            <p
                                class="text-xs font-bold tracking-widest text-slate-400 uppercase"
                            >
                                Experimental section
                            </p>
                            <h2
                                class="font-display mt-1 text-xl text-[#0b2942] dark:text-white"
                            >
                                Case Draft Note
                            </h2>
                        </div>
                        <span
                            class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-500 dark:bg-slate-800"
                            >Version {{ baseLockVersion }}</span
                        >
                    </div>
                    <label for="case-note" class="sr-only"
                        >De-identified case draft note</label
                    >
                    <textarea
                        id="case-note"
                        v-model="content"
                        data-test="case-note"
                        rows="18"
                        maxlength="50000"
                        placeholder="Record one de-identified clinical observation here…"
                        class="min-h-[22rem] w-full resize-y bg-transparent px-5 py-5 text-base leading-7 text-slate-800 outline-none placeholder:text-slate-300 sm:px-7 sm:py-6 dark:text-slate-100 dark:placeholder:text-slate-600"
                        @input="editNote"
                    />
                    <div
                        class="flex items-center justify-between border-t border-slate-100 px-5 py-3 text-xs text-slate-400 dark:border-slate-800"
                    >
                        <span>Device-first autosave</span
                        ><span
                            >{{ content.length.toLocaleString() }} /
                            50,000</span
                        >
                    </div>
                </section>

                <aside
                    class="space-y-4 lg:sticky lg:top-6 lg:self-start"
                    aria-live="polite"
                >
                    <section
                        data-test="save-status"
                        class="rounded-2xl border p-4 shadow-sm transition-colors"
                        :class="statusClasses"
                    >
                        <div class="flex items-start gap-3">
                            <component
                                :is="statePresentation.icon"
                                class="mt-0.5 size-5 shrink-0"
                                :class="
                                    state === 'saving' ? 'animate-spin' : ''
                                "
                            />
                            <div class="min-w-0">
                                <p class="font-bold">
                                    {{ statePresentation.label }}
                                </p>
                                <p class="mt-1 text-xs opacity-75">
                                    {{ statePresentation.detail }}
                                </p>
                            </div>
                        </div>
                        <button
                            v-if="state === 'failed'"
                            type="button"
                            class="mt-4 w-full rounded-xl bg-[#0b2942] px-4 py-2.5 text-sm font-bold text-white"
                            @click="syncDraft()"
                        >
                            Retry
                        </button>
                    </section>

                    <section
                        v-if="conflict"
                        data-test="conflict-panel"
                        class="rounded-2xl border border-rose-200 bg-white p-4 shadow-lg dark:border-rose-900 dark:bg-slate-900"
                    >
                        <h2
                            class="font-display text-xl text-[#0b2942] dark:text-white"
                        >
                            Review this section
                        </h2>
                        <p class="mt-1 text-xs leading-5 text-slate-500">
                            The server changed after this device began editing.
                            Compare both versions before deciding.
                        </p>
                        <div class="mt-4 space-y-3">
                            <div
                                class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800"
                            >
                                <p
                                    class="text-[0.65rem] font-bold tracking-widest text-slate-400 uppercase"
                                >
                                    On server · v{{
                                        conflict.server.lock_version
                                    }}
                                </p>
                                <p
                                    class="mt-2 line-clamp-4 text-sm whitespace-pre-wrap text-slate-700 dark:text-slate-200"
                                >
                                    {{
                                        conflict.server.content || 'Empty note'
                                    }}
                                </p>
                            </div>
                            <div
                                class="rounded-xl bg-amber-50 p-3 dark:bg-amber-950/40"
                            >
                                <p
                                    class="text-[0.65rem] font-bold tracking-widest text-amber-700 uppercase"
                                >
                                    On this device
                                </p>
                                <p
                                    class="mt-2 line-clamp-4 text-sm whitespace-pre-wrap text-slate-700 dark:text-slate-200"
                                >
                                    {{ conflict.local || 'Empty note' }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 grid gap-2">
                            <button
                                type="button"
                                class="rounded-xl border px-3 py-2.5 text-left text-sm font-bold hover:bg-slate-50 dark:hover:bg-slate-800"
                                @click="resolveWithServer"
                            >
                                <Cloud class="mr-2 inline size-4" />Use server
                                version
                            </button>
                            <button
                                type="button"
                                class="rounded-xl border px-3 py-2.5 text-left text-sm font-bold hover:bg-slate-50 dark:hover:bg-slate-800"
                                @click="keepDeviceCopy"
                            >
                                <Copy class="mr-2 inline size-4" />Keep local
                                draft as a copy
                            </button>
                            <button
                                v-if="!confirmingReplace"
                                type="button"
                                class="rounded-xl border border-rose-200 px-3 py-2.5 text-left text-sm font-bold text-rose-700 hover:bg-rose-50"
                                @click="confirmingReplace = true"
                            >
                                <AlertTriangle
                                    class="mr-2 inline size-4"
                                />Replace server version
                            </button>
                            <div
                                v-else
                                class="rounded-xl border border-rose-300 bg-rose-50 p-3"
                            >
                                <p class="text-xs leading-5 text-rose-900">
                                    This replaces the newer server text. The
                                    action will be recorded in the audit log.
                                </p>
                                <div class="mt-3 flex gap-2">
                                    <button
                                        type="button"
                                        class="rounded-lg bg-rose-700 px-3 py-2 text-xs font-bold text-white"
                                        @click="replaceServer"
                                    >
                                        Yes, replace it</button
                                    ><button
                                        type="button"
                                        class="px-3 py-2 text-xs font-bold text-rose-900"
                                        @click="confirmingReplace = false"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        v-if="deviceCopyKept"
                        class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950"
                    >
                        <Copy class="mr-2 inline size-4" /><strong
                            >Device copy kept.</strong
                        >
                        It will be cleared when you log out.
                    </section>
                    <section
                        class="rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                    >
                        <div class="flex gap-3">
                            <ShieldCheck
                                class="size-5 shrink-0 text-emerald-600"
                            />
                            <div>
                                <p
                                    class="font-bold text-[#0b2942] dark:text-white"
                                >
                                    Privacy boundary
                                </p>
                                <p class="mt-1 text-xs leading-5">
                                    Drafts stay in this browser until synced or
                                    logout. This spike makes no permanent
                                    offline-storage guarantee.
                                </p>
                            </div>
                        </div>
                    </section>
                </aside>
            </div>
        </div>
    </main>
</template>
