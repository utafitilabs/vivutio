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

use Symfony\Bundle\SecurityBundle\Security;
use Vivutio\Bundle\IdentityBundle\Access\ConcernCatalogue;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Model\HeldConcern;
use Vivutio\Contracts\Access\Grant;
use Vivutio\Contracts\Access\Verb;

/**
 * What somebody may do right now, as the voters answer it for them: every
 * declared pair is asked about that person, so a pair their position stores
 * but a rule refuses (a module's pair before they belong to a department) is
 * not listed, and the record never claims more than a page would allow.
 */
final readonly class GrantsNowService
{
    public function __construct(
        private ConcernCatalogue $catalogue,
        private Security $security,
    ) {
    }

    /**
     * @return list<HeldConcern> in declaration order
     */
    public function of(User $person): array
    {
        $held = [];

        foreach ($this->catalogue->grouped() as $declaredBy => $concerns) {
            foreach ($concerns as $concern) {
                $verbs = array_values(array_filter(
                    Verb::cases(),
                    fn (Verb $verb): bool => $concern->supports($verb)
                        && $this->security->isGrantedForUser($person, (string) Grant::of($concern->key(), $verb)),
                ));

                if ([] !== $verbs) {
                    $held[] = new HeldConcern($declaredBy, $concern->label(), $concern->isSensitive(), $verbs);
                }
            }
        }

        return $held;
    }
}
