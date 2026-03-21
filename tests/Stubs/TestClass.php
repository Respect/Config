<?php

declare(strict_types=1);

namespace Respect\Config;

use function func_num_args;

class TestClass
{
    public bool $ok = false;

    public bool $myPropertyUsed = false;

    public string $myProperty = 'foo';

    public function __construct(mixed $foo = null, public mixed $bar = null, public mixed $baz = null)
    {
        if (!$foo) {
            return;
        }

        $this->ok = true;
    }

    public function usingProperty(): void
    {
        if ($this->myProperty !== 'bar') {
            return;
        }

        $this->myPropertyUsed = true;
    }

    public function noParams(): void
    {
        if (func_num_args() !== 0) {
            return;
        }

        $this->ok = true;
    }

    public function oneParam(mixed $ok): void
    {
        if (!$ok) {
            return;
        }

        $this->ok = true;
    }

    public function twoParams(mixed $ok, mixed $ok2): void
    {
        if (!$ok || !$ok2) {
            return;
        }

        $this->ok = true;
    }
}
