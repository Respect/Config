<?php

namespace Respect\Config;

use Psr\Container as Interop;

class InvalidArgumentException extends \InvalidArgumentException implements Interop\ContainerExceptionInterface
{
}

