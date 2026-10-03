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

namespace Vivutio\Bundle\IdentityBundle\Exception;

/**
 * A position was to be written with pairs no position may carry. Every one is
 * named at once, each with its reason, so whoever sent them fixes them in one
 * pass rather than one per attempt.
 */
final class UngrantablePairsException extends \DomainException
{
    /**
     * @param array<string, string> $reasons each refused pair with the reason it was refused
     */
    public function __construct(array $reasons)
    {
        $this->pairs = array_keys($reasons);

        $lines = [];
        foreach ($reasons as $pair => $reason) {
            $lines[] = \sprintf('"%s": %s', $pair, $reason);
        }

        parent::__construct('A position cannot carry these: '.implode('; ', $lines).'.');
    }

    /** @var list<string> */
    public readonly array $pairs;
}
