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

namespace Vivutio\Core\Tests\Core;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Vivutio\Bundle\IdentityBundle\Controller\PasswordController;
use Vivutio\Bundle\IdentityBundle\Controller\SecurityController;
use Vivutio\Bundle\IdentityBundle\Entity\Position;
use Vivutio\Bundle\IdentityBundle\Test\AuthorityTestCase;
use Vivutio\Bundle\ShellBundle\Controller\DashboardController;
use Vivutio\Core\Tests\Application\Kernel;
use Vivutio\Core\Tests\Core\Authority\CoreProbes;

/**
 * The core held to the five proofs, through the same base every module's
 * suite extends. Record a reviewed change to the table with:
 *
 *     VIVUTIO_RECORD_AUTHORITY_TABLE=1 vendor/bin/phpunit --filter CoreAuthorityTest
 */
final class CoreAuthorityTest extends AuthorityTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected static function probes(): array
    {
        return CoreProbes::all();
    }

    protected static function packageDirectory(): string
    {
        return \dirname(__DIR__, 2).'/src/Vivutio';
    }

    protected static function authorityTable(): string
    {
        return __DIR__.'/authority-table.md';
    }

    /** The position the core's probes open and configure. */
    protected function seedSubjects(EntityManagerInterface $entityManager): void
    {
        $entityManager->persist((new Position())
            ->setName('Probed seat')
            ->setGrants(['directory.read'])
            ->setUuid(Uuid::fromString(CoreProbes::POSITION_UUID)));
    }

    protected static function openRoutes(): array
    {
        return [
            SecurityController::SIGN_IN => 'A stranger has to reach the form to become anybody at all.',
            SecurityController::SIGN_OUT => 'Ending one\'s own session confers nothing, and the firewall answers it before any controller.',
            DashboardController::HOME => 'Everybody signed in lands here; dashboard.read decides whether it is the organization\'s dashboard or their own, never whether there is one.',
            DashboardController::MINE => 'One\'s own dashboard, showing nothing but what is one\'s own.',
            PasswordController::FORGOT => 'Whoever forgot their password is signed out; it answers the same for every address and sends a link only to the account\'s own.',
            PasswordController::LINK => 'It only moves the link\'s token into the visitor\'s session; what it allows is decided when the token is redeemed.',
            PasswordController::RESET => 'Opened only with a working link; who it changes is read from the token, never from the request.',
        ];
    }
}
