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
use Symfony\Component\Mime\Email;
use Vivutio\Bundle\IdentityBundle\Access\ConcernCatalogue;
use Vivutio\Bundle\IdentityBundle\Entity\Position;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;
use Vivutio\Bundle\IdentityBundle\Service\MailAvailability;
use Vivutio\Core\Tests\Application\Kernel;

/**
 * Configuring a person, as designed in vivutio-designs/team/member-configure.html:
 * a panel for the details, one for signing in and one for the position, each
 * saved by itself, and deactivating in the side column.
 *
 * As ruled in uhifadhi (#67), managing the directory and personal details is
 * the tiers' alone, so no position can raise itself or take over a colleague's
 * account; and nobody acts on an account above their own tier.
 */
final class ConfiguringAPersonTest extends MigrationsTestCase
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

    public function testATierOpensTheConfigurePageFromTheRecord(): void
    {
        $amani = $this->person('Amani', TierEnum::Staff, ['directory.read'], 'Housekeeper');
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        $record = $this->browser->request('GET', $this->address($amani));
        self::assertSame($this->address($amani).'/configure', $record->selectLink('Configure')->attr('href'));

        $page = $this->browser->request('GET', $this->address($amani).'/configure');
        self::assertResponseIsSuccessful();
        self::assertSame('Amani', $page->selectButton('Save details')->form()->getValues()['first_name']);
        self::assertSame('amani@vivutio-camps.example', $page->selectButton('Save sign-in')->form()->getValues()['email']);
        self::assertSame((string) $amani->getPosition()?->getUuid(), $page->selectButton('Save position')->form()->getValues()['position']);
    }

    /** Even a position holding everything a position can hold configures nobody, and is shown no Configure. */
    public function testStaffConfigureNobody(): void
    {
        $amani = $this->person('Amani', TierEnum::Staff);
        $this->signedInAs($this->person('Elia', TierEnum::Staff, $this->everyPairAPositionCanHold(), 'Everything'));

        self::assertCount(0, $this->browser->request('GET', $this->address($amani))->selectLink('Configure'));

        $this->browser->request('GET', $this->address($amani).'/configure');
        self::assertResponseStatusCodeSame(403);
        $this->browser->request('POST', $this->address($amani).'/configure/details', ['first_name' => 'Taken', 'last_name' => 'Over']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame('Amani', $this->reloaded($amani)->getFirstName());
    }

    public function testNobodyConfiguresAnAccountAboveTheirOwnTier(): void
    {
        $neema = $this->person('Neema', TierEnum::SuperAdmin);
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        $this->browser->request('GET', $this->address($neema).'/configure');

        self::assertResponseStatusCodeSame(403);
    }

    public function testTheDetailsAreSavedByThemselves(): void
    {
        $amani = $this->person('Amani', TierEnum::Staff);
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        $this->save($amani, 'Save details', ['first_name' => '  Amani ', 'last_name' => 'Lyimo', 'phone' => '+255 700 000 114']);

        self::assertResponseRedirects($this->address($amani).'/configure');
        $this->browser->followRedirect();
        self::assertSelectorTextContains('.notice[role="status"]', 'The details are saved.');
        $amani = $this->reloaded($amani);
        self::assertSame(['Amani', 'Lyimo', '+255 700 000 114'], [$amani->getFirstName(), $amani->getLastName(), $amani->getPhone()]);
    }

    public function testANameCannotBeEmpty(): void
    {
        $amani = $this->person('Amani', TierEnum::Staff);
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        $page = $this->save($amani, 'Save details', ['first_name' => ' ', 'last_name' => 'Lyimo']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('first_name', $page->filter('.field.wrong input')->attr('name'));
        self::assertSame('Amani', $this->reloaded($amani)->getFirstName());
    }

    public function testTheAddressIsChangedAndAnAddressInUseIsRefused(): void
    {
        $amani = $this->person('Amani', TierEnum::Staff);
        $this->person('Elia', TierEnum::Staff);
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        $page = $this->save($amani, 'Save sign-in', ['email' => 'Elia@Vivutio-Camps.example']);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('already', $page->filter('.field.wrong .hint')->text());

        $this->save($amani, 'Save sign-in', ['email' => 'Amani.Lyimo@Vivutio-Camps.example']);
        self::assertResponseRedirects($this->address($amani).'/configure');
        self::assertSame('amani.lyimo@vivutio-camps.example', $this->reloaded($amani)->getEmail());
    }

    /** The tiers offered are only those the person configuring may give. */
    public function testOnlyTheTiersOneMayGiveAreOffered(): void
    {
        $amani = $this->person('Amani', TierEnum::Staff);

        $this->signedInAs($this->person('Baraka', TierEnum::Admin));
        self::assertSame(['Admin', 'Staff'], $this->tiersOffered($amani));

        $this->signedInAs($this->person('Neema', TierEnum::SuperAdmin));
        self::assertSame(['Super Admin', 'Admin', 'Staff'], $this->tiersOffered($amani));
    }

    public function testAnAdminCannotMakeASuperAdminEvenByForgingTheForm(): void
    {
        $amani = $this->person('Amani', TierEnum::Staff);
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        $page = $this->browser->request('GET', $this->address($amani).'/configure');
        $form = $page->selectButton('Save sign-in')->form();
        $this->browser->request('POST', (string) $form->getUri(), ['email' => 'amani@vivutio-camps.example', 'tier' => 'super_admin', '_token' => $form->getValues()['_token']]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(TierEnum::Staff, $this->reloaded($amani)->getTier());
    }

    /** The last Super Admin is offered no other tier, is told why, and a forged form changes nothing. */
    public function testTheLastSuperAdminKeepsTheirTierAndTheFormSaysWhy(): void
    {
        $neema = $this->person('Neema', TierEnum::SuperAdmin);
        $this->signedInAs($neema);

        $page = $this->browser->request('GET', $this->address($neema).'/configure');
        self::assertSame(['Super Admin'], $this->tiersOffered($neema));
        self::assertStringContainsString('only active Super Admin', $page->filter('[data-tier-says]')->text());

        $form = $page->selectButton('Save sign-in')->form();
        $this->browser->request('POST', (string) $form->getUri(), ['email' => 'neema@vivutio-camps.example', 'tier' => 'admin', '_token' => $form->getValues()['_token']]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(TierEnum::SuperAdmin, $this->reloaded($neema)->getTier());
    }

    public function testThePositionIsSetAndCleared(): void
    {
        $amani = $this->person('Amani', TierEnum::Staff);
        $guide = (new Position())->setName('Guide')->setGrants(['directory.read']);
        $this->em->persist($guide);
        $this->em->flush();
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        $this->save($amani, 'Save position', ['position' => (string) $guide->getUuid()]);
        self::assertSame('Guide', $this->reloaded($amani)->getPosition()?->getName());

        $this->save($amani, 'Save position', ['position' => '']);
        self::assertNull($this->reloaded($amani)->getPosition());
    }

    public function testDeactivatingAndBringingBack(): void
    {
        $amani = $this->person('Amani', TierEnum::Staff);
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        $page = $this->browser->request('GET', $this->address($amani).'/configure');
        $this->browser->submit($page->selectButton('Deactivate')->form());
        self::assertResponseRedirects($this->address($amani));
        self::assertFalse($this->reloaded($amani)->isActive());

        $page = $this->browser->request('GET', $this->address($amani).'/configure');
        $this->browser->submit($page->selectButton('Bring back')->form());
        self::assertTrue($this->reloaded($amani)->isActive());
    }

    public function testTheLastSuperAdminIsOfferedNoDeactivateAndSaysWhy(): void
    {
        $neema = $this->person('Neema', TierEnum::SuperAdmin);
        $this->signedInAs($neema);

        $page = $this->browser->request('GET', $this->address($neema).'/configure');

        self::assertCount(0, $page->selectButton('Deactivate'));
        self::assertStringContainsString('only active Super Admin', $page->filter('[data-account-actions]')->text());
    }

    /** As uhifadhi's ruled sign-in help (D): sent from the record, said in one line, remembered on the card. */
    public function testATierSendsALinkToSetANewPassword(): void
    {
        $amani = $this->person('Amani', TierEnum::Staff);
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        $page = $this->browser->request('GET', $this->address($amani).'/configure');
        $this->browser->submit($page->selectButton('Send')->form());

        self::assertResponseRedirects($this->address($amani).'/configure');
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('amani@vivutio-camps.example', $email->getTo()[0]->getAddress());
        self::assertStringContainsString('Baraka Kimaro sent you a link', (string) $email->getTextBody());

        $page = $this->browser->followRedirect();
        self::assertSelectorTextContains('.notice[role="status"]', 'A link was sent to amani@vivutio-camps.example. It works once and expires in an hour.');
        self::assertStringContainsString('by Baraka Kimaro', $page->filter('[data-account-actions]')->text());
        self::assertCount(1, $page->selectButton('Send again'));
    }

    public function testWithoutMailNoLinkIsOfferedAndTheCardSaysWhy(): void
    {
        $amani = $this->person('Amani', TierEnum::Staff);
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));
        static::getContainer()->set('identity.mail_availability', new MailAvailability(false));

        $page = $this->browser->request('GET', $this->address($amani).'/configure');

        self::assertCount(0, $page->selectButton('Send'));
        self::assertStringContainsString('cannot send email yet', $page->filter('[data-account-actions]')->text());
    }

    /** A form whose token is missing changes nothing. */
    public function testAFormWithoutItsTokenChangesNothing(): void
    {
        $amani = $this->person('Amani', TierEnum::Staff);
        $this->signedInAs($this->person('Baraka', TierEnum::Admin));

        $this->browser->request('POST', $this->address($amani).'/configure/details', ['first_name' => 'Taken', 'last_name' => 'Over']);
        self::assertResponseStatusCodeSame(422);
        $this->browser->request('POST', $this->address($amani).'/deactivate');
        self::assertResponseStatusCodeSame(422);

        $amani = $this->reloaded($amani);
        self::assertSame('Amani', $amani->getFirstName());
        self::assertTrue($amani->isActive());
    }

    /**
     * @param array<string, string> $values
     */
    private function save(User $person, string $button, array $values): Crawler
    {
        $page = $this->browser->request('GET', $this->address($person).'/configure');

        return $this->browser->submit($page->selectButton($button)->form($values));
    }

    /**
     * @return list<string>
     */
    private function tiersOffered(User $person): array
    {
        return $this->browser->request('GET', $this->address($person).'/configure')
            ->filter('[data-tiers] input[name="tier"]')
            ->each(static fn (Crawler $option): string => trim($option->closest('label')?->text() ?? ''));
    }

    /**
     * @return list<string>
     */
    private function everyPairAPositionCanHold(): array
    {
        $catalogue = static::getContainer()->get(Kernel::CATALOGUE);
        self::assertInstanceOf(ConcernCatalogue::class, $catalogue);

        return $catalogue->positionPairs();
    }

    /**
     * @param list<string>|null $grants
     */
    private function person(string $name, TierEnum $tier, ?array $grants = null, string $seat = 'Seat'): User
    {
        $position = null;
        if (null !== $grants) {
            $position = (new Position())->setName($seat)->setGrants($grants);
            $this->em->persist($position);
        }

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

    private function reloaded(User $user): User
    {
        $this->em->clear();
        $fresh = $this->em->find(User::class, $user->getId());
        self::assertInstanceOf(User::class, $fresh);

        return $fresh;
    }

    private function address(User $user): string
    {
        return '/team/'.$user->getUuid();
    }
}
