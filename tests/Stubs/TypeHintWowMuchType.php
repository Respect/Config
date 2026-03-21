<?php

declare(strict_types=1);

namespace Respect\Config;

use DateTime;

class TypeHintWowMuchType
{
    public DateTime $d;

    public function __construct(DateTime $date)
    {
        $this->d = $date;
    }
}
