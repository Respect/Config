<?php

declare(strict_types=1);

namespace Respect\Config;

class MyLazyLoadedHelloWorldConsumer
{
    protected string $string;

    public function __construct(mixed $hello)
    {
        $this->string = $hello;
    }

    public function __toString(): string
    {
        return $this->string;
    }
}
