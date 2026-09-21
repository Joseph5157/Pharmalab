export type StoredCaseDraft = {
    key: string;
    noteId: string;
    userId: number;
    content: string;
    baseLockVersion: number;
    clientOperationId: string;
    updatedAt: string;
};

export type StoredCaseDraftCopy = StoredCaseDraft & {
    id: string;
    copiedAt: string;
};

const DATABASE_NAME = 'pharmalab-case-drafts';
const DATABASE_VERSION = 1;
let connection: Promise<IDBDatabase> | null = null;

function database(): Promise<IDBDatabase> {
    if (connection) return connection;

    connection = new Promise((resolve, reject) => {
        const request = indexedDB.open(DATABASE_NAME, DATABASE_VERSION);
        request.onupgradeneeded = () => {
            const db = request.result;
            if (!db.objectStoreNames.contains('drafts')) {
                db.createObjectStore('drafts', { keyPath: 'key' });
            }
            if (!db.objectStoreNames.contains('copies')) {
                db.createObjectStore('copies', { keyPath: 'id' });
            }
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });

    return connection;
}

function requestResult<T>(request: IDBRequest<T>): Promise<T> {
    return new Promise((resolve, reject) => {
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

export const draftKey = (userId: number, noteId: string) =>
    `${userId}:${noteId}`;

export async function getCaseDraft(key: string) {
    const db = await database();
    return requestResult<StoredCaseDraft | undefined>(
        db.transaction('drafts').objectStore('drafts').get(key),
    );
}

export async function putCaseDraft(draft: StoredCaseDraft) {
    const db = await database();
    await requestResult(
        db.transaction('drafts', 'readwrite').objectStore('drafts').put(draft),
    );
}

export async function deleteCaseDraft(key: string) {
    const db = await database();
    await requestResult(
        db.transaction('drafts', 'readwrite').objectStore('drafts').delete(key),
    );
}

export async function keepCaseDraftCopy(draft: StoredCaseDraft) {
    const db = await database();
    const copy: StoredCaseDraftCopy = {
        ...draft,
        id: crypto.randomUUID(),
        copiedAt: new Date().toISOString(),
    };
    await requestResult(
        db.transaction('copies', 'readwrite').objectStore('copies').put(copy),
    );
    return copy;
}

export async function clearCaseDraftStorage() {
    const db = await database();
    const transaction = db.transaction(['drafts', 'copies'], 'readwrite');
    transaction.objectStore('drafts').clear();
    transaction.objectStore('copies').clear();

    await new Promise<void>((resolve, reject) => {
        transaction.oncomplete = () => resolve();
        transaction.onerror = () => reject(transaction.error);
        transaction.onabort = () => reject(transaction.error);
    });

    db.close();
    connection = null;
}
