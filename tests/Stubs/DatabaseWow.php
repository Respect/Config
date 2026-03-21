<?php

declare(strict_types=1);

namespace Respect\Config;

class DatabaseWow
{
    public mixed $c;

    public function __construct(mixed $con)
    {
        $this->c = $con;
    }
}
