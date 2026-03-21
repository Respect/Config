<?php

declare(strict_types=1);

namespace Respect\Config;

class StaticNonObjectReturn
{
    public bool $ready = true;

    public static function init(): string
    {
        return 'not_an_object';
    }
}
