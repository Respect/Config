<?php

declare(strict_types=1);

namespace Respect\Config;

class AutowireTypedConsumer
{
    public function __construct(public AutowireDependency $dep)
    {
    }
}
