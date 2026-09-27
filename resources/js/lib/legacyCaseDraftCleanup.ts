/**
 * Transitional cleanup for the retired SYNC-SPIKE-01 `CaseDraftNote` experiment.
 *
 * Students who used the spike before Slice 2C Task 10 retired it may still have a
 * device-local `pharmalab-case-drafts` IndexedDB database holding draft-note data.
 * Nothing reads or writes that database anymore, so it would otherwise sit orphaned
 * on shared devices — violating the accepted logout-privacy requirement. Deleting
 * the whole database on authenticated startup and logout actively wipes it.
 * `indexedDB.deleteDatabase()` is idempotent (a no-op once the database no longer
 * exists), so it is safe to run on every load.
 *
 * REMOVE this module (and its two call sites) after one release/pilot cleanup
 * period, once pre-retirement devices have had a chance to load the app at least
 * once. Server-side tables (`case_draft_notes`, `sync_operations`) and their rows
 * are intentionally untouched — this cleans device-local IndexedDB only.
 */
export function deleteLegacyCaseDraftDatabase(): void {
    void indexedDB.deleteDatabase('pharmalab-case-drafts');
}
