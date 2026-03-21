<?php

declare(strict_types=1);

namespace Respect\Config;

class AutowireWrapper
{
    public function __construct(public mixed $inner)
    {
    }
}
