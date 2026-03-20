<?php

declare(strict_types=1);

namespace Respect\Config;

use Psr\Container\ContainerInterface;
use ReflectionNamedType;

use function array_key_exists;
use function end;
use function key;

class Autowire extends Instantiator
{
    protected ContainerInterface|null $container = null;

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    /** @inheritDoc */
    protected function cleanupParams(array $params): array
    {
        $constructor = $this->reflection()->getConstructor();
        if ($constructor && $this->container) {
            foreach ($constructor->getParameters() as $param) {
                $name = $param->getName();
                if (array_key_exists($name, $this->params)) {
                    $params[$name] = $this->lazyLoad($params[$name] ?? null);
                    continue;
                }

                $type = $param->getType();
                if (
                    !($type instanceof ReflectionNamedType) || $type->isBuiltin()
                    || !$this->container->has($type->getName())
                ) {
                    continue;
                }

                $params[$name] = $this->container->get($type->getName());
            }

            while (end($params) === null && ($key = key($params)) !== null) {
                unset($params[$key]);
            }

            return $params;
        }

        return parent::cleanupParams($params);
    }
}
