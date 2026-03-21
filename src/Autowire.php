<?php

declare(strict_types=1);

namespace Respect\Config;

use Psr\Container\ContainerInterface;
use ReflectionNamedType;

use function array_key_exists;

class Autowire extends Instantiator
{
    protected ContainerInterface|null $container = null;

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    /** @inheritDoc */
    protected function cleanupParams(array $params, bool $forConstructor = true): array
    {
        $constructor = $this->reflection()->getConstructor();
        $container = $this->container;
        if ($forConstructor && $constructor && $container) {
            foreach ($constructor->getParameters() as $param) {
                $name = $param->getName();
                if (array_key_exists($name, $this->params)) {
                    $value = $params[$name] ?? null;
                    if ($value instanceof Ref) {
                        $params[$name] = $container->get($value->id);
                    } else {
                        $this->propagateContainer($value);
                        $params[$name] = $this->lazyLoad($value);
                    }
                } else {
                    $type = $param->getType();
                    if (
                        $type instanceof ReflectionNamedType && !$type->isBuiltin()
                        && $container->has($type->getName())
                    ) {
                        $params[$name] = $container->get($type->getName());
                    }
                }
            }

            return $this->stripTrailingNulls($params);
        }

        return parent::cleanupParams($params);
    }

    protected function propagateContainer(mixed $value): void
    {
        if (!$value instanceof self || $value->container !== null || $this->container === null) {
            return;
        }

        $value->setContainer($this->container);
    }
}
