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

namespace Vivutio\Bundle\IdentityBundle\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Model\TierChange;
use Vivutio\Bundle\IdentityBundle\Service\UserService;

/**
 * What one person may do to another's account: the rules between tiers, asked
 * as attributes so that a route, a control and a command ask the same
 * question and get the same answer, with its reason.
 *
 * These are not pairs in the grants matrix. They are the tiers' own rules,
 * which no position can grant or take away; a position decides only whether
 * Staff reach a page at all, and is asked separately.
 *
 * The rules themselves are the tier's ({@see \Vivutio\Bundle\IdentityBundle\Enum\TierEnum}),
 * and the last-Super-Admin rule is the account service's, so this voter only
 * puts them where Symfony asks.
 *
 * @see https://symfony.com/doc/current/security/voters.html
 * @see vendor/symfony/security-core/Authorization/Voter/Voter.php — supports() and voteOnAttribute(), and the Vote a reason is added to
 *
 * @extends Voter<string, mixed>
 */
final class AccountVoter extends Voter
{
    /** Change the account's name or address, send it a reset link, or bring it back. Subject: the account. */
    public const string ACT_ON = 'identity.act-on-account';

    /** Subject: a {@see TierChange}. */
    public const string CHANGE_TIER = 'identity.change-tier';

    /** Subject: the account. */
    public const string DEACTIVATE = 'identity.deactivate';

    /** Sign in as the account, to see what its holder sees. Subject: the account. */
    public const string SIGN_IN_AS = 'identity.sign-in-as';

    private const array ATTRIBUTES = [self::ACT_ON, self::CHANGE_TIER, self::DEACTIVATE, self::SIGN_IN_AS];

    public function __construct(
        private readonly UserService $accounts,
    ) {
    }

    /**
     * Every one of its attributes, whatever the subject: a question this
     * voter owns about something it cannot read is refused here, never left
     * to a voter that would answer it by accident.
     */
    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, self::ATTRIBUTES, true);
    }

    public function supportsAttribute(string $attribute): bool
    {
        return \in_array($attribute, self::ATTRIBUTES, true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $actor = $token->getUser();
        if (!$actor instanceof User) {
            $vote?->addReason('Nobody is signed in with an account of this organization.');

            return false;
        }

        return match ($attribute) {
            self::ACT_ON => $this->mayActOn($actor, $subject, $vote),
            self::CHANGE_TIER => $this->mayChangeTier($actor, $subject, $vote),
            self::DEACTIVATE => $this->mayDeactivate($actor, $subject, $vote),
            self::SIGN_IN_AS => $this->maySignInAs($actor, $subject, $vote),
            default => false,
        };
    }

    private function mayActOn(User $actor, mixed $subject, ?Vote $vote): bool
    {
        if (!$subject instanceof User) {
            $vote?->addReason('The question is not about an account.');

            return false;
        }

        if (!$actor->getTier()->mayActOnAccountOf($subject->getTier())) {
            $vote?->addReason(\sprintf('%s cannot act on the account of %s, a %s: nobody reaches above their own tier.', $actor->getTier()->label(), $subject->getFullName(), $subject->getTier()->label()));

            return false;
        }

        return true;
    }

    private function mayChangeTier(User $actor, mixed $subject, ?Vote $vote): bool
    {
        if (!$subject instanceof TierChange) {
            $vote?->addReason('The question is not about a change of tier.');

            return false;
        }

        $held = $subject->account->getTier();

        if (!$actor->getTier()->mayChangeTier($held, $subject->to)) {
            $vote?->addReason(\sprintf('%s cannot move %s from %s to %s: only a Super Admin makes or changes a Super Admin, Admins change Admins and Staff, and Staff change no tier.', $actor->getTier()->label(), $subject->account->getFullName(), $held->label(), $subject->to->label()));

            return false;
        }

        if ($held !== $subject->to && $this->accounts->isLastActiveSuperAdmin($subject->account)) {
            $vote?->addReason(\sprintf('%s is the only active Super Admin, so their tier cannot be lowered. Make somebody else a Super Admin first.', $subject->account->getFullName()));

            return false;
        }

        return true;
    }

    private function mayDeactivate(User $actor, mixed $subject, ?Vote $vote): bool
    {
        if (!$this->mayActOn($actor, $subject, $vote)) {
            return false;
        }

        \assert($subject instanceof User);

        if ($this->accounts->isLastActiveSuperAdmin($subject)) {
            $vote?->addReason(\sprintf('%s is the only active Super Admin, so the account cannot be deactivated. Make somebody else a Super Admin first.', $subject->getFullName()));

            return false;
        }

        return true;
    }

    private function maySignInAs(User $actor, mixed $subject, ?Vote $vote): bool
    {
        if (!$subject instanceof User) {
            $vote?->addReason('The question is not about an account.');

            return false;
        }

        if (!$actor->getTier()->maySignInAsAnother()) {
            $vote?->addReason(\sprintf('Only a Super Admin signs in as another person; %s is %s.', $actor->getFullName(), $actor->getTier()->label()));

            return false;
        }

        return true;
    }
}
