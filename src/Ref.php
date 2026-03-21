<?php

declare(strict_types=1);

namespace Respect\Config;

class Ref
{
    public function __construct(public readonly string $id)
    {
    }
}
