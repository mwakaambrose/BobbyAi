<?php

namespace App\Agent\Values;

final readonly class PageElement
{
    /**
     * @param  array<string, bool>  $state
     */
    public function __construct(
        public int $ref,
        public string $role,
        public string $name,
        public array $state = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            ref: (int) ($data['ref'] ?? 0),
            role: (string) ($data['role'] ?? 'generic'),
            name: (string) ($data['name'] ?? ''),
            state: is_array($data['state'] ?? null) ? $data['state'] : [],
        );
    }

    /**
     * Determine whether the element cannot currently be interacted with.
     */
    public function isDisabled(): bool
    {
        return (bool) ($this->state['disabled'] ?? false);
    }

    /**
     * Render the element as a single prompt line.
     */
    public function toPromptLine(): string
    {
        return '['.$this->ref.'] '.$this->role.' "'.$this->name.'"'.($this->isDisabled() ? ' (disabled)' : '');
    }
}
