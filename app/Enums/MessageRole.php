<?php

namespace App\Enums;

enum MessageRole: string
{
    case User = 'user';
    case Agent = 'agent';
}
