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

use Vivutio\Bundle\IdentityBundle\Access\ConcernCatalogue;
use Vivutio\Bundle\IdentityBundle\Entity\Position;
use Vivutio\Bundle\IdentityBundle\Model\MatrixGroup;
use Vivutio\Bundle\IdentityBundle\Model\MatrixRow;
use Vivutio\Bundle\IdentityBundle\Model\PositionSummary;
use Vivutio\Bundle\IdentityBundle\Repository\UserRepository;
use Vivutio\Contracts\Access\Grant;
use Vivutio\Contracts\Access\Verb;

/**
 * The positions as their pages draw them: the matrix, generated from what the
 * installed packages declare and nothing else, and each position summed up.
 *
 * A cell is drawn only where a position may carry the pair: a verb a concern
 * does not declare has no cell, and neither does one only the tiers hold. A
 * concern with no cell at all has no row.
 */
final readonly class PositionMatrixService
{
    public function __construct(
        private ConcernCatalogue $catalogue,
        private UserRepository $users,
    ) {
    }

    /**
     * @return list<MatrixGroup>
     */
    public function matrix(): array
    {
        $groups = [];

        foreach ($this->catalogue->grouped() as $declaredBy => $concerns) {
            $rows = [];
            foreach ($concerns as $concern) {
                $cells = [];
                foreach (Verb::cases() as $verb) {
                    $pair = (string) Grant::of($concern->key(), $verb);
                    $cells[$verb->value] = $concern->supports($verb) && !$concern->isTierOnly($verb) ? $pair : null;
                }

                if ([] !== array_filter($cells)) {
                    $rows[] = new MatrixRow($concern->label(), $concern->isSensitive(), $cells);
                }
            }

            if ([] !== $rows) {
                $groups[] = new MatrixGroup($declaredBy, $rows);
            }
        }

        return $groups;
    }

    /**
     * @param list<Position> $positions
     *
     * @return list<PositionSummary>
     */
    public function summaries(array $positions): array
    {
        $carried = $this->catalogue->positionPairs();

        return array_map(function (Position $position) use ($carried): PositionSummary {
            $byVerb = [];
            $sensitive = [];

            foreach (array_intersect($position->getGrants(), $carried) as $pair) {
                $grant = Grant::tryParse($pair);
                if (null === $grant) {
                    continue;
                }
                $byVerb[$grant->verb->value] = ($byVerb[$grant->verb->value] ?? 0) + 1;
                if ($this->catalogue->isSensitive($grant->concern)) {
                    $sensitive[$grant->concern] = true;
                }
            }

            $ordered = [];
            foreach (Verb::cases() as $verb) {
                if (isset($byVerb[$verb->value])) {
                    $ordered[$verb->value] = $byVerb[$verb->value];
                }
            }

            return new PositionSummary($position, $ordered, \count($sensitive), $this->users->findBy(['position' => $position], ['firstName' => 'ASC', 'lastName' => 'ASC']));
        }, $positions);
    }
}
