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

namespace Vivutio\Bundle\ConnectBundle;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * The home of the hub channel: one implementation of the partner channel,
 * present in every installation and used only by one that turned it on.
 *
 * Nothing outside this bundle names it. Every other package reaches a partner
 * through the channel contract, which is what keeps an installation complete
 * without the hub.
 */
final class ConnectBundle extends AbstractBundle
{
    /**
     * The class sits at the package root, beside the package's composer.json.
     * AbstractBundle assumes a class one directory down, in src/, and answers
     * with the directory above the class's own.
     *
     * @see https://symfony.com/doc/current/bundles.html — the directory
     *      structure "follows a set of conventions, but is flexible to be
     *      adjusted if needed"
     * @see vendor/symfony/dependency-injection/Kernel/AbstractBundle.php — getPath()
     */
    public function getPath(): string
    {
        return __DIR__;
    }
}
