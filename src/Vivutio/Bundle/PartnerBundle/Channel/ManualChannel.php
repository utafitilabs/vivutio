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

namespace Vivutio\Bundle\PartnerBundle\Channel;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Vivutio\Bundle\PartnerBundle\Entity\Partner;
use Vivutio\Contracts\Partner\PartnerChannelInterface;
use Vivutio\Contracts\Partner\PartnerInterface;
use Vivutio\Contracts\Partner\PartnerMessage;

/**
 * The manual channel: a message leaves as an email to the address the partner
 * is kept with, its reference in the subject so a reply quotes it. Always
 * available; it needs nothing outside the installation but its mail.
 */
final readonly class ManualChannel implements PartnerChannelInterface
{
    public function __construct(private MailerInterface $mailer)
    {
    }

    public function send(PartnerInterface $partner, PartnerMessage $message): string
    {
        $contact = $partner instanceof Partner ? $partner->getContact() : '';
        $this->mailer->send((new TemplatedEmail())
            ->to(new Address($partner->getEmail(), $contact))
            ->subject($message->reference.' · '.$message->subject)
            ->textTemplate('@Partner/email/message.txt.twig')
            ->context(['partner' => $partner, 'contact' => $contact, 'message' => $message]));

        return 'Sent by email to '.$partner->getEmail();
    }
}
