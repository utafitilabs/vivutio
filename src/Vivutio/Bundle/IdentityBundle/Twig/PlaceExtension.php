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

namespace Vivutio\Bundle\IdentityBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Vivutio\Bundle\IdentityBundle\Service\PlaceDirectoryService;

/**
 * `place_name(kind, id)`: what a kept place is called, wherever a posting or
 * a department's place is drawn, offices and a package's places alike.
 *
 * @see https://symfony.com/doc/current/templates.html#templates-twig-extension
 */
final class PlaceExtension extends AbstractExtension
{
    public function __construct(private readonly PlaceDirectoryService $places)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('place_name', $this->places->nameOf(...)),
        ];
    }
}
