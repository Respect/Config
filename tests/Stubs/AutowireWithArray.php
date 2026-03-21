<?php

declare(strict_types=1);

namespace Respect\Config;

class AutowireWithArray
{
    /** @param array<string> $paths */
    public function __construct(public array $paths)
    {
    }
}
