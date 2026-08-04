<?php

namespace App\Enums;

enum MessageKind: string
{
    case Text = 'text';
    case Drafts = 'drafts';
    case Run = 'run';
    case Edit = 'edit';
    case Error = 'error';
}
