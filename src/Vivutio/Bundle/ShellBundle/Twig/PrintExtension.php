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

/**
 * What the print layout needs that a printed page cannot fetch: the house
 * typeface, carried inside the page, since the browser prints a file of its
 * own that reaches no address.
 */
final class PrintExtension extends AbstractExtension
{
    private const string TYPEFACE = __DIR__.'/../public/fonts/Figtree-normal.woff2';

    public function getFunctions(): array
    {
        return [
            new TwigFunction('print_typeface', $this->typeface(...), ['is_safe' => ['html']]),
        ];
    }

    /** The @font-face rule for Figtree, its file inside it. */
    public function typeface(): string
    {
        $font = (string) file_get_contents(self::TYPEFACE);

        return '@font-face{font-family:Figtree;src:url(data:font/woff2;base64,'.base64_encode($font).') format("woff2");font-weight:300 900}';
    }
}
