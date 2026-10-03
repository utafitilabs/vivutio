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

namespace Vivutio\Contracts\Tests\Access;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vivutio\Contracts\Access\Grant;
use Vivutio\Contracts\Access\Verb;

/**
 * One spelling of a pair, because four places write it: a position stores it,
 * a route names it, a control is drawn on it and the build's tests match the
 * three. Two of them disagreeing about where the dot goes is a power enforced
 * that nobody granted.
 */
final class GrantTest extends TestCase
{
    public function testAPairIsWrittenConcernDotVerb(): void
    {
        self::assertSame('positions.configure', (string) Grant::of('positions', Verb::Configure));
    }

    public function testAConcernKeyMayCarryUnderscoresBecauseTheVerbIsTheLastSegment(): void
    {
        $grant = Grant::parse('personal_details.read');

        self::assertSame('personal_details', $grant->concern);
        self::assertSame(Verb::Read, $grant->verb);
    }

    #[DataProvider('everyVerb')]
    public function testEveryVerbRoundTrips(Verb $verb): void
    {
        $grant = Grant::of('offices', $verb);

        self::assertTrue($grant->equals(Grant::parse((string) $grant)));
    }

    /**
     * @return iterable<string, array{Verb}>
     */
    public static function everyVerb(): iterable
    {
        foreach (Verb::cases() as $verb) {
            yield $verb->value => [$verb];
        }
    }

    public function testTwoPairsAreTheSameWhenBothHalvesAre(): void
    {
        self::assertTrue(Grant::of('offices', Verb::Read)->equals(Grant::of('offices', Verb::Read)));
        self::assertFalse(Grant::of('offices', Verb::Read)->equals(Grant::of('offices', Verb::Delete)));
        self::assertFalse(Grant::of('offices', Verb::Read)->equals(Grant::of('positions', Verb::Read)));
    }

    public function testAStringWithNoVerbIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/names no verb/');

        Grant::parse('offices');
    }

    public function testAStringEndingInSomethingThatIsNotOneOfTheSixIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/no verb this product has/');

        Grant::parse('offices.approve');
    }

    public function testAConcernWithADotIsRefusedBecauseThePairWouldBeAmbiguous(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/ambiguous/');

        Grant::of('personal.details', Verb::Read);
    }

    #[DataProvider('keysThatAreNotSlugs')]
    public function testAConcernKeyIsALowercaseSlug(string $key): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Grant::of($key, Verb::Read);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function keysThatAreNotSlugs(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase' => ['Offices'];
        yield 'hyphen' => ['personal-details'];
        yield 'leading underscore' => ['_offices'];
        yield 'trailing underscore' => ['offices_'];
        yield 'two underscores together' => ['personal__details'];
        yield 'space' => ['personal details'];
    }

    /**
     * A pair left behind by an uninstalled module is read, never crashed on:
     * it can still be shown and still be revoked.
     */
    public function testAnUnreadablePairCanBeAskedAboutWithoutThrowing(): void
    {
        self::assertNull(Grant::tryParse('leftover_from_a_module'));
        self::assertNotNull(Grant::tryParse('offices.manage'));
    }
}
