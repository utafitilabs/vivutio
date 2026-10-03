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

/**
 * How to call one route: what the authority table needs to send it as anybody.
 *
 * A route without a probe cannot be called by the table, so it cannot be
 * known who may open it; the table refuses to run until every route has one.
 */
final readonly class Probe
{
    /**
     * @param string               $route  the route's name
     * @param string               $method the method the request is sent with
     * @param string               $path   the address, with real identifiers in it
     * @param array<string, mixed> $body   the form fields a write sends
     */
    public function __construct(
        public string $route,
        public string $method,
        public string $path,
        public array $body = [],
    ) {
    }
}
