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

namespace Vivutio\Bundle\IdentityBundle\Test;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\CacheableVoterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Vivutio\Bundle\IdentityBundle\Access\ConcernCatalogue;
use Vivutio\Bundle\IdentityBundle\DependencyInjection\TaggedServices;
use Vivutio\Bundle\IdentityBundle\DependencyInjection\TestServicesPass;
use Vivutio\Bundle\IdentityBundle\Entity\Position;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;
use Vivutio\Bundle\IdentityBundle\Security\AccountVoter;
use Vivutio\Contracts\Access\ConcernSourceInterface;
use Vivutio\Contracts\Access\Grant;
use Vivutio\Contracts\Access\Verb;

/**
 * The five proofs of the security blueprint, over one package: the core, or a
 * module. A module's suite extends this, names its probes, its open routes and
 * the file its reviewed table is kept in, and is held to what the core is.
 *
 *   1. The route walk: every route of the package names the attribute it
 *      checks, or is open for a stated reason.
 *   2. One question, one voter: every attribute the package checks, every
 *      control it draws and every declared pair is answered by exactly one
 *      voter.
 *   3. The authority table: every probe, sent as every kind of person, gives
 *      the outcome the reviewed table records. A change fails until recorded:
 *
 *          VIVUTIO_RECORD_AUTHORITY_TABLE=1 vendor/bin/phpunit --filter <the test>
 *
 *   4. No self-escalation: every write, sent as everyone below the tiers,
 *      leaves nobody holding more than the sender could already grant.
 *   5. Canaries: marker values only some may see never reach anybody else.
 *
 * Nothing here decides what an outcome should be. The voters and the firewall
 * decide, exactly as for a real request.
 *
 * It runs against the package's test kernel with the framework's test
 * container on, a PostgreSQL database it empties and migrates, and debug off,
 * as an installation runs: the debug error page prints source code and would
 * fail every canary on the source rather than the product.
 */
abstract class AuthorityTestCase extends WebTestCase
{
    public const string RECORD = 'VIVUTIO_RECORD_AUTHORITY_TABLE';

    /** An address nobody else has, so seeing it anywhere is seeing a person's details. */
    private const string CANARY = 'canary.c4f7e1@vivutio-camps.example';

    private KernelBrowser $browser;

    private int $people = 0;

    /**
     * What to send, as every kind of person: at least one for every route of
     * the package, with real identifiers in the address.
     *
     * @return list<Probe>
     */
    abstract protected static function probes(): array;

    /** The directory the package's code is in. Its routes are those whose controller is in it. */
    abstract protected static function packageDirectory(): string;

    /** The committed markdown file holding the reviewed table. */
    abstract protected static function authorityTable(): string;

    /**
     * The package's routes that check no attribute, each with the reason.
     * Adding one is a decision about who may open a page, reviewed as one.
     *
     * @return array<string, string> route name to the reason
     */
    protected static function openRoutes(): array
    {
        return [];
    }

    /**
     * Seeds the package's own marker values, and says which attribute lets
     * somebody see each. The core's are seeded already: a person's address,
     * readable with personal_details.read, and a Super Admin's tier.
     *
     * @return array<string, string> marker to the attribute that may see it
     */
    protected function seedCanaries(EntityManagerInterface $entityManager): array
    {
        return [];
    }

    public static function setUpBeforeClass(): void
    {
        (new Filesystem())->remove(static::createKernel(['debug' => false])->getCacheDir());
    }

    protected function setUp(): void
    {
        $this->browser = static::createClient(['debug' => false]);

        $this->connection()->executeStatement('DROP SCHEMA IF EXISTS public CASCADE');
        $this->connection()->executeStatement('CREATE SCHEMA public');
        $this->migrate();
    }

