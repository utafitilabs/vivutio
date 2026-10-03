<?php

declare(strict_types=1);

/*
 * This file is part of the vivutio core.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Vivutio\Core\Tests\Core\Authority;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * What every route checks, read from its controller's #[IsGranted], on the
 * class and on the method: what the framework's own listener enforces.
 *
 * @see vendor/symfony/security-http/EventListener/IsGrantedAttributeListener.php
 */
final class RouteGates
{
    /**
     * @return array<string, list<string>> route name to the attributes it checks
     */
    public static function of(RouterInterface $router): array
    {
        $gates = [];
        foreach ($router->getRouteCollection()->all() as $name => $route) {
            $controller = $route->getDefault('_controller');
            if (!\is_string($controller)) {
                throw new \LogicException(\sprintf('The route "%s" names no controller.', $name));
            }

            [$class, $method] = str_contains($controller, '::') ? explode('::', $controller, 2) : [$controller, '__invoke'];
            if (!class_exists($class)) {
                throw new \LogicException(\sprintf('The route "%s" names a controller that does not exist: %s.', $name, $class));
            }

            $reflection = new \ReflectionMethod($class, $method);
            $attributes = [];
            foreach ([...$reflection->getDeclaringClass()->getAttributes(IsGranted::class), ...$reflection->getAttributes(IsGranted::class)] as $attribute) {
                $checked = $attribute->newInstance()->attribute;
                if (!\is_string($checked)) {
                    throw new \LogicException(\sprintf('The route "%s" checks an expression. Name an attribute a voter answers instead, so the route can be walked.', $name));
                }
                $attributes[] = $checked;
            }

            $gates[$name] = $attributes;
        }

        return $gates;
    }
}
