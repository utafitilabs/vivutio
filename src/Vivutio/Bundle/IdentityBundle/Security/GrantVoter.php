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
use Vivutio\Bundle\IdentityBundle\Access\ConcernCatalogue;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Contracts\Access\Grant;

/**
 * Whether somebody holds a pair, `<concern>.<verb>`, and why not when they
 * do not. It fails closed: a question it cannot answer is a refusal.
 *
 * It answers only pairs somebody declared. A gate on any other string is a
 * typo or a removed package; this voter abstains on it, nobody else answers
 * it, and the decision manager refuses it to everybody.
 *
 * In order:
 *
 *   1. A deactivated account holds nothing.
 *   2. The tiers above the matrix hold every declared pair. They are asked
 *      first so that whoever writes the positions is never described by one
 *      and cannot lock themselves out of the page that would let them back in.
 *   3. A pair only the tiers hold is refused to everybody else, even when a
 *      position still stores it.
 *   4. Staff hold what their position grants and nothing more.
 *   5. A module's pair also needs a department: everybody but the tiers
 *      belongs to exactly one, and a person without one reaches no module.
 *      It holds when their department, or one they support, allows it; a
 *      new department allows nothing.
 *
 * Where a grant reaches, a place or a department's records, is asked of the
 * subject once postings and departments are stored. Until then a position's
 * grant on the core's own concerns reaches the organization, the one scope
 * the core can answer without them.
 *
 * @see https://symfony.com/doc/current/security/voters.html
 * @see vendor/symfony/security-core/Authorization/Voter/Voter.php — supports() and voteOnAttribute(), and the Vote a reason is added to
 *
 * @extends Voter<string, mixed>
 */
final class GrantVoter extends Voter
{
    public function __construct(
        private readonly ConcernCatalogue $catalogue,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $this->supportsAttribute($attribute);
    }

    /**
     * Only a declared pair. The base voter claims every attribute here, and a
     * voter that claims a question it never answers is a second voter on it,
     * the overlap the route walk refuses.
     *
     * @see vendor/symfony/security-core/Authorization/Voter/Voter.php — supportsAttribute() returns true unless overridden
     */
    public function supportsAttribute(string $attribute): bool
    {
        $grant = Grant::tryParse($attribute);

        return null !== $grant && $this->catalogue->has($grant);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            $vote?->addReason('Nobody is signed in with an account of this organization.');

            return false;
        }

        if (!$user->isActive()) {
            $vote?->addReason(\sprintf('The account of %s is deactivated, and a deactivated account holds nothing.', $user->getFullName()));

            return false;
        }

        if ($user->getTier()->holdsEveryPermission()) {
            return true;
        }

        if ($this->catalogue->isTierOnly($attribute)) {
            $vote?->addReason(\sprintf('Only Admins and Super Admins hold "%s"; no position does.', $attribute));

            return false;
        }

        $position = $user->getPosition();
        if (null === $position) {
            $vote?->addReason(\sprintf('%s holds no position, so they hold nothing.', $user->getFullName()));

            return false;
        }

        if (!$position->grants($attribute)) {
            $vote?->addReason(\sprintf('The position %s does not grant "%s".', $position->getName(), $attribute));

            return false;
        }

        $grant = Grant::parse($attribute);
        $module = $this->catalogue->moduleOf($grant->concern);
        if (null === $module) {
            return true;
        }

        $department = $user->getDepartment();
        if (null === $department) {
            $vote?->addReason(\sprintf('"%s" belongs to the %s module, and %s belongs to no department, so they reach no module.', $attribute, $module, $user->getFullName()));

            return false;
        }

        foreach ([$department, ...$user->getSupports()] as $reaching) {
            if ($reaching->allows($attribute)) {
                return true;
            }
        }

        $vote?->addReason(\sprintf('"%s" belongs to the %s module, and neither %s nor a department %s supports allows it.', $attribute, $module, $department->getName(), $user->getFullName()));

        return false;
    }
}
