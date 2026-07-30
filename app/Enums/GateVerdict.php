<?php

namespace App\Enums;

enum GateVerdict: string
{
    case Allowed = 'allowed';
    case Denied = 'denied';
}
