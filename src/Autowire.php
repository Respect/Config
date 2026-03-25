<?php

declare(strict_types=1);

namespace Respect\Config;

use Psr\Container\ContainerInterface;
use Respect\Parameter\Resolver;

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
            foreach ($params as $name => $value) {
                if ($value instanceof Ref) {
                    $params[$name] = $container->get($value->id);
                } else {
                    $this->propagateContainer($value);
                    $params[$name] = $this->lazyLoad($value);
                }
            }

            return $this->stripTrailingNulls((new Resolver($container))->resolveNamed($constructor, $params));
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
