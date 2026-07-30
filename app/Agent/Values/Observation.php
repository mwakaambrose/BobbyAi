<?php

namespace App\Agent\Values;

final readonly class Observation
{
    /**
     * @param  PageElement[]  $elements
     */
    public function __construct(
        public string $url,
        public string $title,
        public ?string $screenshot,
        public array $elements,
    ) {}

    /**
     * @param  array{url?: string, title?: string, screenshot?: string|null, elements?: array<int, array<string, mixed>>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            url: (string) ($data['url'] ?? ''),
            title: (string) ($data['title'] ?? ''),
            screenshot: $data['screenshot'] ?? null,
            elements: array_map(PageElement::fromArray(...), $data['elements'] ?? []),
        );
    }

    /**
     * Find the element carrying the given ref.
     */
    public function element(int $ref): ?PageElement
    {
        foreach ($this->elements as $element) {
            if ($element->ref === $ref) {
                return $element;
            }
        }

        return null;
    }

    /**
     * Get the host of the currently loaded page.
     */
    public function host(): ?string
    {
        return parse_url($this->url, PHP_URL_HOST) ?: null;
    }

    /**
     * Render the observation as the textual half of the prompt.
     */
    public function toPromptText(): string
    {
        $lines = array_map(fn (PageElement $element): string => $element->toPromptLine(), $this->elements);

        return implode("\n", [
            'URL: '.$this->url,
            'TITLE: '.$this->title,
            'ELEMENTS:',
            ...$lines,
        ]);
    }
}
