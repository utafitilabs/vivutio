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

namespace Vivutio\Bundle\ShellBundle\Tests\Unit\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Twig\Environment;
use Twig\Extension\CoreExtension;
use Twig\Loader\ArrayLoader;
use Vivutio\Bundle\ShellBundle\EventListener\OrganizationTimeZoneListener;
use Vivutio\Contracts\Settings\OrganizationIdentity;
use Vivutio\Contracts\Settings\OrganizationIdentitySourceInterface;

/**
 * Every time a page shows is in the organization's time zone, whichever page
 * and package draws it: a step taken at 22:31 UTC reads 01:31 the next day in
 * Dar es Salaam. Until the organization is recorded, times stay as the
 * installation runs.
 */
final class OrganizationTimeZoneListenerTest extends TestCase
{
    public function testTimesAreShownInTheOrganizationsTimeZone(): void
    {
        $twig = $this->twig();
        (new OrganizationTimeZoneListener($twig, self::source(new OrganizationIdentity('Vivutio Camps', timeZone: 'Africa/Dar_es_Salaam'))))->onRequest(self::event());

        self::assertSame('6 Oct 2026, 01:31', $twig->render('time'));
    }

    public function testWithoutAnOrganizationTimesStayAsTheInstallationRuns(): void
    {
        $twig = $this->twig();
        (new OrganizationTimeZoneListener($twig, self::source(null)))->onRequest(self::event());

        self::assertSame('5 Oct 2026, 22:31', $twig->render('time'));
    }

    private function twig(): Environment
    {
        $twig = new Environment(new ArrayLoader(['time' => "{{ '2026-10-05T22:31:00+00:00'|date('j M Y, H:i') }}"]));
        $twig->getExtension(CoreExtension::class)->setTimezone('UTC');

        return $twig;
    }

    private static function source(?OrganizationIdentity $identity): OrganizationIdentitySourceInterface
    {
        return new class($identity) implements OrganizationIdentitySourceInterface {
            public function __construct(private ?OrganizationIdentity $identity)
            {
            }

            public function identity(): ?OrganizationIdentity
            {
                return $this->identity;
            }
        };
    }

    private static function event(): RequestEvent
    {
        $kernel = new class implements HttpKernelInterface {
            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                return new Response();
            }
        };

        return new RequestEvent($kernel, new Request(), HttpKernelInterface::MAIN_REQUEST);
    }
}
