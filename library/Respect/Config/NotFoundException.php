<?php

namespace Respect\Config;

use Interop\Container\Exception\NotFoundException as BaseNotFoundException;

class NotFoundException extends \Exception implements BaseNotFoundException
{
}