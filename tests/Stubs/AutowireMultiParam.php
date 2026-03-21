<?php

declare(strict_types=1);

namespace Respect\Config;

use DateTime;

class AutowireMultiParam
{
    public function __construct(public DateTime $date, public AutowireDependency $dep)
    {
    }
}
