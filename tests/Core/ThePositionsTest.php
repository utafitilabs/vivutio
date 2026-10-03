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

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Form;
use Vivutio\Bundle\IdentityBundle\Entity\Position;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;

/**
 * The positions, ported from uhifadhi's register, record and configure page:
 * what each position grants and who holds it, read by whoever holds
 * positions.read, and changed by the tiers alone (as ruled in uhifadhi, #67),
 * since a position able to change positions could raise itself.
 */
final class ThePositionsTest extends MigrationsTestCase
{
    private KernelBrowser $browser;

    private EntityManagerInterface $em;

    protected function start(): void
    {
        $this->browser = static::createClient();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate();

        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->em = $em;
    }

    public function testTheRegisterSaysWhatEachPositionGrantsAndWhoHoldsIt(): void
    {
        $housekeeper = $this->position('Housekeeper', ['directory.read', 'personal_details.read']);
        $this->person('Amani', TierEnum::Staff, $housekeeper);
        $this->person('Jabiri', TierEnum::Staff, $housekeeper);
        $this->position('Guide', []);
        $this->signedInAs($this->person('Elia', TierEnum::Staff, $this->position('Clerk', ['positions.read'])));

        $page = $this->browser->request('GET', '/team/positions');

        self::assertResponseIsSuccessful();
        self::assertSame(['Clerk', 'Guide', 'Housekeeper'], $page->filter('tr[data-position]')->each(static fn (Crawler $row): string => (string) $row->attr('data-position')));
        $row = $page->filter('tr[data-position="Housekeeper"]');
        self::assertSame('reads 2 concerns', trim($row->filter('[data-grants]')->text()));
        self::assertSame('1', trim($row->filter('[data-sensitive]')->text()));
        self::assertSame('2', trim($row->filter('[data-holders]')->attr('data-holders') ?? ''));
        self::assertSame('Grants nothing', trim($page->filter('tr[data-position="Guide"] [data-grants]')->text()));
        self::assertSame('/team/positions/'.$housekeeper->getUuid(), $row->selectLink('Open')->attr('href'));
    }

    /** The tiers are explained above the matrix without naming one, so the page tells nobody who holds which. */
    public function testTheTiersAreExplainedAboveTheMatrix(): void
    {
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        $page = $this->browser->request('GET', '/team/positions');

        self::assertStringContainsString('Above the matrix', $page->filter('[data-tiers]')->text());
        self::assertStringNotContainsString('Super Admin', (string) $page->filter('main')->html());
    }

    public function testWithoutPositionsReadTheRegisterIsNotOpenedNorInTheMenu(): void
    {
        $this->signedInAs($this->person('Elia', TierEnum::Staff, $this->position('Clerk', ['directory.read'])));

        self::assertCount(0, $this->browser->request('GET', '/')->filter('nav.menu')->selectLink('Positions'));
        $this->browser->request('GET', '/team/positions');
        self::assertResponseStatusCodeSame(403);
    }

    public function testThePositionsRecordShowsItsGrantsAndHolders(): void
    {
        $housekeeper = $this->position('Housekeeper', ['directory.read', 'personal_details.read']);
        $amani = $this->person('Amani', TierEnum::Staff, $housekeeper);
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        $page = $this->browser->request('GET', '/team/positions/'.$housekeeper->getUuid());

        self::assertSame('Housekeeper', trim($page->filter('h1')->text()));
        self::assertSame(['directory.read', 'personal_details.read'], $page->filter('[data-matrix] [data-pair][data-on]')->each(static fn (Crawler $cell): string => (string) $cell->attr('data-pair')));
        self::assertSame('/team/'.$amani->getUuid(), $page->filter('[data-holders]')->selectLink('Amani Kimaro')->attr('href'));
    }

    /** A verb a concern does not declare has no cell, and a pair only the tiers hold is never offered. */
    public function testTheMatrixDrawsOnlyWhatAPositionMayCarry(): void
    {
        $housekeeper = $this->position('Housekeeper', []);
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        $page = $this->browser->request('GET', '/team/positions/'.$housekeeper->getUuid().'/configure');

        $offered = $page->filter('[data-matrix] input[name="grants[]"]')->each(static fn (Crawler $box): string => (string) $box->attr('value'));
        self::assertContains('directory.read', $offered);
        self::assertContains('positions.read', $offered);
        self::assertNotContains('directory.manage', $offered);
        self::assertNotContains('positions.configure', $offered);
        self::assertNotContains('directory.delete', $offered, 'the directory declares no delete');
    }

