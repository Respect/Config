<?php

declare(strict_types=1);

namespace Respect\Config;

class MyLazyLoadedHelloWorld
{
    public function __construct(protected string $string)
    {
    }

    public function __toString(): string
    {
        return $this->string;
    }
}
