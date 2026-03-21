<?php

declare(strict_types=1);

namespace Respect\Config;

use DateTime;

class AutowireAllOptional
{
    public function __construct(public DateTime|null $a = null, public DateTime|null $b = null)
    {
    }
}