    protected function tearDown(): void
    {
        $this->connection()->close();

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
        $open = static::openRoutes();
        $gates = $this->gates();
        $silent = [];
        $stale = [];

        foreach ($gates as $route => $attributes) {
            $isOpen = \array_key_exists($route, $open);

            if ([] === $attributes && !$isOpen) {
                $silent[] = $route;
            }
            if ([] !== $attributes && $isOpen) {
                $stale[] = $route.' (it checks '.implode(', ', $attributes).')';
            }
        }

        $missing = array_values(array_diff(array_keys($open), array_keys($gates)));

        self::assertSame([], $silent, "These routes check no attribute and are not on the open list:\n".implode("\n", $silent)."\nName the attribute with #[IsGranted], or add the route to openRoutes() with the reason it needs none.");
        self::assertSame([], $stale, "These routes are on the open list but check an attribute; take them off it:\n".implode("\n", $stale));
        self::assertSame([], $missing, "The open list names routes that do not exist:\n".implode("\n", $missing));
    }

    public function testEveryAttributeThePackageChecksIsAnsweredByExactlyOneVoter(): void
    {
        $wrong = [];

        foreach (['a door in a template' => self::doors(), ...$this->gates()] as $route => $attributes) {
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
     * every rule between tiers has one voter, so a page added tomorrow cannot
     * meet a question with two answers, and a module's voter cannot quietly
     * claim another package's question.
     */
    public function testEveryDeclaredPairAndEveryTierRuleIsAnsweredByExactlyOneVoter(): void
    {
        $wrong = [];
        foreach ([...$this->catalogue()->pairs(), AccountVoter::ACT_ON, AccountVoter::CHANGE_TIER, AccountVoter::DEACTIVATE, AccountVoter::SIGN_IN_AS, AccountVoter::SEE_TIERS] as $attribute) {
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
        self::assertSame([], $this->claimants('nothing_declares_this.read'));
    }

    /** A pair the package declares is one some route of it checks or some control of it is drawn by. */
    public function testEveryPairThePackageDeclaresIsCheckedBySomeRouteOrControl(): void
    {
        $checked = self::doors();
        foreach ($this->gates() as $attributes) {
            $checked = [...$checked, ...$attributes];
        }

        $unchecked = [];
        foreach ($this->tagged(TestServicesPass::CONCERN_SOURCES) as $source) {
            self::assertInstanceOf(ConcernSourceInterface::class, $source);
            if (!self::isThePackages($source::class)) {
                continue;
            }

            foreach ($source->concerns() as $concern) {
                foreach (Verb::cases() as $verb) {
                    $pair = (string) Grant::of($concern->key(), $verb);
                    if ($concern->supports($verb) && !\in_array($pair, $checked, true)) {
                        $unchecked[] = $source->declaredBy().': '.$pair;
                    }
                }
            }
        }

        self::assertSame([], $unchecked, "These declared pairs are checked by no route and draw no control, so ticking them changes nothing:\n".implode("\n", $unchecked));
    }

    public function testEveryRouteHasAProbe(): void
    {
        $routes = array_keys($this->gates());
        $probed = array_map(static fn (Probe $probe): string => $probe->route, static::probes());

        self::assertSame([], array_values(array_diff($routes, $probed)), 'These routes have no probe, so the table cannot say who may open them.');
        self::assertSame([], array_values(array_diff($probed, $routes)), 'These probes name routes that are not the package\'s.');
    }

    public function testEveryRouteAnswersEveryKindOfPersonAsTheReviewedTableSays(): void
    {
        $table = $this->table();

        if ('1' === getenv(self::RECORD)) {
            file_put_contents(static::authorityTable(), $table);
            self::markTestIncomplete('The authority table was recorded. Read its diff before committing it.');
        }

        self::assertFileExists(static::authorityTable(), 'No reviewed table yet. Record it with '.self::RECORD.'=1 and read it.');
        self::assertSame(
            (string) file_get_contents(static::authorityTable()),
            $table,
            'Who may open what has changed. If the change is intended, record the table with '.self::RECORD.'=1 and review its diff; if not, a loophole has opened.',
        );
    }

    /**
     * No self-escalation: every write is sent as every kind of person below
     * the tiers, and afterwards nobody may hold more than the sender held
     * before it, whatever the table says. A write that hands out a tier, a
     * pair or a signed-in account fails here even where it is allowed.
     */
    public function testNoWriteLeavesAnybodyHoldingMoreThanTheSenderCouldGrant(): void
    {
        $gates = $this->gates();
        $belowTheTiers = [Person::Stranger, Person::DeactivatedWhileSignedIn, Person::StaffWithoutPosition, Person::StaffHoldingThePair, Person::StaffHoldingAllButThePair];

        $escalations = [];
        foreach (static::probes() as $probe) {
            if ('GET' === $probe->method) {
                continue;
            }

            foreach ($belowTheTiers as $person) {
                $account = $this->signIn($person, $gates[$probe->route] ?? []);

                $before = $this->holdings();
                $this->send($probe);
                foreach ($this->holdings()->escalationsSince($before, $account?->getId()) as $escalation) {
                    $escalations[] = \sprintf('%s %s as %s: %s', $probe->method, $probe->path, $person->value, $escalation);
                }
            }
        }

        self::assertSame([], $escalations, implode("\n", $escalations));
    }

    /**
     * Canaries: a Super Admin is seeded with an address nobody else has, and
     * the package seeds its own markers. Every probe is sent as every kind of
     * person, and no marker may reach somebody who may not see it, nor the
     * words "Super Admin" anybody who may not see tiers. A page that opens
     * correctly and shows too much fails here.
     */
    public function testNoRouteShowsACanaryToSomebodyWhoMayNotSeeIt(): void
    {
        $this->account(TierEnum::SuperAdmin, null, self::CANARY)->setFirstName('Aaron')->setLastName('Canary');
        $markers = [self::CANARY => 'personal_details.read', ...$this->seedCanaries($this->entityManager()), 'Super Admin' => AccountVoter::SEE_TIERS];
        $this->entityManager()->flush();

        $gates = $this->gates();
        $leaks = [];

        foreach (static::probes() as $probe) {
            foreach (Person::cases() as $person) {
                $account = $this->signIn($person, $gates[$probe->route] ?? []);
                $this->send($probe);
                $content = (string) $this->browser->getInternalResponse()->getContent();

                foreach ($markers as $marker => $attribute) {
                    if (str_contains($content, $marker) && !$this->may($account, $attribute)) {
                        $leaks[] = \sprintf('%s %s shows "%s" to %s, who may not hold %s', $probe->method, $probe->path, $marker, $person->value, $attribute);
                    }
                }
            }
        }

        self::assertSame([], $leaks, implode("\n", $leaks));
    }

    private function table(): string
    {
        $gates = $this->gates();

        $lines = [
            '# Authority table',
            '',
            'Every route of the package, called as every kind of person. Generated by',
            'the authority test base; a change fails the build until it is recorded',
            'with `'.self::RECORD.'=1` and its diff is reviewed.',
        ];

        foreach (static::probes() as $probe) {
            $checked = $gates[$probe->route] ?? [];

            $lines[] = '';
            $lines[] = \sprintf('## %s: %s %s', $probe->route, $probe->method, $probe->path);
            $lines[] = '';
            $lines[] = 'Checks: '.([] === $checked ? 'nothing (an open route)' : '`'.implode('`, `', $checked).'`');
            $lines[] = '';
            $lines[] = '| Person | Outcome |';
            $lines[] = '|---|---|';

            foreach (Person::cases() as $person) {
                $lines[] = \sprintf('| %s | %s |', $person->value, $this->outcome($probe, $person, $checked));
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param list<string> $checked the attributes the route checks
     */
    private function outcome(Probe $probe, Person $person, array $checked): string
    {
        $this->signIn($person, $checked);
        $this->send($probe);

        $response = $this->browser->getResponse();
        $status = $response->getStatusCode();

        self::assertLessThan(500, $status, \sprintf('%s %s as %s failed with %d: an error is never an answer.', $probe->method, $probe->path, $person->value, $status));

        if ($response->isRedirection()) {
            $to = (string) parse_url((string) $response->headers->get('Location'), \PHP_URL_PATH);

            return '/login' === $to ? 'sent to sign-in' : 'redirected to '.$to;
        }

        // Allowed is not enough: which page somebody was given matters as much,
        // the organization's dashboard or their own, so its title is recorded.
        $title = 1 === preg_match('/<title>(.*?)<\/title>/s', (string) $response->getContent(), $found) ? trim(html_entity_decode($found[1])) : '';

        return match (true) {
            $response->isSuccessful() => '' === $title ? 'allowed' : 'allowed: '.$title,
            403 === $status => 'refused',
            404 === $status => 'not found',
            default => 'answered '.$status,
        };
    }

    /**
     * A fresh browser signed in as a kind of person, or as nobody.
     *
     * @param list<string> $checked the attributes the route checks
     */
    private function signIn(Person $person, array $checked): ?User
    {
        $this->browser->restart();

        $account = $this->person($person, $checked);
        if (null !== $account) {
            $this->browser->loginUser($account);
        }

        if (Person::DeactivatedWhileSignedIn === $person) {
            $this->connection()->executeStatement('UPDATE identity_user SET active = false WHERE id = ?', [$account?->getId()]);
            $account?->setActive(false);
        }

        return $account;
    }

    /**
     * Sends a probe. A write whose form is on a page reads that page's token
     * first, as a browser would; a person refused the page gets no token, and
     * the write goes without one.
     */
    private function send(Probe $probe): void
    {
        $body = $probe->body;

        if (null !== $probe->formAt) {
            $page = $this->browser->request('GET', $probe->formAt);
            $token = $page->filter('input[name="_token"]');
            if ($this->browser->getResponse()->isSuccessful() && 1 === $token->count()) {
                $body['_token'] = (string) $token->attr('value');
            }
        }

        $this->browser->request($probe->method, $probe->path, $body);
    }

    /**
     * The account a kind of person signs in with, or null for a stranger.
     *
     * @param list<string> $checked the attributes the route checks
     */
    private function person(Person $person, array $checked): ?User
    {
        $pairs = array_values(array_filter($checked, static fn (string $attribute): bool => null !== Grant::tryParse($attribute)));
        $everyPair = $this->catalogue()->positionPairs();

        return match ($person) {
            Person::Stranger => null,
            Person::DeactivatedWhileSignedIn => $this->account(TierEnum::Admin),
            Person::StaffWithoutPosition => $this->account(TierEnum::Staff),
            Person::StaffHoldingThePair => $this->account(TierEnum::Staff, $this->position(array_values(array_intersect($pairs, $everyPair)))),
            Person::StaffHoldingAllButThePair => $this->account(TierEnum::Staff, $this->position(array_values(array_diff($everyPair, $pairs)))),
            Person::Admin => $this->account(TierEnum::Admin),
            Person::SuperAdmin => $this->account(TierEnum::SuperAdmin),
        };
    }

    /**
     * @param list<string> $grants
     */
    private function position(array $grants): Position
    {
        $position = (new Position())->setName('Seat '.($this->people + 1))->setGrants($grants);
        $this->entityManager()->persist($position);

        return $position;
    }

    private function account(TierEnum $tier, ?Position $position = null, ?string $email = null): User
    {
        ++$this->people;

        $user = (new User())
            ->setEmail($email ?? 'person'.$this->people.'@vivutio-camps.example')
            ->setFirstName('Person')
            ->setLastName((string) $this->people)
            ->setTier($tier)
            ->setPosition($position)
            ->setPassword('a hash, never a password');

        $em = $this->entityManager();
        $em->persist($user);
        $em->flush();

        return $user;
    }

    /** What every account holds now, read from the database rather than from any object a page may have changed. */
    private function holdings(): Holdings
    {
        $every = $this->catalogue()->pairs();
        $people = [];

        foreach ($this->connection()->fetchAllAssociative('SELECT u.id, u.tier, u.active, p.grants FROM identity_user u LEFT JOIN identity_position p ON p.id = u.position_id') as $row) {
            ['id' => $id, 'tier' => $tierName, 'active' => $active, 'grants' => $stored] = $row;
            self::assertIsInt($id);
            self::assertIsString($tierName);
            self::assertIsBool($active);
            $grants = \is_string($stored) ? json_decode($stored, true, flags: \JSON_THROW_ON_ERROR) : [];
            self::assertIsArray($grants);
            $tier = TierEnum::from($tierName);

            $people[$id] = [
                'tier' => $tier->value,
                'active' => $active,
                'pairs' => $tier->holdsEveryPermission() ? $every : array_values(array_intersect(array_filter($grants, \is_string(...)), $every)),
            ];
        }

        return new Holdings($people);
    }

    private function may(?User $account, string $attribute): bool
    {
        if (null === $account) {
            return false;
        }

        $security = static::getContainer()->get('security.helper');
        self::assertInstanceOf(Security::class, $security);

        return $security->isGrantedForUser($account, $attribute);
    }

    /**
     * The package's routes, each with the attributes it checks.
     *
     * @return array<string, list<string>>
     */
    private function gates(): array
    {
        $router = static::getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        $gates = [];
        foreach (RouteGates::of($router) as $route => $attributes) {
            $controller = $router->getRouteCollection()->get($route)?->getDefault('_controller');
            $class = \is_string($controller) ? explode('::', $controller, 2)[0] : '';
            if (class_exists($class) && self::isThePackages($class)) {
                $gates[$route] = $attributes;
            }
        }

        return $gates;
    }

    /** @param class-string|string $class */
    private static function isThePackages(string $class): bool
    {
        if (!class_exists($class)) {
            return false;
        }

        $file = (string) new \ReflectionClass($class)->getFileName();

        return str_starts_with($file, rtrim(static::packageDirectory(), '/').'/');
    }

    /**
     * Every attribute a template of the package draws a control by, through
     * the one helper that does it.
     *
     * @return list<string>
     */
    private static function doors(): array
    {
        $doors = [];
        $templates = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(static::packageDirectory(), \FilesystemIterator::SKIP_DOTS));
        foreach ($templates as $template) {
            if (!$template instanceof \SplFileInfo || !str_ends_with($template->getFilename(), '.html.twig')) {
                continue;
            }
            preg_match_all("/door\\(\\s*'([^']+)'/", (string) file_get_contents($template->getPathname()), $matches);
            $doors = [...$doors, ...$matches[1]];
        }

        return $doors;
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
        foreach ($this->tagged(TestServicesPass::VOTERS) as $voter) {
            self::assertInstanceOf(VoterInterface::class, $voter);

            if (!$voter instanceof CacheableVoterInterface || $voter->supportsAttribute($attribute)) {
                $claimants[] = $voter::class;
            }
        }

        return $claimants;
    }

    /**
     * @return list<object>
     */
    private function tagged(string $id): array
    {
        $services = static::getContainer()->get($id);
        self::assertInstanceOf(TaggedServices::class, $services, 'The test container is off: the authority test base needs framework.test.');

        return $services->all();
    }

    private function catalogue(): ConcernCatalogue
    {
        $catalogue = static::getContainer()->get('identity.access.catalogue');
        self::assertInstanceOf(ConcernCatalogue::class, $catalogue);

        return $catalogue;
    }

    private function migrate(): void
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);

        $application = new Application($kernel);
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $output = new BufferedOutput();
        $status = $application->run(new ArrayInput(['command' => 'doctrine:migrations:migrate', '--no-interaction' => true]), $output);

        self::assertSame(0, $status, 'The migrations failed: '.$output->fetch());
    }

    private function entityManager(): EntityManagerInterface
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }

    private function connection(): Connection
    {
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }
}
