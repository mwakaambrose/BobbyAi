<?php

namespace App\Enums;

enum ActionType: string
{
    case Click = 'click';
    case Type = 'type';
    case Press = 'press';
    case Scroll = 'scroll';
    case Wait = 'wait';
    case Finish = 'finish';
    case Abort = 'abort';

    /**
     * Determine whether the action ends the run.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Finish, self::Abort], true);
    }

    /**
     * Determine whether the action targets a specific page element.
     */
    public function targetsElement(): bool
    {
        return in_array($this, [self::Click, self::Type], true);
    }
}
