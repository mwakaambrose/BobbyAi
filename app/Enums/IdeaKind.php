<?php

namespace App\Enums;

enum IdeaKind: string
{
    case Announcement = 'announcement';
    case Lesson = 'lesson';
    case Story = 'story';
    case Opinion = 'opinion';

    /**
     * Get the human readable label for the kind.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Get the values a classifier may return.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
