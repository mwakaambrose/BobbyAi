<?php

namespace App\Agent\Values;

use App\Models\BrandProfile;

/**
 * Everything a draft is conditioned on, gathered into one argument so the
 * composer's signature stops growing with each new input.
 */
final class DraftContext
{
    public function __construct(
        public readonly string $brief,
        public readonly ?BrandProfile $brand = null,
        public readonly ?string $revisionOf = null,
        public readonly ?NarrativeContext $narrative = null,
    ) {}

    /**
     * Get the brand's banned terms, if any.
     *
     * @return array<int, string>
     */
    public function bannedTerms(): array
    {
        return $this->brand->banned_terms ?? [];
    }
}