    public function testATierChangesWhatAPositionGrantsAndItsName(): void
    {
        $housekeeper = $this->position('Housekeeper', ['directory.read']);
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        $page = $this->browser->request('GET', '/team/positions/'.$housekeeper->getUuid().'/configure');
        $form = $page->selectButton('Save position')->form();
        $form['name'] = 'Head housekeeper';
        $this->choose($form, 'directory.read', false);
        $this->choose($form, 'personal_details.read', true);
        $this->browser->submit($form);

        self::assertResponseRedirects('/team/positions/'.$housekeeper->getUuid());
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.notice[role="status"]', 'The position is saved.');
        $housekeeper = $this->reloaded($housekeeper);
        self::assertSame('Head housekeeper', $housekeeper->getName());
        self::assertSame(['personal_details.read'], $housekeeper->getGrants());
    }

    public function testAPairOnlyTheTiersHoldIsRefusedEvenByAForgedForm(): void
    {
        $housekeeper = $this->position('Housekeeper', ['directory.read']);
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        $page = $this->browser->request('GET', '/team/positions/'.$housekeeper->getUuid().'/configure');
        $form = $page->selectButton('Save position')->form();
        $this->browser->request('POST', (string) $form->getUri(), ['name' => 'Housekeeper', 'grants' => ['directory.read', 'directory.manage'], '_token' => $form->getValues()['_token']]);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('directory.manage', (string) $this->browser->getResponse()->getContent());
        self::assertSame(['directory.read'], $this->reloaded($housekeeper)->getGrants());
    }

    public function testANameCannotBeEmpty(): void
    {
        $housekeeper = $this->position('Housekeeper', []);
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        $page = $this->browser->request('GET', '/team/positions/'.$housekeeper->getUuid().'/configure');
        $page = $this->browser->submit($page->selectButton('Save position')->form(['name' => '  ']));

        self::assertResponseStatusCodeSame(422);
        self::assertSame('name', $page->filter('.field.wrong input')->attr('name'));
    }

    public function testATierAddsAPositionAndGoesOnToSayWhatItGrants(): void
    {
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        $page = $this->browser->request('GET', '/team/positions');
        $this->browser->submit($page->selectButton('Add the position')->form(['name' => 'Night guard']));

        $added = $this->em->getRepository(Position::class)->findOneBy(['name' => 'Night guard']);
        self::assertInstanceOf(Position::class, $added);
        self::assertSame([], $added->getGrants(), 'a new position grants nothing');
        self::assertResponseRedirects('/team/positions/'.$added->getUuid().'/configure');
    }

    /** Even a position holding everything a position can hold changes no position, and is offered no control to. */
    public function testStaffChangeNoPosition(): void
    {
        $housekeeper = $this->position('Housekeeper', ['directory.read']);
        $this->signedInAs($this->person('Elia', TierEnum::Staff, $this->position('Clerk', ['positions.read', 'directory.read'])));

        $register = $this->browser->request('GET', '/team/positions');
        self::assertCount(0, $register->selectButton('Add the position'));
        self::assertCount(0, $this->browser->request('GET', '/team/positions/'.$housekeeper->getUuid())->selectLink('Configure'));

        $this->browser->request('GET', '/team/positions/'.$housekeeper->getUuid().'/configure');
        self::assertResponseStatusCodeSame(403);
        $this->browser->request('POST', '/team/positions', ['name' => 'Taken over']);
        self::assertResponseStatusCodeSame(403);
    }

    private function choose(Form $form, string $pair, bool $on): void
    {
        $boxes = $form['grants'];
        self::assertIsArray($boxes);

        foreach ($boxes as $box) {
            self::assertInstanceOf(ChoiceFormField::class, $box);
            if ($pair === $box->availableOptionValues()[0]) {
                $on ? $box->tick() : $box->untick();

                return;
            }
        }

        self::fail('No box for '.$pair);
    }

    /**
     * @param list<string> $grants
     */
    private function position(string $name, array $grants): Position
    {
        $position = (new Position())->setName($name)->setGrants($grants);
        $this->em->persist($position);
        $this->em->flush();

        return $position;
    }

    private function person(string $name, TierEnum $tier, ?Position $position = null): User
    {
        $user = (new User())
            ->setEmail(strtolower($name).'@vivutio-camps.example')
            ->setFirstName($name)
            ->setLastName('Kimaro')
            ->setTier($tier)
            ->setPosition($position)
            ->setPassword('a hash, never a password');
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function signedInAs(User $user): void
    {
        $this->browser->restart();
        $this->browser->loginUser($user);
    }

    private function reloaded(Position $position): Position
    {
        $this->em->clear();
        $fresh = $this->em->find(Position::class, $position->getId());
        self::assertInstanceOf(Position::class, $fresh);

        return $fresh;
    }
}
