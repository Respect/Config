<?php

declare(strict_types=1);

namespace Respect\Config;

use DateTime;

class AutowireConsumer
{
    public function __construct(public DateTime $date)
    {
    }
}
