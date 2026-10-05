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

/**
 * Who deletes outright: a Super Admin, read from the signed-in account
 * itself, so one signed in as another person is that person and is refused;
 * and never their own account. Asked with a record, or with none for the
 * Deletions page.
 *
 * @extends Voter<string, mixed>
 */
final class DeletionVoter extends Voter
{
    public const string DELETE = 'identity.delete';

    public function supportsAttribute(string $attribute): bool
    {
        return self::DELETE === $attribute;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::DELETE === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $actor = $token->getUser();
        if (!$actor instanceof User || !$actor->getTier()->mayDelete()) {
            $vote?->addReason('Only a Super Admin deletes.');

            return false;
        }
        if ($subject === $actor || ($subject instanceof User && $subject->getId() === $actor->getId())) {
            $vote?->addReason('Nobody deletes their own account; another Super Admin can.');

            return false;
        }

        return true;
    }
}
