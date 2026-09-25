import { computed, onBeforeUnmount, onMounted, ref, type Ref } from 'vue';
import {
    deleteSection,
    getSection,
    keepSectionCopy,
    putSection,
    sectionKey as buildSectionKey,
    type StoredSection,
} from '@/lib/outboxStore';

export type SyncState =
    | 'server'
    | 'unsynced'
    | 'saving'
    | 'device'
    | 'failed'
    | 'conflict';
export type SyncedSection = { lock_version: number; updated_at: string };
export type SectionSyncOptions<T extends SyncedSection> = {
    userId: number;
    resourceId: string;
    sectionKey: string;
    endpoint: string;
    initialPayload: T;
    debounceMs?: number;
    /**
     * Extra keys of T that are server-computed/read-only display data (e.g. a
     * denormalized display block), on top of the always-excluded
     * `lock_version`/`updated_at`. The server's RejectsUnknownFields trait
     * rejects any of these sent back as top-level request fields.
     */
    readonlyFields?: (keyof T)[];
};

export function useSectionSync<T extends SyncedSection>(
    options: SectionSyncOptions<T>,
) {
    const storageKey = buildSectionKey(
        options.userId,
        options.sectionKey,
        options.resourceId,
    );
    // The initial payload is often a reactive Inertia prop (a Proxy). The
    // structured clone algorithm cannot clone Proxy objects at all, even when
    // the underlying data is plain and JSON-safe — a JSON round-trip both
    // strips reactivity and produces a real deep clone for this composable's
    // always-JSON-serializable sync payloads.
    const payload = ref(
        JSON.parse(JSON.stringify(options.initialPayload)),
    ) as Ref<T>;
    const baseLockVersion = ref(options.initialPayload.lock_version);
    const operationId = ref<string | null>(null);
    const state = ref<SyncState>('server');
    const savedAt = ref(new Date(options.initialPayload.updated_at));
    const online = ref(navigator.onLine);
    const conflict = ref<{ server: T; local: T } | null>(null);
    const confirmingReplace = ref(false);
    const deviceCopyKept = ref(false);
    const validationErrors = ref<string[]>([]);
    let timer: ReturnType<typeof setTimeout> | undefined;
    let syncing = false;
    let snapshotGeneration = 0;
    const csrf = () =>
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? '';
    const draft = (): StoredSection<T> | null =>
        operationId.value
            ? {
                  key: storageKey,
                  sectionKey: options.sectionKey,
                  resourceId: options.resourceId,
                  userId: options.userId,
                  // payload.value is reactive (ref() wraps object values via
                  // reactive()); IndexedDB's put() uses the structured clone
                  // algorithm, which — like structuredClone() above — cannot
                  // clone a Proxy. Strip reactivity the same JSON-safe way.
                  payload: JSON.parse(JSON.stringify(payload.value)),
                  baseLockVersion: baseLockVersion.value,
                  clientOperationId: operationId.value,
                  updatedAt: new Date().toISOString(),
              }
            : null;
    async function edit() {
        conflict.value = null;
        confirmingReplace.value = false;
        validationErrors.value = [];
        operationId.value = crypto.randomUUID();
        state.value = 'unsynced';
        const current = draft();
        if (current) await putSection(current);
        if (!online.value) {
            state.value = 'device';
            return;
        }
        window.clearTimeout(timer);
        timer = window.setTimeout(() => void sync(), options.debounceMs ?? 700);
    }
    async function adoptServerSnapshot(server: T) {
        window.clearTimeout(timer);
        snapshotGeneration++;
        payload.value = server;
        baseLockVersion.value = server.lock_version;
        savedAt.value = new Date(server.updated_at);
        operationId.value = null;
        conflict.value = null;
        confirmingReplace.value = false;
        deviceCopyKept.value = false;
        validationErrors.value = [];
        await deleteSection(storageKey);
        state.value = 'server';
    }
    async function sync(
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
        validationErrors.value = [];
        const generation = snapshotGeneration;
        const id = operationId.value;
        const sent = payload.value;
        const version = baseLockVersion.value;
        // lock_version/updated_at are this composable's own server-authoritative
        // fields; readonlyFields covers any caller-specific ones (e.g. a display
        // block). Neither is a field the server accepts back on this endpoint.
        const excluded = new Set<keyof T>([
            'lock_version',
            'updated_at',
            ...(options.readonlyFields ?? []),
        ]);
        const editable = Object.fromEntries(
            Object.entries(sent).filter(
                ([key]) => !excluded.has(key as keyof T),
            ),
        );
        try {
            const response = await fetch(options.endpoint, {
                method: 'PUT',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                },
                body: JSON.stringify({
                    client_operation_id: id,
                    base_lock_version: version,
                    ...editable,
                    resolution,
                    confirmed,
                }),
            });
            if (generation !== snapshotGeneration) return;
            if (response.status === 409) {
                const body = (await response.json()) as { section: T };
                if (operationId.value === id) {
                    conflict.value = { server: body.section, local: sent };
                    state.value = 'conflict';
                }
                return;
            }
            if (response.status === 422) {
                const body = (await response.json()) as {
                    message?: string;
                    errors?: Record<string, string[]>;
                };
                if (operationId.value === id) {
                    validationErrors.value = body.errors
                        ? Object.values(body.errors).flat()
                        : body.message
                          ? [body.message]
                          : ['This section could not be saved.'];
                    state.value = 'failed';
                }
                return;
            }
            if (!response.ok)
                throw new Error(`Sync failed with ${response.status}`);
            const body = (await response.json()) as { section: T };
            baseLockVersion.value = body.section.lock_version;
            savedAt.value = new Date(body.section.updated_at);
            if (operationId.value === id) {
                if (
                    resolution === 'use_server' ||
                    resolution === 'keep_local_copy'
                )
                    payload.value = body.section;
                operationId.value = null;
                conflict.value = null;
                confirmingReplace.value = false;
                await deleteSection(storageKey);
                state.value = 'server';
            } else {
                const newer = draft();
                if (newer) {
                    newer.baseLockVersion = body.section.lock_version;
                    await putSection(newer);
                }
                state.value = 'unsynced';
                window.setTimeout(() => void sync(), 0);
            }
        } catch {
            if (generation === snapshotGeneration)
                state.value = online.value ? 'failed' : 'device';
        } finally {
            syncing = false;
            if (
                generation !== snapshotGeneration &&
                operationId.value &&
                online.value
            )
                window.setTimeout(() => void sync(), 0);
        }
    }
    async function resolveWithServer() {
        if (!conflict.value) return;
        operationId.value = crypto.randomUUID();
        await putSection(draft()!);
        await sync('use_server');
    }
    async function keepDeviceCopy() {
        const current = draft();
        if (!current || !conflict.value) return;
        await keepSectionCopy(current);
        deviceCopyKept.value = true;
        operationId.value = crypto.randomUUID();
        await putSection(draft()!);
        await sync('keep_local_copy');
    }
    async function replaceServer() {
        if (!conflict.value) return;
        baseLockVersion.value = conflict.value.server.lock_version;
        operationId.value = crypto.randomUUID();
        await putSection(draft()!);
        await sync('replace_server', true);
    }
    const retry = () => void sync();
    const handleOnline = () => {
        online.value = true;
        if (operationId.value && state.value !== 'conflict') void sync();
    };
    const handleOffline = () => {
        online.value = false;
        if (operationId.value) state.value = 'device';
    };
    onMounted(async () => {
        const local = await getSection<T>(storageKey);
        if (local) {
            payload.value = local.payload;
            baseLockVersion.value = local.baseLockVersion;
            operationId.value = local.clientOperationId;
            state.value = online.value ? 'unsynced' : 'device';
            if (online.value) void sync();
        }
        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);
    });
    onBeforeUnmount(() => {
        window.clearTimeout(timer);
        window.removeEventListener('online', handleOnline);
        window.removeEventListener('offline', handleOffline);
    });
    return {
        payload,
        state: computed(() => state.value),
        savedAt,
        online: computed(() => online.value),
        baseLockVersion: computed(() => baseLockVersion.value),
        conflict,
        confirmingReplace,
        deviceCopyKept,
        validationErrors,
        edit,
        resolveWithServer,
        keepDeviceCopy,
        replaceServer,
        retry,
        adoptServerSnapshot,
    };
}
