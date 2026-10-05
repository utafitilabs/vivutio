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
use Symfony\Component\Mime\Email;
use Vivutio\Bundle\PartnerBundle\Entity\Partner;
use Vivutio\Bundle\PartnerBundle\Enum\PartnerKindEnum;
use Vivutio\Contracts\Partner\PartnerChannelInterface;
use Vivutio\Contracts\Partner\PartnerMessage;

/**
 * The manual channel (ruled 1 October): a request to a partner leaves as an
 * email to the address the partner is kept with, and its status is recorded
 * by hand. A module sends through the channel contract and never builds the
 * mail itself, so the hub channel can take a partner over without the module
 * knowing.
 */
final class TheManualChannelTest extends MigrationsTestCase
{
    protected function start(): void
    {
        static::bootKernel();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate();
    }

    public function testAMessageLeavesAsAnEmailToThePartner(): void
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $camp = (new Partner('Ngorongoro Rim Camp', PartnerKindEnum::Accommodation, 'TZ', 'stay@rim-camp.example'))->setContact('Paulo Saitoti');
        $em->persist($camp);
        $em->flush();

        $channel = static::getContainer()->get(PartnerChannelInterface::class);
        self::assertInstanceOf(PartnerChannelInterface::class, $channel);
        $said = $channel->send($camp, new PartnerMessage('RQ-0001', 'Rooms for the Mollel party, 2 to 4 Nov 2026', "2 double rooms, 2 nights from 2 Nov 2026.\nPlease confirm with your reference."));

        self::assertSame('Sent by email to stay@rim-camp.example', $said);
        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('stay@rim-camp.example', $email->getTo()[0]->getAddress());
        self::assertSame('Paulo Saitoti', $email->getTo()[0]->getName());
        self::assertSame('RQ-0001 · Rooms for the Mollel party, 2 to 4 Nov 2026', $email->getSubject());
        self::assertStringContainsString('2 double rooms, 2 nights from 2 Nov 2026.', (string) $email->getTextBody());
        self::assertStringContainsString('Our reference: RQ-0001', (string) $email->getTextBody());
    }
}
