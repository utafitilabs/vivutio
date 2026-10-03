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

use PHPUnit\Framework\TestCase;
use Vivutio\Contracts\Access\Concern;
use Vivutio\Contracts\Access\ConcernSourceInterface;
use Vivutio\Contracts\Access\Scope;
use Vivutio\Contracts\Access\Verb;

/**
 * A concern is declared by constructing one, and a declaration that could not
 * be drawn honestly in the grants matrix does not construct. The failure then
 * lands in the declaring package's own test run and never on an
 * administrator's screen.
 */
final class ConcernTest extends TestCase
{
    public function testAConcernCarriesItsKeyLabelSentenceVerbsAndScopes(): void
    {
        $concern = new Concern(
            key: 'positions',
            label: 'Positions',
            description: 'The seats people hold and what each one grants.',
            verbs: [Verb::Read, Verb::Configure],
            scopes: [Scope::ORGANIZATION],
        );

        self::assertSame('positions', $concern->key());
        self::assertSame('Positions', $concern->label());
        self::assertSame('The seats people hold and what each one grants.', $concern->description());
        self::assertSame([Verb::Read, Verb::Configure], $concern->verbs());
        self::assertSame(['organization'], $concern->scopes());
        self::assertFalse($concern->isSensitive());
        self::assertNull($concern->ownWords());
        self::assertNull($concern->moduleSlug());
    }

    public function testAConcernAnswersWhetherItSupportsAVerbAndOffersAScope(): void
    {
        $concern = self::positions();

        self::assertTrue($concern->supports(Verb::Read));
        self::assertFalse($concern->supports(Verb::Export));
        self::assertTrue($concern->offers(Scope::ORGANIZATION));
        self::assertFalse($concern->offers(Scope::DEPARTMENT));
    }

    public function testAConcernMayOfferAScopeAPackageDeclared(): void
    {
        $concern = new Concern(
            key: 'notes',
            label: 'Notes',
            description: 'What is written in a notebook.',
            verbs: [Verb::Read, Verb::Record],
            scopes: [Scope::ORGANIZATION, 'notebooks'],
            moduleSlug: 'notes',
        );

        self::assertTrue($concern->offers('notebooks'));
        self::assertSame('notes', $concern->moduleSlug());
    }

    public function testAConcernWithoutASentenceIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/without a description/');

        new Concern('positions', 'Positions', ' ', [Verb::Read], [Scope::ORGANIZATION]);
    }

    public function testAConcernSupportingNoVerbIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/supports no verb/');

        new Concern('positions', 'Positions', 'The seats people hold.', [], [Scope::ORGANIZATION]);
    }

    public function testAConcernOfferingNoScopeIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/offers no scope/');

        new Concern('positions', 'Positions', 'The seats people hold.', [Verb::Read], []);
    }

    public function testAConcernRepeatingAVerbIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/twice/');

        new Concern('positions', 'Positions', 'The seats people hold.', [Verb::Read, Verb::Read], [Scope::ORGANIZATION]);
    }

    public function testAConcernRepeatingAScopeIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/twice/');

        new Concern('positions', 'Positions', 'The seats people hold.', [Verb::Read], [Scope::ORGANIZATION, Scope::ORGANIZATION]);
    }

    public function testAScopeNamedByAKeyThatIsNotASlugIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not a scope key/');

        new Concern('positions', 'Positions', 'The seats people hold.', [Verb::Read], ['Note Books']);
    }

    public function testAKeyThatIsNotASlugIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not a slug/');

        new Concern('Personal Details', 'Personal details', 'What is known about a person.', [Verb::Read], [Scope::ORGANIZATION]);
    }

    public function testOwnScopeWithoutThePackagesOwnWordsIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/what own means/');

        new Concern('notes', 'Notes', 'What is written in a notebook.', [Verb::Read], [Scope::OWN]);
    }

    public function testOwnWordsWithoutOwnScopeAreRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/would name nothing/');

        new Concern('notes', 'Notes', 'What is written in a notebook.', [Verb::Read], [Scope::ORGANIZATION], ownWords: 'own notes');
    }

    public function testOwnScopeComesWithThePackagesOwnWords(): void
    {
        $concern = new Concern('notes', 'Notes', 'What is written in a notebook.', [Verb::Read], [Scope::OWN], ownWords: 'own notes');

        self::assertSame('own notes', $concern->ownWords());
    }

    public function testASensitiveConcernSaysSo(): void
    {
        $concern = new Concern(
            key: 'personal_details',
            label: 'Personal details',
            description: 'What is known about a person: their address, their phone, their documents.',
            verbs: [Verb::Read, Verb::Manage],
            scopes: [Scope::ORGANIZATION, Scope::DEPARTMENT],
            sensitive: true,
        );

        self::assertTrue($concern->isSensitive());
    }

    public function testAnOrdinaryConcernHoldsNoVerbForTheTiersAlone(): void
    {
        foreach (Verb::cases() as $verb) {
            self::assertFalse(self::positions()->isTierOnly($verb));
        }
    }

    public function testAConcernNamesTheVerbsOnlyTheTiersHold(): void
    {
        $concern = new Concern(
            key: 'positions',
            label: 'Positions',
            description: 'The seats people hold and what each one grants.',
            verbs: [Verb::Read, Verb::Configure],
            scopes: [Scope::ORGANIZATION],
            tierOnly: [Verb::Configure],
        );

        self::assertTrue($concern->isTierOnly(Verb::Configure));
        self::assertFalse($concern->isTierOnly(Verb::Read));
    }

    public function testATierOnlyVerbTheConcernDoesNotSupportIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/does not support it/');

        new Concern('positions', 'Positions', 'The seats people hold.', [Verb::Read], [Scope::ORGANIZATION], tierOnly: [Verb::Configure]);
    }

    public function testTheTagIsTheOneTheCoreCollects(): void
    {
        self::assertSame('vivutio.access.concerns', ConcernSourceInterface::TAG);
    }

    private static function positions(): Concern
    {
        return new Concern(
            key: 'positions',
            label: 'Positions',
            description: 'The seats people hold and what each one grants.',
            verbs: [Verb::Read, Verb::Configure],
            scopes: [Scope::ORGANIZATION],
        );
    }
}
