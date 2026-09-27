import { onBeforeUnmount, onMounted, ref } from 'vue';
import {
    deleteSection,
    getSection,
    listAllSections,
    putSection,
    sectionKey as buildSectionKey,
    type StoredSection,
} from '@/lib/outboxStore';

export type RepeatableRowCreateOptions = {
    userId: number;
    sectionKey: string;
    endpoint: string;
    responseKey: string;
    emptyPayload: () => Record<string, unknown>;
};

function csrfToken(): string {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? ''
    );
}

/**
 * A "local:" prefix marks a row that only exists in this browser's IndexedDB
 * outbox and has never reached the server. Row components use this to decide
 * whether they can safely call useSectionSync (which needs a real server id
 * to build its endpoint) or must render a pending state instead.
 */
export const LOCAL_ROW_PREFIX = 'local:';

export function useRepeatableRowCreate<T extends { id: string }>(
    options: RepeatableRowCreateOptions,
) {
    const online = ref(navigator.onLine);
    const createSectionKey = `${options.sectionKey}:create`;
    /** Keyed by localId; present only for a permanently-rejected (422/409) queued create. */
    const failedCreateErrors = ref<Record<string, string[]>>({});

    function clearFailedCreateError(localId: string) {
        if (!(localId in failedCreateErrors.value)) return;
        const { [localId]: _removed, ...rest } = failedCreateErrors.value;
        failedCreateErrors.value = rest;
    }

    async function flush(
        draft: StoredSection<Record<string, unknown>>,
    ): Promise<T | null> {
        try {
            const response = await fetch(options.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    client_operation_id: draft.clientOperationId,
                    ...draft.payload,
                }),
            });
            if (response.status === 422 || response.status === 409) {
                // Unlike a network/server failure, resubmitting the exact
                // same payload will never succeed here — mirror
                // useSectionSync's validationErrors convention instead of
                // silently retrying this draft forever on every 'online'
                // event.
                const body = (await response.json().catch(() => null)) as {
                    message?: string;
                    errors?: Record<string, string[]>;
                } | null;
                const messages = body?.errors
                    ? Object.values(body.errors).flat()
                    : [body?.message ?? 'This row could not be created.'];
                failedCreateErrors.value = {
                    ...failedCreateErrors.value,
                    [draft.resourceId]: messages,
                };
                await putSection({ ...draft, lastError: messages.join(' ') });
                return null;
            }
            // A non-422/409 failure (network error below, or another status
            // here) is transient, not a rejection of this payload — leave
            // any existing failedCreateErrors entry alone (rather than
            // clearing it, as retryFailedCreate used to do before calling
            // this) so a retry attempt that fails again for a different
            // reason doesn't strand the row with no visible error and no
            // Retry/Discard buttons.
            if (!response.ok) return null;
            const body = (await response.json()) as Record<string, T>;
            await deleteSection(draft.key);
            clearFailedCreateError(draft.resourceId);
            return body[options.responseKey];
        } catch {
            return null;
        }
    }

    /** Writes a local draft immediately and, if online, tries to sync it now. */
    async function queueCreate(): Promise<T> {
        const clientOperationId = crypto.randomUUID();
        const localId = `${LOCAL_ROW_PREFIX}${clientOperationId}`;
        const payload = options.emptyPayload();
        const draft: StoredSection<Record<string, unknown>> = {
            key: buildSectionKey(options.userId, createSectionKey, localId),
            sectionKey: createSectionKey,
            resourceId: localId,
            userId: options.userId,
            payload,
            baseLockVersion: 0,
            clientOperationId,
            updatedAt: new Date().toISOString(),
        };
        await putSection(draft);

        const optimisticRow = {
            ...payload,
            id: localId,
            lock_version: 0,
            updated_at: draft.updatedAt,
        } as unknown as T;

        if (!online.value) return optimisticRow;

        return (await flush(draft)) ?? optimisticRow;
    }

    /** Cancels a queued create that never reached the server (pure local removal, works offline). */
    async function cancelQueuedCreate(localId: string) {
        await deleteSection(
            buildSectionKey(options.userId, createSectionKey, localId),
        );
        clearFailedCreateError(localId);
    }

    /**
     * Retries a single draft the user explicitly asked to retry after a
     * 422/409. Does not clear failedCreateErrors up front — flush() itself
     * decides whether the retry's outcome should update, keep, or clear it,
     * so a retry that fails again (for any reason) never silently strands
     * the row with no visible error.
     */
    async function retryFailedCreate(localId: string): Promise<T | null> {
        const draft = await getSection<Record<string, unknown>>(
            buildSectionKey(options.userId, createSectionKey, localId),
        );
        if (!draft) return null;
        return flush(draft);
    }

    /**
     * Finds every queued draft for this section and this user and retries
     * each one — skipping drafts already marked permanently failed, so a
     * reconnect doesn't keep resubmitting a rejection that will never
     * succeed on its own.
     */
    async function replayPending(
        onReplaced: (localId: string, row: T) => void,
    ) {
        const all = await listAllSections<Record<string, unknown>>();
        const pending = all.filter(
            (section) =>
                section.sectionKey === createSectionKey &&
                section.userId === options.userId &&
                !section.lastError,
        );
        for (const draft of pending) {
            const synced = await flush(draft);
            if (synced) onReplaced(draft.resourceId, synced);
        }
    }

    function handleOnline() {
        online.value = true;
    }
    function handleOffline() {
        online.value = false;
    }

    onMounted(() => {
        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);
    });
    onBeforeUnmount(() => {
        window.removeEventListener('online', handleOnline);
        window.removeEventListener('offline', handleOffline);
    });

    return {
        online,
        failedCreateErrors,
        queueCreate,
        cancelQueuedCreate,
        retryFailedCreate,
        replayPending,
        handleOnline,
        handleOffline,
    };
}
