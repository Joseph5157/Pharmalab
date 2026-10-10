<?php

namespace App\Services;

use App\Models\RotationCaseTarget;

/**
 * The single place that decides whether a case category belongs to a
 * rotation's configured target categories. Used by both case requests and by
 * progress counting, so the normalization rule is never redefined per call
 * site.
 */
class RotationCategoryMatcher
{
    /** @var array<string, array<string, string>> rotation id => [normalized key => canonical category] */
    private array $cache = [];

    /**
     * The rotation's configured categories, keyed by normalized key, valued
     * by the canonical casing faculty entered. Empty when the rotation has no
     * category targets, in which case case categories stay free text.
     *
     * @return array<string, string>
     */
    public function categories(string $rotationId): array
    {
        return $this->cache[$rotationId] ??= RotationCaseTarget::query()
            ->withoutGlobalScopes()
            ->where('rotation_id', $rotationId)
            ->whereNotNull('category')
            ->orderBy('category')
            ->pluck('category')
            ->mapWithKeys(fn (string $category): array => [(string) RotationCaseTarget::normalize($category) => $category])
            ->all();
    }

    /** @return list<string> */
    public function options(string $rotationId): array
    {
        return array_values($this->categories($rotationId));
    }

    /** The canonical stored casing for an input category, or null when it does not match. */
    public function canonical(string $rotationId, ?string $input): ?string
    {
        $key = RotationCaseTarget::normalize($input);

        return $key === null ? null : ($this->categories($rotationId)[$key] ?? null);
    }

    public function forget(string $rotationId): void
    {
        unset($this->cache[$rotationId]);
    }
}
