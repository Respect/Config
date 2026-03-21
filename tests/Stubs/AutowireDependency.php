<?php

declare(strict_types=1);

namespace Respect\Config;

class AutowireDependency
{
    public function __construct(public string $value = 'default')
    {
    }
}
