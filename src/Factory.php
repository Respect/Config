<?php

declare(strict_types=1);

namespace Respect\Config;

class Factory extends Instantiator
{
    public function getInstance(bool $forceNew = false): mixed
    {
        $this->instance = null;

        return parent::getInstance(true);
    }
}
