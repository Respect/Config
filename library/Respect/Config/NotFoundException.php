<?php

namespace Respect\Config;

use Psr\Container as Interop;

class NotFoundException extends InvalidArgumentException implements Interop\NotFoundExceptionInterface
{
}

