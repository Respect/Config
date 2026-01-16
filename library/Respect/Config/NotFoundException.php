<?php

namespace Respect\Config;

use Psr\Container\NotFoundExceptionInterface as BaseNotFoundException;

class NotFoundException extends \Exception implements BaseNotFoundException
{
}