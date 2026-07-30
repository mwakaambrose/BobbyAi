<?php

namespace App\Agent\Browser;

final readonly class SessionHandle
{
    public function __construct(public string $id) {}
}
