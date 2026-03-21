<?php

declare(strict_types=1);

namespace Respect\Config;

class Factory extends Instantiator
{
    public function shouldCache(): bool
    {
        return false;
    }
}
