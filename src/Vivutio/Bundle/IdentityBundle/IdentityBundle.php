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

namespace Vivutio\Bundle\IdentityBundle;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * The home of the organization an installation serves and of the people in
 * it: users, tiers and positions, the permissions every package declares, and
 * the voters that decide each of them.
 */
final class IdentityBundle extends AbstractBundle
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
