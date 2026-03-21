<?php

declare(strict_types=1);

namespace Respect\Config;

use DateTime;

class StaticFactoryStub
{
    private function __construct()
    {
    }

    public static function factory(): DateTime
    {
        return new DateTime();
    }
}
