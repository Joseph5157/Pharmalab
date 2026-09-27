import { expect, test } from '@playwright/test';
import {
    classifyOutboxSections,
    type StoredSection,
} from '../../resources/js/lib/outboxStore';

function section(overrides: Partial<StoredSection>): StoredSection {
    return {
        key: 'key',
        sectionKey: 'section',
        resourceId: 'resource',
        userId: 1,
        payload: {},
        baseLockVersion: 0,
        clientOperationId: 'operation',
        updatedAt: '2026-09-27T00:00:00.000Z',
        ...overrides,
    };
}

test('matching-case outbox records block and are surfaced as case sections', () => {
    const result = classifyOutboxSections(
        [section({ key: 'matching', caseId: 'case-1' })],
        'case-1',
    );

    expect(result.caseSections.map((entry) => entry.key)).toEqual(['matching']);
    expect(result.ambiguousSections).toEqual([]);
});

test('legacy outbox records with no caseId are treated as blocking and ambiguous', () => {
    const result = classifyOutboxSections(
        [
            section({ key: 'legacy-section', resourceId: 'case-1' }),
            section({ key: 'legacy-row', resourceId: 'row-9' }),
        ],
        'case-1',
    );

    expect(result.caseSections).toEqual([]);
    expect(result.ambiguousSections.map((entry) => entry.key)).toEqual([
        'legacy-section',
        'legacy-row',
    ]);
});

test('outbox records with a different known caseId do not block', () => {
    const result = classifyOutboxSections(
        [section({ key: 'other', caseId: 'case-2' })],
        'case-1',
    );

    expect(result.caseSections).toEqual([]);
    expect(result.ambiguousSections).toEqual([]);
});

test('mixed outbox records are split without dropping or re-assigning any', () => {
    const result = classifyOutboxSections(
        [
            section({ key: 'other', caseId: 'case-2' }),
            section({ key: 'matching', caseId: 'case-1' }),
            section({ key: 'legacy' }),
        ],
        'case-1',
    );

    expect(result.caseSections.map((entry) => entry.key)).toEqual(['matching']);
    expect(result.ambiguousSections.map((entry) => entry.key)).toEqual([
        'legacy',
    ]);
});
