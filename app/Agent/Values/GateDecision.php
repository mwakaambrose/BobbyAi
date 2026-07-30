<?php

namespace App\Agent\Values;

use App\Enums\GateVerdict;

final readonly class GateDecision
{
    public function __construct(
        public GateVerdict $verdict,
        public string $reason = '',
    ) {}

    /**
     * Allow the proposed action.
     */
    public static function allow(): self
    {
        return new self(GateVerdict::Allowed);
    }

    /**
     * Deny the proposed action for the given reason.
     */
    public static function deny(string $reason): self
    {
        return new self(GateVerdict::Denied, $reason);
    }

    /**
     * Determine whether the action may be executed.
     */
    public function isAllowed(): bool
    {
        return $this->verdict === GateVerdict::Allowed;
    }
}
