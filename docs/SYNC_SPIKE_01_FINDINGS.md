# SYNC-SPIKE-01 Findings

## Decision

The device-first, section-level sync approach is suitable for reuse in the walking skeleton, subject to the boundaries below. It prevents silent overwrite by combining an IndexedDB outbox, a stable client operation ID, and a server-side lock version.

This spike is deliberately limited to one `Case Draft Note`. It is not the clinical case schema.

## Proven flow

1. Each edit is written to IndexedDB before a network save is attempted.
2. The outbox operation retains its `client_operation_id` across manual or automatic retries.
3. The server locks the note row, checks `base_lock_version`, and increments `lock_version` only on an accepted write.
4. A duplicate operation returns its prior result without applying a second write.
5. A stale operation receives `409 Conflict` and the current server section. It cannot overwrite the server value.
6. The student can use the server section, archive the local text as a device copy, or explicitly confirm replacement of the latest server section.
7. Every conflict-resolution choice creates an append-only audit event with versions and the section key, not note content.

## Verification results

- Feature tests cover accepted save, idempotent retry, stale conflict, all resolution choices, explicit replacement audit, other-student denial, and cross-institution denial.
- A headless Chromium run at a 390 x 844 viewport covers online autosave, a failed network save, device persistence, offline refresh, later recovery, foreground reconnect sync, a two-session stale-version conflict, mobile conflict choices, and logout clearing.
- Production TypeScript compilation, frontend checks, PHP formatting, PHPStan, and the application test suite pass.

## Important boundary: offline refresh

Clinical pages are not service-worker cached in this spike. A refresh performed while fully offline therefore shows the browser's network error until connectivity returns. The IndexedDB outbox survives that refresh; after reconnecting and reopening the page, the local draft is recovered and synchronized. This proves no draft loss without introducing the explicitly deferred service-worker caching work.

## Security and privacy observations

- Server reads and writes require an authenticated, active, verified student who owns the note and belongs to its institution.
- IndexedDB keys include the authenticated user and note IDs.
- Logout clears both pending drafts and archived device copies before sending the logout request.
- Audit and sync-operation records contain identifiers, versions, outcome, and resolution metadata—not clinical narrative content.
- Seeded demo credentials are disabled when the application environment is `production`.

## Limitations retained by design

- Browser storage may be evicted; no permanent offline-storage guarantee is made.
- A cold offline page load is unavailable without a service worker.
- Background Sync is not used. Synchronization occurs only while the page is open, on reconnect, or after manual retry.
- Conflict handling is section-level. There is no automatic field or text merge.
- Submission and faculty review remain online-only and are not part of this experiment.
- Archived local copies are only a proof of preservation in IndexedDB; a full draft-management screen is deferred.

## Reuse guidance

Reuse the protocol as a shared section-sync service rather than copying editor code into each clinical form. Keep one outbox record and lock version per independently editable section, use a new operation ID for each logical content state, and preserve the rule that conflict resolution is a separate operation. Before clinical rollout, institutional policy must decide device retention duration and whether local copies may persist beyond a session.
