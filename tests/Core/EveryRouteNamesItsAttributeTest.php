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

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\CacheableVoterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Vivutio\Bundle\IdentityBundle\Access\ConcernCatalogue;
use Vivutio\Bundle\IdentityBundle\Controller\SecurityController;
use Vivutio\Bundle\IdentityBundle\Security\AccountVoter;
use Vivutio\Contracts\Access\ConcernSourceInterface;
use Vivutio\Contracts\Access\Grant;
use Vivutio\Contracts\Access\Verb;
use Vivutio\Core\Tests\Application\Fixtures\TaggedServices;
use Vivutio\Core\Tests\Application\Kernel;

/**
 * The route walk: what a route enforces and what the installation declares
 * cannot drift apart, and no question has two answers.
 *
 *   1. Every route names the attribute it checks, or is on the reviewed list
 *      of routes that need none, with the reason. A route that names nothing
 *      is a page anybody signed in can open, which must be said rather than
 *      left to be inferred from an absence.
 *   2. Every attribute is answered by exactly one voter. None is a typo or a
 *      removed package, and the page is shut for everybody with nothing
 *      saying why. Two is a second voter that can be outvoted: under the
 *      affirmative strategy its refusal would never count.
 *   3. Every pair a real package declares is checked by some route. A box in
 *      the matrix that nothing checks changes nothing when it is ticked.
 *
 * All three look like working code, which is why they are a build test.
 */
