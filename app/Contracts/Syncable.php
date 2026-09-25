<?php

namespace App\Contracts;

interface Syncable
{
    public function getLockVersion(string $sectionKey): int;
    public function getInstitutionId(): string;

    /** @param array<string, mixed> $attributes */
    public function applySyncedAttributes(string $sectionKey, array $attributes, int $newLockVersion): void;
}
