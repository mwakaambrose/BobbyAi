<?php

namespace App\Enums;

enum StepPhase: string
{
    case Observe = 'observe';
    case Think = 'think';
    case Gate = 'gate';
    case Act = 'act';
    case Verify = 'verify';
}
