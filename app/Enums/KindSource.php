<?php

namespace App\Enums;

enum KindSource: string
{
    case Agent = 'agent';
    case User = 'user';

    /**
     * Determine whether a classifier may overwrite a kind from this source.
     *
     * A correction made by hand is final; the classifier never argues with it.
     */
    public function isOverwritable(): bool
    {
        return $this === self::Agent;
    }
}
