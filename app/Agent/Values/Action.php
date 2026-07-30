<?php

namespace App\Agent\Values;

use App\Enums\ActionType;
use InvalidArgumentException;

final readonly class Action
{
    public function __construct(
        public ActionType $type,
        public ?int $ref = null,
        public ?string $text = null,
        public ?string $key = null,
        public string $why = '',
    ) {}

    /**
     * Build an action from raw model output, validating the shape.
     *
     * @param  array{action?: string, ref?: int|string|null, text?: string|null, key?: string|null, why?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        $type = ActionType::tryFrom((string) ($data['action'] ?? ''))
            ?? throw new InvalidArgumentException('Unknown action type ['.($data['action'] ?? '').'].');

        $ref = isset($data['ref']) ? (int) $data['ref'] : null;

        if ($type->targetsElement() && $ref === null) {
            throw new InvalidArgumentException('Action ['.$type->value.'] requires a ref.');
        }

        if ($type === ActionType::Type && blank($data['text'] ?? null)) {
            throw new InvalidArgumentException('Action [type] requires text.');
        }

        if ($type === ActionType::Press && blank($data['key'] ?? null)) {
            throw new InvalidArgumentException('Action [press] requires a key.');
        }

        return new self(
            type: $type,
            ref: $ref,
            text: $data['text'] ?? null,
            key: $data['key'] ?? null,
            why: (string) ($data['why'] ?? ''),
        );
    }

    /**
     * Get a one line description used in the step history.
     */
    public function describe(): string
    {
        return match (true) {
            $this->type->targetsElement() => $this->type->value.' #'.$this->ref,
            $this->type === ActionType::Press => 'press '.$this->key,
            default => $this->type->value,
        };
    }

    /**
     * @return array{action: string, ref: int|null, text: string|null, key: string|null, why: string}
     */
    public function toArray(): array
    {
        return [
            'action' => $this->type->value,
            'ref' => $this->ref,
            'text' => $this->text,
            'key' => $this->key,
            'why' => $this->why,
        ];
    }
}
