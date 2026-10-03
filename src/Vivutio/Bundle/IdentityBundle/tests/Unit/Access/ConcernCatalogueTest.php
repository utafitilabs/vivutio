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

namespace Vivutio\Bundle\IdentityBundle\Tests\Unit\Access;

use PHPUnit\Framework\TestCase;
use Vivutio\Bundle\IdentityBundle\Access\ConcernCatalogue;
use Vivutio\Contracts\Access\Concern;
use Vivutio\Contracts\Access\ConcernInterface;
use Vivutio\Contracts\Access\ConcernSourceInterface;
use Vivutio\Contracts\Access\Grant;
use Vivutio\Contracts\Access\Scope;
use Vivutio\Contracts\Access\ScopeSourceInterface;
use Vivutio\Contracts\Access\Verb;

/**
 * Everything there is to hold a permission about in an installation is what
 * its packages declared, and nothing else. The catalogue keeps no list of its
 * own, and it refuses a declaration it could not answer for.
 */
final class ConcernCatalogueTest extends TestCase
{
    public function testItGroupsEveryConcernUnderWhoeverDeclaredIt(): void
    {
        $catalogue = new ConcernCatalogue(
            [
                self::concerns('Team', self::positions(), self::personalDetails()),
                self::concerns('Notes', self::notes()),
            ],
            [self::scopes('Notes', new Scope('notebooks', 'Notebooks', 'One or more named notebooks.'))],
        );

        self::assertSame(['Team', 'Notes'], array_keys($catalogue->grouped()));
        self::assertSame(['positions', 'personal_details'], self::keys($catalogue->grouped()['Team']));
        self::assertSame(['notes'], self::keys($catalogue->grouped()['Notes']));
        self::assertSame(['positions', 'personal_details', 'notes'], self::keys($catalogue->all()));
        self::assertSame('Notes', $catalogue->declarerOf('notes'));
        self::assertNull($catalogue->declarerOf('nobody_declared_this'));
    }

    public function testAPackageThatDeclaresNothingStillHasItsGroup(): void
    {
        $catalogue = new ConcernCatalogue([self::concerns('Team')]);

        self::assertSame(['Team' => []], $catalogue->grouped());
    }

    public function testAnInstallationThatDeclaresNothingHasNoPairs(): void
    {
        $catalogue = new ConcernCatalogue();

        self::assertSame([], $catalogue->all());
        self::assertSame([], $catalogue->pairs());
    }

    public function testEveryPairIsTheConcernsDeclaredVerbsAndNoOther(): void
    {
        $catalogue = new ConcernCatalogue([self::concerns('Team', self::positions(), self::personalDetails())]);

        self::assertSame(
            ['positions.read', 'positions.configure', 'personal_details.read', 'personal_details.manage'],
            $catalogue->pairs(),
        );
    }

    public function testThePairsFollowTheFixedVerbOrderAndNotTheDeclarationOrder(): void
    {
        $backwards = new Concern('positions', 'Positions', 'The seats people hold.', [Verb::Configure, Verb::Read], [Scope::ORGANIZATION]);

        self::assertSame(
            ['positions.read', 'positions.configure'],
            (new ConcernCatalogue([self::concerns('Team', $backwards)]))->pairs(),
        );
    }

    public function testAPairIsRecognisedOnlyWhenTheConcernSupportsThatVerb(): void
    {
        $catalogue = new ConcernCatalogue([self::concerns('Team', self::positions())]);

        self::assertTrue($catalogue->has(Grant::of('positions', Verb::Read)));
        self::assertFalse($catalogue->has(Grant::of('positions', Verb::Export)));
        self::assertFalse($catalogue->has(Grant::of('nobody_declared_this', Verb::Read)));
    }

