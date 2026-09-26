<?php

namespace App\Models\Concerns;

trait SyncsWithLockVersion
{
    public function getLockVersion(string $sectionKey): int
    {
        return (int) $this->getAttribute($this->lockVersionColumn($sectionKey));
    }

    public function getInstitutionId(): string
    {
        return (string) $this->institution_id;
    }

    /** @param array<string, mixed> $attributes */
    public function applySyncedAttributes(string $sectionKey, array $attributes, int $newLockVersion): void
    {
        $this->forceFill([...$attributes, $this->lockVersionColumn($sectionKey) => $newLockVersion])->save();
    }

    protected function lockVersionColumn(string $sectionKey): string
    {
        return 'lock_version';
    }
}
