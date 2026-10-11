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
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;

/**
 * Who reads the audit trail: Super Admins alone, as ruled (11 October 2026).
 *
 * @extends Voter<string, mixed>
 */
final class AuditVoter extends Voter
{
    public const string READ = 'identity.audit';

    public function supportsAttribute(string $attribute): bool
    {
        return self::READ === $attribute;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::READ === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $actor = $token->getUser();
        if ($actor instanceof User && TierEnum::SuperAdmin === $actor->getTier()) {
            return true;
        }
        $vote?->addReason('Only a Super Admin reads the audit log.');

        return false;
    }
}
