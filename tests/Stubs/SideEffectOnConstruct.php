<?php

declare(strict_types=1);

namespace Respect\Config;

class SideEffectOnConstruct
{
    public function __construct()
    {
        $GLOBALS['_SIDE_EFFECT_'] = true;
    }
}
