<?php

namespace App\Enums;

enum AccountStatus: string
{
    case Disconnected = 'disconnected';
    case Connected = 'connected';
    case Expired = 'expired';

    /**
     * Determine whether the account may start a publishing run.
     */
    public function isPublishable(): bool
    {
        return $this === self::Connected;
    }
}
