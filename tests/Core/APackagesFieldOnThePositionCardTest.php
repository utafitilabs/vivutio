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
use Vivutio\Bundle\IdentityBundle\Entity\Position;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;
use Vivutio\Core\Tests\Application\NotesModule\NotesPlaces;
use Vivutio\Core\Tests\Application\NotesModule\NotesPositionCard;

/**
 * A package's field on a person's Position card (the ruling of 2 October
 * gives "Permissions apply at" to the module that owns properties): drawn in
 * the card, checked with it, saved with it, and said beside the person's
 * record. Played by the stand-in package's desk.
 */
final class APackagesFieldOnThePositionCardTest extends MigrationsTestCase
{
    private KernelBrowser $browser;

    protected function start(): void
    {
        $this->browser = static::createClient();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate();
        NotesPositionCard::$desks = [];
    }

    public function testTheFieldIsDrawnInTheCardAndSavedWithIt(): void
    {
        $amani = $this->person('Amani');
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        $page = $this->browser->request('GET', '/team/'.$amani->getUuid().'/configure');
        $card = $page->filter('form')->reduce(static fn ($form): bool => 1 === $form->selectButton('Save position')->count());
        self::assertCount(1, $card->filter('[data-notes-desk] input[name="notes_desk"]'), 'drawn inside the Position card');

        $this->browser->submit($page->selectButton('Save position')->form(['notes_desk' => 'Window', 'posted_at' => 'board:'.NotesPlaces::KITCHEN]));

        self::assertResponseRedirects('/team/'.$amani->getUuid().'/configure');
        self::assertSame('Window', NotesPositionCard::$desks[(string) $amani->getUuid()]);
        self::assertStringContainsString('At Kitchen board', $this->browser->followRedirect()->filter('[data-notes-desk]')->text(), 'given where they are posted');
        self::assertSame('Window', trim($this->browser->request('GET', '/team/'.$amani->getUuid())->filter('[data-card-summary="Desk"] b')->text()));
    }

    /** A refusal from the package keeps the whole card unsaved, the core's fields too. */
    public function testWhatThePackageRefusesKeepsTheCardUnsaved(): void
    {
        $amani = $this->person('Amani');
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        $page = $this->browser->request('GET', '/team/'.$amani->getUuid().'/configure');
        $page = $this->browser->submit($page->selectButton('Save position')->form(['notes_desk' => 'Busy', 'posted_at' => 'board:'.NotesPlaces::KITCHEN]));

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('That desk is taken.', $page->filter('[data-notes-desk]')->text());
        self::assertSame('Busy', $page->filter('input[name="notes_desk"]')->attr('value'));
        self::assertArrayNotHasKey((string) $amani->getUuid(), NotesPositionCard::$desks);
        $this->em()->clear();
        $fresh = $this->em()->find(User::class, $amani->getId());
        self::assertInstanceOf(User::class, $fresh);
        self::assertTrue($fresh->isPostedAt(null), 'the posting was not saved either');
    }

    private function person(string $name, TierEnum $tier = TierEnum::Staff): User
    {
        $position = (new Position())->setName($name.'\'s seat');
        $this->em()->persist($position);
        $user = (new User())
            ->setEmail(strtolower($name).'@vivutio-camps.example')
            ->setFirstName($name)
            ->setLastName('Kimaro')
            ->setTier($tier)
            ->setPassword('a hash, never a password');
        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    private function signedInAs(User $user): void
    {
        $this->browser->restart();
        $this->browser->loginUser($user);
    }

    private function em(): EntityManagerInterface
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }
}
