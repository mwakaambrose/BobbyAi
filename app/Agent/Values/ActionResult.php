<?php

namespace App\Agent\Values;

final readonly class ActionResult
{
    public function __construct(
        public bool $ok,
        public ?string $error = null,
    ) {}

    /**
     * Create a successful result.
     */
    public static function ok(): self
    {
        return new self(true);
    }

    /**
     * Create a failed result carrying the reason.
     */
    public static function failed(string $error): self
    {
        return new self(false, $error);
    }
}
