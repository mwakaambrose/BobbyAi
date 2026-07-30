<?php

namespace App\Agent\Values;

use App\Models\Post;

final readonly class RunContract
{
    /**
     * @param  string[]  $allowedRoles
     */
    public function __construct(
        public string $allowedHost,
        public string $approvedBody,
        public array $allowedRoles,
        public int $stepBudget,
    ) {}

    /**
     * Build the immutable contract for publishing the given approved post.
     */
    public static function forPost(Post $post): self
    {
        return new self(
            allowedHost: config('agent.linkedin.host'),
            approvedBody: (string) $post->approved_body,
            allowedRoles: ['button', 'textbox', 'link'],
            stepBudget: config('agent.step_cap'),
        );
    }

    /**
     * @param  array{allowed_host: string, approved_body: string, allowed_roles: string[], step_budget: int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            allowedHost: $data['allowed_host'],
            approvedBody: $data['approved_body'],
            allowedRoles: $data['allowed_roles'],
            stepBudget: (int) $data['step_budget'],
        );
    }

    /**
     * @return array{allowed_host: string, approved_body: string, allowed_roles: string[], step_budget: int}
     */
    public function toArray(): array
    {
        return [
            'allowed_host' => $this->allowedHost,
            'approved_body' => $this->approvedBody,
            'allowed_roles' => $this->allowedRoles,
            'step_budget' => $this->stepBudget,
        ];
    }
}
