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
use Vivutio\Contracts\Access\Verb;

/**
 * Six verbs and no seventh. A module declares which of them a concern
 * supports; it cannot add one, because a matrix whose columns differ from one
 * module to the next is one nobody can read across.
 */
final class VerbTest extends TestCase
{
    public function testThereAreExactlySixInTheOrderTheMatrixDrawsThem(): void
    {
        self::assertSame(
            ['read', 'record', 'manage', 'configure', 'delete', 'export'],
            array_map(static fn (Verb $verb): string => $verb->value, Verb::cases()),
        );
    }

    #[DataProvider('everyVerb')]
    public function testEveryVerbHasAColumnHeadAndASentenceSayingWhatItAllows(Verb $verb): void
    {
        self::assertNotSame('', trim($verb->label()));
        self::assertNotSame('', trim($verb->meaning()));
        self::assertStringEndsWith('.', $verb->meaning());
    }

    public function testNoTwoVerbsShareAColumnHeadOrASentence(): void
    {
        $labels = array_map(static fn (Verb $verb): string => $verb->label(), Verb::cases());
        $meanings = array_map(static fn (Verb $verb): string => $verb->meaning(), Verb::cases());

        self::assertSame($labels, array_values(array_unique($labels)));
        self::assertSame($meanings, array_values(array_unique($meanings)));
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
}
