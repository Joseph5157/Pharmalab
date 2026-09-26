import { ref } from 'vue';
import {
    deleteSection,
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
            if (!response.ok) return null;
            const body = (await response.json()) as Record<string, T>;
            await deleteSection(draft.key);
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
    }

    /** Finds every queued draft for this section and this user and retries each one. */
    async function replayPending(
        onReplaced: (localId: string, row: T) => void,
    ) {
        const all = await listAllSections<Record<string, unknown>>();
        const pending = all.filter(
            (section) =>
                section.sectionKey === createSectionKey &&
                section.userId === options.userId,
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

    return {
        online,
        queueCreate,
        cancelQueuedCreate,
        replayPending,
        handleOnline,
        handleOffline,
    };
}
