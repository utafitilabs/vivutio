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

namespace Vivutio\Bundle\IdentityBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Vivutio\Bundle\IdentityBundle\Entity\AccountLink;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\LinkPurposeEnum;
use Vivutio\Bundle\IdentityBundle\Exception\InvalidPasswordException;
use Vivutio\Bundle\IdentityBundle\Exception\InvalidPersonException;
use Vivutio\Bundle\IdentityBundle\Repository\AccountLinkRepository;
use Vivutio\Bundle\IdentityBundle\Repository\UserRepository;

/**
 * The links sent to people's addresses: issued, mailed, redeemed once.
 *
 * Issuing a link of a purpose withdraws every earlier unused one of that
 * purpose for the same account, so an old email stops working the moment a
 * new one is sent.
 */
final readonly class AccountLinkService
{
    /** Asked again within this, an address is not mailed again: a form cannot be used to flood an inbox. */
    private const string QUIET = 'PT1M';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AccountLinkRepository $links,
        private UserRepository $users,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urls,
        private UserPasswordHasherInterface $hasher,
        private PasswordRulesService $rules,
    ) {
    }

    /**
     * Somebody who forgot their password asks for a link. Nothing is said
     * about whether the address has an account: the caller answers the same
     * either way.
     */
    public function requestReset(string $email): void
    {
        $account = $this->users->findOneBy(['email' => mb_strtolower(trim($email))]);
        // An invited account is reached through its invitation, not a reset.
        if (null === $account || !$account->isActive() || !$account->isVerified() || $this->askedJustNow($account)) {
            return;
        }

        $this->sendReset($account, null);
    }

    /** Mails a reset link, whether asked for by its holder or sent by somebody configuring them. */
    public function sendReset(User $account, ?User $sentBy): AccountLink
    {
        [$link, $url] = $this->issue($account, LinkPurposeEnum::Reset, $sentBy);

        $this->mailer->send((new TemplatedEmail())
            ->to(new Address((string) $account->getEmail(), $account->getFullName()))
            ->subject('Set a new password')
            ->textTemplate('@Identity/email/reset.txt.twig')
            ->context(['account' => $account, 'url' => $url, 'sent_by' => $sentBy]));

        return $link;
    }

    /** Mails the invitation an account was made for. */
    public function sendInvitation(User $account, ?User $sentBy): AccountLink
    {
        [$link, $url] = $this->issue($account, LinkPurposeEnum::Invitation, $sentBy);

        $this->mailer->send((new TemplatedEmail())
            ->to(new Address((string) $account->getEmail()))
            ->subject('You have been added to vivutio')
            ->textTemplate('@Identity/email/invitation.txt.twig')
            ->context(['url' => $url, 'sent_by' => $sentBy]));

        return $link;
    }

    /**
     * Accepts an invitation: the person names themselves and chooses their
     * password, the account is verified and the link spent.
     *
     * @throws InvalidPersonException   when a name is refused
     * @throws InvalidPasswordException when the password is refused
     */
    public function accept(AccountLink $link, string $firstName, string $lastName, string $password, string $repeat): User
    {
        $account = $link->getAccount();
        $firstName = trim($firstName);
        $lastName = trim($lastName);

        foreach (['first_name' => [$firstName, 'first name'], 'last_name' => [$lastName, 'last name']] as $field => [$value, $words]) {
            if ('' === $value) {
                throw new InvalidPersonException($field, \sprintf('A %s cannot be empty.', $words));
            }
            if (mb_strlen($value) > User::NAME_MAX_LENGTH) {
                throw new InvalidPersonException($field, \sprintf('A %s can be at most %d characters.', $words, User::NAME_MAX_LENGTH));
            }
        }

        $named = (clone $account)->setFirstName($firstName)->setLastName($lastName);
        $this->rules->refuseIfBroken($named, $password, $repeat);

        $account->setFirstName($firstName)->setLastName($lastName)->setVerified(true);
        $account->setPassword($this->hasher->hashPassword($account, $password));
        $link->setUsedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $account;
    }

    /**
     * The link these two halves make, if it is one that still works: not
     * used, not expired, not withdrawn, of this purpose.
     */
    public function redeemable(string $selector, string $verifier, LinkPurposeEnum $purpose): ?AccountLink
    {
        $link = $this->links->findOneBy(['selector' => $selector]);

        if (null === $link
            || $link->getPurpose() !== $purpose
            || null !== $link->getUsedAt()
            || $link->getExpiresAt() <= new \DateTimeImmutable()
            || !hash_equals($link->getVerifierHash(), hash('sha256', $verifier))) {
            return null;
        }

        return $link;
    }

    /**
     * Sets the password the link was sent for, and spends the link. Every
     * other session of the account ends with the old password.
     *
     * @throws InvalidPasswordException
     */
    public function setPassword(AccountLink $link, string $password, string $repeat): User
    {
        $account = $link->getAccount();
        $this->rules->refuseIfBroken($account, $password, $repeat);

        $account->setPassword($this->hasher->hashPassword($account, $password));
        $link->setUsedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $account;
    }

    /**
     * @return array{AccountLink, string} the link, and the address to send
     */
    private function issue(User $account, LinkPurposeEnum $purpose, ?User $sentBy): array
    {
        $now = new \DateTimeImmutable();

        foreach ($this->links->findBy(['account' => $account, 'purpose' => $purpose, 'usedAt' => null]) as $earlier) {
            $this->entityManager->remove($earlier);
        }

        $selector = bin2hex(random_bytes(12));
        $verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $link = new AccountLink($account, $purpose, $selector, hash('sha256', $verifier), $now, $sentBy);

        $this->entityManager->persist($link);
        $this->entityManager->flush();

        $route = LinkPurposeEnum::Reset === $purpose ? 'identity_reset_link' : 'identity_invitation_link';

        return [$link, $this->urls->generate($route, ['selector' => $selector, 'verifier' => $verifier], UrlGeneratorInterface::ABSOLUTE_URL)];
    }

    private function askedJustNow(User $account): bool
    {
        $since = (new \DateTimeImmutable())->sub(new \DateInterval(self::QUIET));

        foreach ($this->links->findBy(['account' => $account, 'purpose' => LinkPurposeEnum::Reset, 'usedAt' => null]) as $link) {
            if ($link->getCreatedAt() > $since) {
                return true;
            }
        }

        return false;
    }
}
