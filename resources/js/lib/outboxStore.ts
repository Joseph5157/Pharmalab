export type StoredSection<T = Record<string, unknown>> = {
    key: string;
    sectionKey: string;
    resourceId: string;
    userId: number;
    /**
     * The clinical case this draft belongs to. Section-level drafts can infer
     * this from `resourceId`, but repeatable-row creates/edits cannot (their
     * `resourceId` is a row/local id), so it is stored explicitly instead.
     * Submission-review uses it to block submitting a case while that same
     * case still has unsynced or permanently-failed work sitting in IndexedDB.
     * Optional for records written before this field existed.
     */
    caseId?: string;
    payload: T;
    baseLockVersion: number;
    clientOperationId: string;
    updatedAt: string;
    /**
     * Set once a queued row-creation POST comes back 422/409 — a rejection
     * that will never resolve by blindly resubmitting the same payload on
     * the next 'online' event, unlike a genuine network/server failure.
     * Its presence stops useRepeatableRowCreate's replayPending() from
     * retrying this draft until the user explicitly retries or discards it.
     */
    lastError?: string;
};

export type StoredSectionCopy<T = Record<string, unknown>> =
    StoredSection<T> & { id: string; copiedAt: string };

const DATABASE_NAME = 'pharmalab-section-outbox';
const DATABASE_VERSION = 1;
let connection: Promise<IDBDatabase> | null = null;

function database(): Promise<IDBDatabase> {
    if (connection) return connection;
    connection = new Promise((resolve, reject) => {
        const request = indexedDB.open(DATABASE_NAME, DATABASE_VERSION);
        request.onupgradeneeded = () => {
            const db = request.result;
            if (!db.objectStoreNames.contains('sections'))
                db.createObjectStore('sections', { keyPath: 'key' });
            if (!db.objectStoreNames.contains('copies'))
                db.createObjectStore('copies', { keyPath: 'id' });
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
    return connection;
}

function result<T>(request: IDBRequest<T>): Promise<T> {
    return new Promise((resolve, reject) => {
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

export const sectionKey = (
    userId: number,
    section: string,
    resourceId: string,
) => `${userId}:${section}:${resourceId}`;
export async function getSection<T>(key: string) {
    const db = await database();
    return result<StoredSection<T> | undefined>(
        db.transaction('sections').objectStore('sections').get(key),
    );
}
export async function putSection<T>(section: StoredSection<T>) {
    const db = await database();
    await result(
        db
            .transaction('sections', 'readwrite')
            .objectStore('sections')
            .put(section),
    );
}
export async function deleteSection(key: string) {
    const db = await database();
    await result(
        db
            .transaction('sections', 'readwrite')
            .objectStore('sections')
            .delete(key),
    );
}
export async function listAllSections<T>(): Promise<StoredSection<T>[]> {
    const db = await database();
    return result<StoredSection<T>[]>(
        db.transaction('sections').objectStore('sections').getAll(),
    );
}
/**
 * Every still-unsynced draft (pending create, pending section edit, a
 * validation failure, or a permanent 422/409 rejection) that belongs to the
 * given case. Successful syncs delete their record, so any presence means the
 * server does not yet have this device's latest work for the case.
 */
export async function listOutboxSectionsForCase<T = Record<string, unknown>>(
    caseId: string,
): Promise<StoredSection<T>[]> {
    const all = await listAllSections<T>();
    return all.filter(
        (section) =>
            section.caseId === caseId ||
            // Backwards compatibility for section-level drafts written before
            // caseId existed: their resourceId is the case id. Row-level
            // legacy drafts cannot be attributed and are ignored rather than
            // risking blocking on an unrelated case.
            (section.caseId === undefined && section.resourceId === caseId),
    );
}
export async function keepSectionCopy<T>(
    section: StoredSection<T>,
): Promise<StoredSectionCopy<T>> {
    const db = await database();
    const copy = {
        ...section,
        id: crypto.randomUUID(),
        copiedAt: new Date().toISOString(),
    };
    await result(
        db.transaction('copies', 'readwrite').objectStore('copies').put(copy),
    );
    return copy;
}
export async function clearSectionOutbox() {
    const db = await database();
    const tx = db.transaction(['sections', 'copies'], 'readwrite');
    tx.objectStore('sections').clear();
    tx.objectStore('copies').clear();
    await new Promise<void>((resolve, reject) => {
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
        tx.onabort = () => reject(tx.error);
    });
    db.close();
    connection = null;
}