    public function testTwoPackagesDeclaringOneConcernIsRefusedAndBothAreNamed(): void
    {
        $catalogue = new ConcernCatalogue([
            self::concerns('Team', self::positions()),
            self::concerns('Notes', self::positions()),
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/"positions".*Team.*Notes/');

        $catalogue->all();
    }

    public function testItAnswersWhichModuleAConcernBelongsToAndWhetherItIsSensitive(): void
    {
        $catalogue = new ConcernCatalogue(
            [self::concerns('Team', self::personalDetails()), self::concerns('Notes', self::notes())],
            [self::scopes('Notes', new Scope('notebooks', 'Notebooks', 'One or more named notebooks.'))],
        );

        self::assertNull($catalogue->moduleOf('personal_details'));
        self::assertSame('notes', $catalogue->moduleOf('notes'));
        self::assertTrue($catalogue->isSensitive('personal_details'));
        self::assertFalse($catalogue->isSensitive('notes'));
        self::assertFalse($catalogue->isSensitive('nobody_declared_this'));
    }

    public function testATierOnlyPairIsDeclaredButNeverOfferedToAPosition(): void
    {
        $catalogue = new ConcernCatalogue([self::concerns('Team', self::positions())]);

        self::assertTrue($catalogue->isTierOnly('positions.configure'));
        self::assertFalse($catalogue->isTierOnly('positions.read'));
        self::assertFalse($catalogue->isTierOnly('not a pair'));
        self::assertFalse($catalogue->isTierOnly('nobody_declared_this.read'));

        self::assertSame(['positions.read', 'positions.configure'], $catalogue->pairs());
        self::assertSame(['positions.read'], $catalogue->positionPairs());
    }

    public function testTheCoresOwnScopesAreThereWithoutAnybodyDeclaringThem(): void
    {
        $catalogue = new ConcernCatalogue();

        self::assertSame(['organization', 'department', 'own'], self::scopeKeys($catalogue->scopes()));
        self::assertSame('Organization', $catalogue->scope(Scope::ORGANIZATION)?->label);
        self::assertNull($catalogue->scope('nobody_declared_this'));
    }

    public function testAScopeAPackageDeclaresFollowsTheCoresOwnAndNamesItsDeclarer(): void
    {
        $catalogue = new ConcernCatalogue([], [
            self::scopes('Notes', new Scope('notebooks', 'Notebooks', 'One or more named notebooks.')),
        ]);

        self::assertSame(['organization', 'department', 'own', 'notebooks'], self::scopeKeys($catalogue->scopes()));
        self::assertSame('Notes', $catalogue->scopeDeclarerOf('notebooks'));
        self::assertNull($catalogue->scopeDeclarerOf(Scope::ORGANIZATION), 'the core\'s own scopes are declared by no package');
    }

    public function testTwoPackagesDeclaringOneScopeIsRefusedAndBothAreNamed(): void
    {
        $catalogue = new ConcernCatalogue([], [
            self::scopes('Notes', new Scope('notebooks', 'Notebooks', 'One or more named notebooks.')),
            self::scopes('Journal', new Scope('notebooks', 'Books', 'Every book.')),
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/"notebooks".*Notes.*Journal/');

        $catalogue->scopes();
    }

    public function testAPackageRedeclaringOneOfTheCoresOwnScopesIsRefused(): void
    {
        $catalogue = new ConcernCatalogue([], [
            self::scopes('Notes', new Scope('department', 'Desk', 'One desk.')),
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/"department".*the core.*Notes/');

        $catalogue->scopes();
    }

    public function testAConcernNamingAScopeNobodyDeclaredIsRefusedAndItsDeclarerIsNamed(): void
    {
        $catalogue = new ConcernCatalogue([self::concerns('Notes', self::notes())]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/"notes".*Notes.*"notebooks"/');

        $catalogue->all();
    }

    private static function positions(): Concern
    {
        return new Concern(
            key: 'positions',
            label: 'Positions',
            description: 'The seats people hold and what each one grants.',
            verbs: [Verb::Read, Verb::Configure],
            scopes: [Scope::ORGANIZATION],
            tierOnly: [Verb::Configure],
        );
    }

    private static function personalDetails(): Concern
    {
        return new Concern(
            key: 'personal_details',
            label: 'Personal details',
            description: 'What is known about a person.',
            verbs: [Verb::Read, Verb::Manage],
            scopes: [Scope::ORGANIZATION, Scope::DEPARTMENT],
            sensitive: true,
        );
    }

    private static function notes(): Concern
    {
        return new Concern(
            key: 'notes',
            label: 'Notes',
            description: 'What is written in a notebook.',
            verbs: [Verb::Read, Verb::Record],
            scopes: [Scope::ORGANIZATION, 'notebooks'],
            moduleSlug: 'notes',
        );
    }

    private static function concerns(string $declaredBy, ConcernInterface ...$concerns): ConcernSourceInterface
    {
        return new readonly class($declaredBy, array_values($concerns)) implements ConcernSourceInterface {
            /**
             * @param list<ConcernInterface> $concerns
             */
            public function __construct(private string $declaredBy, private array $concerns)
            {
            }

            public function declaredBy(): string
            {
                return $this->declaredBy;
            }

            public function concerns(): iterable
            {
                return $this->concerns;
            }
        };
    }

    private static function scopes(string $declaredBy, Scope ...$scopes): ScopeSourceInterface
    {
        return new readonly class($declaredBy, array_values($scopes)) implements ScopeSourceInterface {
            /**
             * @param list<Scope> $scopes
             */
            public function __construct(private string $declaredBy, private array $scopes)
            {
            }

            public function declaredBy(): string
            {
                return $this->declaredBy;
            }

            public function scopes(): iterable
            {
                return $this->scopes;
            }
        };
    }

    /**
     * @param list<ConcernInterface> $concerns
     *
     * @return list<string>
     */
    private static function keys(array $concerns): array
    {
        return array_map(static fn (ConcernInterface $concern): string => $concern->key(), $concerns);
    }

    /**
     * @param list<Scope> $scopes
     *
     * @return list<string>
     */
    private static function scopeKeys(array $scopes): array
    {
        return array_map(static fn (Scope $scope): string => $scope->key, $scopes);
    }
}
