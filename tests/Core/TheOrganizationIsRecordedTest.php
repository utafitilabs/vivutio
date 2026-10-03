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

use Vivutio\Bundle\IdentityBundle\Exception\InvalidOrganizationIdentityException;
use Vivutio\Bundle\IdentityBundle\Service\OrganizationService;
use Vivutio\Contracts\Settings\OrganizationIdentity;
use Vivutio\Contracts\Settings\OrganizationIdentitySourceInterface;
use Vivutio\Core\Tests\Application\Kernel;

/**
 * Whose installation this is: one organization, its name, the short name used
 * where the full one will not fit, its time zone and its country. The logo is
 * kept empty until files can be stored.
 */
final class TheOrganizationIsRecordedTest extends MigrationsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate();
    }

    /** A fresh installation has not said who it belongs to, and nothing invents a name. */
    public function testAFreshInstallationHasNoIdentity(): void
    {
        self::assertNull($this->source()->identity());
    }

    public function testTheIdentityIsRecordedAndReadBack(): void
    {
        $this->organizations()->record('Vivutio Camps', 'VC', 'Africa/Dar_es_Salaam', 'Tanzania');

        $identity = $this->source()->identity();

        self::assertInstanceOf(OrganizationIdentity::class, $identity);
        self::assertSame('Vivutio Camps', $identity->name);
        self::assertSame('VC', $identity->shortName);
        self::assertSame('Africa/Dar_es_Salaam', $identity->timeZone);
        self::assertSame('Tanzania', $identity->country);
        self::assertNull($identity->logo, 'no logo until files can be stored');
    }

    /** One installation, one organization: recording again changes it, never adds a second. */
    public function testThereIsOnlyEverOneOrganization(): void
    {
        $this->organizations()->record('Vivutio Camps', null, null, null);
        $this->organizations()->record('Vivutio Camps and Lodges', 'VCL', 'Africa/Nairobi', 'Kenya');

        self::assertSame('Vivutio Camps and Lodges', $this->source()->identity()?->name);
        self::assertEquals(1, $this->connection->fetchOne('SELECT COUNT(*) FROM identity_organization'));
    }

    public function testEmptyFieldsAreRecordedAsNotSet(): void
    {
        $this->organizations()->record('  Vivutio Camps  ', '  ', '', null);

        $identity = $this->source()->identity();
        self::assertNotNull($identity);
        self::assertSame('Vivutio Camps', $identity->name);
        self::assertNull($identity->shortName);
        self::assertNull($identity->timeZone);
    }

    /**
     * @return iterable<string, array{string, ?string, ?string, string}>
     */
    public static function identitiesThatAreRefused(): iterable
    {
        yield 'no name' => ['   ', null, null, 'name'];
        yield 'a time zone nobody knows' => ['Vivutio Camps', null, 'Africa/Atlantis', 'time zone'];
        yield 'a short name longer than a short name' => ['Vivutio Camps', 'VIVUTIO CAMPS AND LODGES', null, 'short name'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('identitiesThatAreRefused')]
    public function testAnIdentityThatCannotBeDrawnIsRefusedAndNothingIsWritten(string $name, ?string $short, ?string $zone, string $field): void
    {
        try {
            $this->organizations()->record($name, $short, $zone, null);
            self::fail('it was recorded');
        } catch (InvalidOrganizationIdentityException $refusal) {
            self::assertStringContainsString($field, $refusal->getMessage());
        }

        self::assertNull($this->source()->identity());
    }

    private function organizations(): OrganizationService
    {
        $service = static::getContainer()->get(Kernel::ORGANIZATIONS);
        self::assertInstanceOf(OrganizationService::class, $service);

        return $service;
    }

    private function source(): OrganizationIdentitySourceInterface
    {
        $source = static::getContainer()->get('test_public.organization_identity');
        self::assertInstanceOf(OrganizationIdentitySourceInterface::class, $source);

        return $source;
    }
}
