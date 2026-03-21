<?php

declare(strict_types=1);

namespace Respect\Config;

class PrivateConstructorClass
{
    public string $value = '';

    private function __construct(string $x)
    {
        $this->value = $x;
    }
}
