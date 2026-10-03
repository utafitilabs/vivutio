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
use Vivutio\Bundle\IdentityBundle\Access\ConcernCatalogue;
use Vivutio\Bundle\IdentityBundle\Entity\Position;
use Vivutio\Bundle\IdentityBundle\Exception\InvalidPositionException;
use Vivutio\Bundle\IdentityBundle\Exception\UngrantablePairsException;
use Vivutio\Contracts\Access\Grant;

/**
 * Every way a position comes into being or changes what it grants.
 *
 * A position carries the pairs the grants matrix offers and nothing else. The
 * matrix draws only those, but a request can name anything, so the same rule
 * is held here where the write happens: a crafted request, an import and a
 * screen are refused alike.
 *
 * Who may write positions at all is the voter's question, asked before this.
 */
final readonly class PositionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ConcernCatalogue $catalogue,
    ) {
    }

    /**
     * @param list<string> $grants each written "<concern>.<verb>"
     *
     * @throws InvalidPositionException  when the name is empty or too long
     * @throws UngrantablePairsException when a pair is one no position may carry
     */
    public function create(string $name, array $grants): Position
    {
        $name = self::name($name);
        $this->refuseUngrantable($grants);

        $position = (new Position())->setName($name)->setGrants($grants);

        $this->entityManager->persist($position);
        $this->entityManager->flush();

        return $position;
    }

    /**
     * Replaces what the position grants with these pairs.
     *
     * @param list<string> $grants each written "<concern>.<verb>"
     *
     * @throws UngrantablePairsException when a pair is one no position may carry
     */
    public function changeGrants(Position $position, array $grants): void
    {
        $this->refuseUngrantable($grants);

        $position->setGrants($grants);

        $this->entityManager->flush();
    }

    /**
     * The name and what the position grants, changed together, as its
     * configure page saves them.
     *
     * @param list<string> $grants each written "<concern>.<verb>"
     *
     * @throws InvalidPositionException  when the name is empty or too long
     * @throws UngrantablePairsException when a pair is one no position may carry
     */
    public function change(Position $position, string $name, array $grants): void
    {
        $name = self::name($name);
        $this->refuseUngrantable($grants);

        $position->setName($name)->setGrants(array_values(array_unique($grants)));

        $this->entityManager->flush();
    }

    /**
     * @throws InvalidPositionException
     */
    private static function name(string $name): string
    {
        $name = trim($name);
        if ('' === $name) {
            throw new InvalidPositionException('name', 'A position is known by its name: it cannot be empty.');
        }
        if (mb_strlen($name) > Position::NAME_MAX_LENGTH) {
            throw new InvalidPositionException('name', \sprintf('A name can be at most %d characters.', Position::NAME_MAX_LENGTH));
        }

        return $name;
    }

    /**
     * @param list<string> $grants
     */
    private function refuseUngrantable(array $grants): void
    {
        $refused = [];

        foreach ($grants as $pair) {
            $grant = Grant::tryParse($pair);

            $refused[$pair] = match (true) {
                null === $grant => 'it is not a pair, which is written "<concern>.<verb>"',
                !$this->catalogue->has($grant) => 'nothing in this installation declares it',
                $this->catalogue->isTierOnly($pair) => 'only Admins and Super Admins hold it, and no position does',
                default => null,
            };
        }

        $refused = array_filter($refused, static fn (?string $reason): bool => null !== $reason);

        if ([] !== $refused) {
            throw new UngrantablePairsException($refused);
        }
    }
}
