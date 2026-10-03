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

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\RouterInterface;
use Vivutio\Bundle\IdentityBundle\Access\ConcernCatalogue;
use Vivutio\Bundle\IdentityBundle\Entity\Position;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;
use Vivutio\Bundle\IdentityBundle\Security\AccountVoter;
use Vivutio\Contracts\Access\Grant;
use Vivutio\Core\Tests\Application\Kernel;
use Vivutio\Core\Tests\Core\Authority\CoreProbes;
use Vivutio\Core\Tests\Core\Authority\Holdings;
use Vivutio\Core\Tests\Core\Authority\Person;
use Vivutio\Core\Tests\Core\Authority\Probe;
use Vivutio\Core\Tests\Core\Authority\RouteGates;

/**
 * The authority table: every route, called as every kind of person, compared
 * with the reviewed table committed beside this test.
 *
 * Nothing here decides what the outcome should be. The voters and the
 * firewall decide, exactly as they do for a real request, and the table holds
 * what was reviewed. A change in who may open what is a change to the table,
 * and fails until it is recorded and its diff is read:
 *
 *     VIVUTIO_RECORD_AUTHORITY_TABLE=1 vendor/bin/phpunit --filter AuthorityTableTest
 */
final class AuthorityTableTest extends MigrationsTestCase
{
    private const string TABLE = __DIR__.'/authority-table.md';

    private const string RECORD = 'VIVUTIO_RECORD_AUTHORITY_TABLE';

    /** An address nobody else has, so seeing it anywhere is seeing a person's details. */
    private const string CANARY = 'canary.c4f7e1@vivutio-camps.example';

    private KernelBrowser $browser;

    private int $people = 0;

    /**
     * Without debug, as an installation runs: a refused request answers with
     * what production serves, never the debug page, which prints source code
     * and would fail every canary on the source rather than the product.
     */
    protected function start(): void
    {
        $this->browser = static::createClient(['debug' => false]);
    }

    public static function setUpBeforeClass(): void
    {
        (new Filesystem())->remove(\dirname(__DIR__).'/Application/var/cache/test_without_debug');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate();
    }

    public function testEveryRouteHasAProbe(): void
    {
        $routes = array_keys($this->gates());
        $probed = array_map(static fn (Probe $probe): string => $probe->route, CoreProbes::all());

        self::assertSame([], array_values(array_diff($routes, $probed)), 'These routes have no probe, so the table cannot say who may open them.');
        self::assertSame([], array_values(array_diff($probed, $routes)), 'These probes name routes that do not exist.');
    }

    public function testEveryRouteAnswersEveryKindOfPersonAsTheReviewedTableSays(): void
    {
        $table = $this->table();

        if ('1' === getenv(self::RECORD)) {
            file_put_contents(self::TABLE, $table);
            self::markTestIncomplete('The authority table was recorded. Read its diff before committing it.');
        }

        self::assertFileExists(self::TABLE, 'No reviewed table yet. Record it with '.self::RECORD.'=1 and read it.');
        self::assertSame(
            (string) file_get_contents(self::TABLE),
            $table,
            'Who may open what has changed. If the change is intended, record the table with '.self::RECORD.'=1 and review its diff; if not, a loophole has opened.',
        );
    }

    /**
     * Canaries: marker values in what only some may see. A Super Admin is
     * seeded with an address nobody else has; every route is called as every
     * kind of person, and the marker must not reach anybody who may not read
     * personal details, nor the words "Super Admin" anybody who may not see
     * tiers. A page that opens correctly and shows too much fails here.
     */
    public function testNoRouteShowsACanaryToSomebodyWhoMayNotSeeIt(): void
    {
        $this->account(TierEnum::SuperAdmin, null, self::CANARY)->setFirstName('Aaron')->setLastName('Canary');
        $this->entityManager()->flush();
        $gates = $this->gates();
        $leaks = [];

        foreach (CoreProbes::all() as $probe) {
            foreach (Person::cases() as $person) {
                [$account, $content] = $this->call($probe, $person, $gates[$probe->route] ?? []);

                if (str_contains($content, self::CANARY) && !$this->may($account, 'personal_details.read')) {
                    $leaks[] = \sprintf('%s %s shows a person\'s address to %s', $probe->method, $probe->path, $person->value);
                }
                if (str_contains($content, 'Super Admin') && !$this->may($account, AccountVoter::SEE_TIERS)) {
                    $leaks[] = \sprintf('%s %s shows a Super Admin\'s tier to %s', $probe->method, $probe->path, $person->value);
                }
            }
        }

        self::assertSame([], $leaks, implode("\n", $leaks));
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
        $writes = array_filter(CoreProbes::all(), static fn (Probe $probe): bool => 'GET' !== $probe->method);
        $belowTheTiers = [Person::Stranger, Person::DeactivatedWhileSignedIn, Person::StaffWithoutPosition, Person::StaffHoldingThePair, Person::StaffHoldingAllButThePair];
        self::assertNotSame([], $writes, 'there is a write to check');

        $escalations = [];
        foreach ($writes as $probe) {
            foreach ($belowTheTiers as $person) {
                $this->browser->restart();
                $account = $this->person($person, $gates[$probe->route] ?? []);
                if (null !== $account) {
                    $this->browser->loginUser($account);
                }
                if (Person::DeactivatedWhileSignedIn === $person) {
                    $this->connection()->executeStatement('UPDATE identity_user SET active = false WHERE id = ?', [$account?->getId()]);
                }

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

        $security = static::getContainer()->get(Kernel::SECURITY);
        self::assertInstanceOf(Security::class, $security);

        return $security->isGrantedForUser($account, $attribute);
    }

    /**
     * Sends a probe as a kind of person.
     *
     * @param list<string> $checked the attributes the route checks
     *
     * @return array{?User, string} the account it was sent as, and what came back
     */
    private function call(Probe $probe, Person $person, array $checked): array
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

        $this->send($probe);

        return [$account, (string) $this->browser->getInternalResponse()->getContent()];
    }

    private function table(): string
    {
        $gates = $this->gates();

        $lines = [
            '# Authority table',
            '',
            'Every route of the core, called as every kind of person. Generated by',
            '`tests/Core/AuthorityTableTest.php`; a change fails the build until it is',
            'recorded with `VIVUTIO_RECORD_AUTHORITY_TABLE=1` and its diff is reviewed.',
        ];

        foreach (CoreProbes::all() as $probe) {
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
        $this->browser->restart();

        $account = $this->person($person, $checked);
        if (null !== $account) {
            $this->browser->loginUser($account);
        }

        if (Person::DeactivatedWhileSignedIn === $person) {
            $this->connection()->executeStatement('UPDATE identity_user SET active = false WHERE id = ?', [$account?->getId()]);
        }

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

    /**
     * @return array<string, list<string>>
     */
    private function gates(): array
    {
        $router = static::getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        return RouteGates::of($router);
    }

    private function catalogue(): ConcernCatalogue
    {
        $catalogue = static::getContainer()->get(Kernel::CATALOGUE);
        self::assertInstanceOf(ConcernCatalogue::class, $catalogue);

        return $catalogue;
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
