<?php

declare(strict_types=1);

namespace Respect\Config;

use DateTime;

class Foo
{
    public mixed $bar = null;

    public static function hey(DateTime $date): DateTime
    {
        return $date;
    }

    public function hello(mixed $some, Bar $bar): void
    {
        $this->bar = $bar;
    }
}
