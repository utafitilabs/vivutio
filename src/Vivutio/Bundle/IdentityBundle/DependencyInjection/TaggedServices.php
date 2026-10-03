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

namespace Vivutio\Bundle\IdentityBundle\DependencyInjection;

/**
 * Every service carrying one tag, collected so a specification can walk them:
 * the voters an installation has, or every declaration of concerns.
 */
final readonly class TaggedServices
{
    /**
     * @param iterable<object> $services
     */
    public function __construct(
        private iterable $services,
    ) {
    }

    /**
     * @return list<object>
     */
    public function all(): array
    {
        $all = [];
        foreach ($this->services as $service) {
            $all[] = $service;
        }

        return $all;
    }
}
