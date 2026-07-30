<?php

namespace App\Enums;

enum RunStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Published = 'published';
    case Failed = 'failed';
    case Aborted = 'aborted';

    /**
     * Determine whether the run is still in progress.
     */
    public function isActive(): bool
    {
        return in_array($this, [self::Queued, self::Running], true);
    }
}
