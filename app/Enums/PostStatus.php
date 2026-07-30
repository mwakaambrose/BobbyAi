<?php

namespace App\Enums;

enum PostStatus: string
{
    case Brief = 'brief';
    case Draft = 'draft';
    case Approved = 'approved';
    case Publishing = 'publishing';
    case Published = 'published';
    case Failed = 'failed';

    /**
     * Get the ordered list of board columns.
     *
     * @return array<int, self>
     */
    public static function board(): array
    {
        return [self::Brief, self::Draft, self::Approved, self::Publishing, self::Published, self::Failed];
    }

    /**
     * Get the human readable label for the status.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }
}
