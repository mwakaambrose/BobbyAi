<?php

namespace App\Enums;

enum SocialPlatform: string
{
    case LinkedIn = 'linkedin';

    /**
     * Get the human readable label for the platform.
     */
    public function label(): string
    {
        return match ($this) {
            self::LinkedIn => 'LinkedIn',
        };
    }
}
