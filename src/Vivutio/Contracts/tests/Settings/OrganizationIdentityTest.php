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

namespace Vivutio\Contracts\Tests\Settings;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vivutio\Contracts\Settings\OrganizationIdentity;

final class OrganizationIdentityTest extends TestCase
{
    public function testAnOrganizationIsKnownByItsName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new OrganizationIdentity('   ');
    }

    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function zones(): iterable
    {
        yield 'east of the meridian, whole hours' => ['Africa/Dar_es_Salaam', 'UTC+3'];
        yield 'on the meridian' => ['UTC', 'UTC+0'];
        yield 'west, in winter' => ['America/New_York', 'UTC-5'];
        yield 'a half hour' => ['Asia/Kolkata', 'UTC+5:30'];
        yield 'not set' => [null, null];
        yield 'a zone PHP does not know' => ['Africa/Atlantis', null];
    }

    #[DataProvider('zones')]
    public function testTheOffsetIsDrawnBesideTheZone(?string $zone, ?string $offset): void
    {
        $identity = new OrganizationIdentity('Vivutio Camps', timeZone: $zone);

        self::assertSame($offset, $identity->utcOffset(new \DateTimeImmutable('2026-01-15 12:00:00 UTC')));
    }
}
