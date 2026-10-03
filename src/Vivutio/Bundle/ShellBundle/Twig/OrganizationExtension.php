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

namespace Vivutio\Bundle\ShellBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Vivutio\Contracts\Settings\OrganizationIdentity;
use Vivutio\Contracts\Settings\OrganizationIdentitySourceInterface;

/**
 * `organization()`: whose installation this is, for the frame to draw its
 * name, or null until it is recorded.
 *
 * @see https://twig.symfony.com/doc/3.x/advanced.html#functions
 */
final class OrganizationExtension extends AbstractExtension
{
    public function __construct(
        private readonly OrganizationIdentitySourceInterface $source,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('organization', $this->organization(...)),
        ];
    }

    public function organization(): ?OrganizationIdentity
    {
        return $this->source->identity();
    }
}
