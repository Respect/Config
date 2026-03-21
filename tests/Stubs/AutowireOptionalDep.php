<?php

declare(strict_types=1);

namespace Respect\Config;

use DateTime;

class AutowireOptionalDep
{
    public function __construct(public DateTime $date, public AutowireDependency|null $dep = null)
    {
    }
}