#[CoversNothing]
final class EveryRouteNamesItsAttributeTest extends KernelTestCase
{
    /**
     * The routes that check no attribute, each with the reason. Adding one
     * here is a decision about who may open a page, reviewed as one.
     */
    private const array OPEN = [
        SecurityController::SIGN_IN => 'A stranger has to reach the form to become anybody at all.',
        SecurityController::SIGN_OUT => 'Ending one\'s own session confers nothing, and the firewall answers it before any controller.',
        'test_landing' => 'This application\'s front page, standing in for an installation\'s: it only says who is signed in.',
    ];

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // A booted kernel leaves its exception handlers on the stack, and
        // PHPUnit fails a test that ends with more of them than it began with.
        while (true) {
            $previous = set_exception_handler(static fn () => null);
            restore_exception_handler();
            if (null === $previous) {
                break;
            }
            restore_exception_handler();
        }
    }

    public function testEveryRouteNamesAnAttributeOrIsOpenForAStatedReason(): void
    {
        $silent = [];
        $stale = [];

        foreach ($this->gates() as $route => $attributes) {
            $open = \array_key_exists($route, self::OPEN);

            if ([] === $attributes && !$open) {
                $silent[] = $route;
            }

            if ([] !== $attributes && $open) {
                $stale[] = $route.' (it checks '.implode(', ', $attributes).')';
            }
        }

        $missing = array_diff(array_keys(self::OPEN), array_keys($this->gates()));

        self::assertSame([], $silent, "These routes check no attribute and are not on the open list:\n".implode("\n", $silent)."\nName the attribute with #[IsGranted], or add the route to OPEN with the reason it needs none.");
        self::assertSame([], $stale, "These routes are on the open list but check an attribute; take them off it:\n".implode("\n", $stale));
        self::assertSame([], array_values($missing), "The open list names routes that do not exist:\n".implode("\n", $missing));
    }

    public function testEveryAttributeARouteChecksIsAnsweredByExactlyOneVoter(): void
    {
        $wrong = [];

        foreach ($this->gates() as $route => $attributes) {
            foreach ($attributes as $attribute) {
                $claimants = $this->claimants($attribute);
                if (1 !== \count($claimants)) {
                    $wrong[] = \sprintf('%s checks "%s", which %s', $route, $attribute, [] === $claimants ? 'no voter answers' : 'several voters answer: '.implode(', ', $claimants));
                }
            }
        }

        self::assertSame([], $wrong, implode("\n", $wrong));
    }

    /**
     * Not only the attributes a route names today: every declared pair and
     * every rule between tiers has one voter, so a page added tomorrow
     * cannot meet a question with two answers.
     */
    public function testEveryDeclaredPairAndEveryTierRuleIsAnsweredByExactlyOneVoter(): void
    {
        $attributes = [
            ...$this->catalogue()->pairs(),
            AccountVoter::ACT_ON,
            AccountVoter::CHANGE_TIER,
            AccountVoter::DEACTIVATE,
            AccountVoter::SIGN_IN_AS,
        ];

        $wrong = [];
        foreach ($attributes as $attribute) {
            $claimants = $this->claimants($attribute);
            if (1 !== \count($claimants)) {
                $wrong[] = \sprintf('"%s" is answered by %s', $attribute, [] === $claimants ? 'no voter' : implode(', ', $claimants));
            }
        }

        self::assertSame([], $wrong, implode("\n", $wrong));
    }

    /** A question nothing declares is answered by nobody, so the decision manager refuses it. */
    public function testAQuestionNothingDeclaresIsAnsweredByNoVoter(): void
    {
        self::assertSame([], $this->claimants('property_booking.record'));
        self::assertSame([], $this->claimants('notices.delete'));
    }

    /**
     * A pair a real package declares is one some route checks. The test
     * application's stand-in packages declare pairs no route checks, which is
     * why only declarations shipped under src/ are held to it.
     */
    public function testEveryPairARealPackageDeclaresIsCheckedBySomeRoute(): void
    {
        $checked = [];
        foreach ($this->gates() as $attributes) {
            $checked = [...$checked, ...$attributes];
        }

        $unchecked = [];
        foreach ($this->shippedSources() as $source) {
            foreach ($source->concerns() as $concern) {
                foreach (Verb::cases() as $verb) {
                    $pair = (string) Grant::of($concern->key(), $verb);
                    if ($concern->supports($verb) && !\in_array($pair, $checked, true)) {
                        $unchecked[] = $source->declaredBy().': '.$pair;
                    }
                }
            }
        }

        self::assertSame([], $unchecked, "These declared pairs are checked by no route, so ticking them changes nothing:\n".implode("\n", $unchecked));
    }

    /**
     * Every route by name, with the attributes its controller's
     * #[IsGranted] names, on the class and on the method.
     *
     * @return array<string, list<string>>
     */
    private function gates(): array
    {
        $router = static::getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        $gates = [];
        foreach ($router->getRouteCollection()->all() as $name => $route) {
            $controller = $route->getDefault('_controller');
            self::assertIsString($controller, $name.' names no controller');

            [$class, $method] = str_contains($controller, '::') ? explode('::', $controller, 2) : [$controller, '__invoke'];
            self::assertTrue(class_exists($class), $name.' names a controller that does not exist: '.$class);

            $reflection = new \ReflectionMethod($class, $method);
            $attributes = [];
            foreach ([...$reflection->getDeclaringClass()->getAttributes(IsGranted::class), ...$reflection->getAttributes(IsGranted::class)] as $attribute) {
                $attribute = $attribute->newInstance()->attribute;
                self::assertIsString($attribute, $name.' checks an expression; name an attribute a voter answers instead, so it can be walked.');
                $attributes[] = $attribute;
            }

            $gates[$name] = $attributes;
        }

        return $gates;
    }

    /**
     * The voters that claim an attribute. A voter that does not say which
     * attributes it answers claims every one.
     *
     * @return list<string>
     */
    private function claimants(string $attribute): array
    {
        $claimants = [];
        foreach ($this->tagged(Kernel::VOTERS) as $voter) {
            self::assertInstanceOf(VoterInterface::class, $voter);

            if (!$voter instanceof CacheableVoterInterface || $voter->supportsAttribute($attribute)) {
                $claimants[] = $voter::class;
            }
        }

        return $claimants;
    }

    /**
     * The declarations shipped by a package of the core, not by the test
     * application's stand-ins.
     *
     * @return list<ConcernSourceInterface>
     */
    private function shippedSources(): array
    {
        $src = \dirname(__DIR__, 2).'/src/';

        $shipped = [];
        foreach ($this->tagged(Kernel::CONCERN_SOURCES) as $source) {
            self::assertInstanceOf(ConcernSourceInterface::class, $source);

            if (str_starts_with((string) new \ReflectionClass($source)->getFileName(), $src)) {
                $shipped[] = $source;
            }
        }

        return $shipped;
    }

    /**
     * @return list<object>
     */
    private function tagged(string $id): array
    {
        $services = static::getContainer()->get($id);
        self::assertInstanceOf(TaggedServices::class, $services);

        return $services->all();
    }

    private function catalogue(): ConcernCatalogue
    {
        $catalogue = static::getContainer()->get(Kernel::CATALOGUE);
        self::assertInstanceOf(ConcernCatalogue::class, $catalogue);

        return $catalogue;
    }
}
