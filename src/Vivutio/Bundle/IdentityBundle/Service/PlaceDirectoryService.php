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

use Symfony\Component\Uid\Uuid;
use Vivutio\Bundle\IdentityBundle\Entity\Department;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Exception\InvalidPlaceException;
use Vivutio\Bundle\IdentityBundle\Model\PlaceGroup;
use Vivutio\Contracts\Place\PlaceInterface;
use Vivutio\Contracts\Place\PlaceSourceInterface;

/**
 * Every place people are posted at, from every source: the core's offices
 * and whatever a package offers. A place is kept elsewhere as its kind and
 * its id, and named here.
 */
final readonly class PlaceDirectoryService
{
    /** How a posting reads once the package that offered its place is gone. */
    public const string UNOFFERED = 'A place no installed package offers';

    /**
     * @param iterable<PlaceSourceInterface> $sources
     */
    public function __construct(private iterable $sources)
    {
    }

    /**
     * The places on offer, under their kind's heading, offices first.
     *
     * @return list<PlaceGroup>
     */
    public function groups(): array
    {
        $groups = [];
        foreach ($this->sources as $source) {
            $places = [...$source->places()];
            if ([] !== $places) {
                $groups[] = new PlaceGroup($source->kind(), $source->label(), array_values($places));
            }
        }

        return $groups;
    }

    /** A place by kind and id, offered now or not; null where nothing answers for it. */
    public function find(?string $kind, ?string $id): ?PlaceInterface
    {
        if (null === $kind || null === $id) {
            return null;
        }

        return $this->source($kind)?->find($id);
    }

    public function postingOf(User $user): ?PlaceInterface
    {
        return $this->find($user->getPostedKind(), $user->getPostedId());
    }

    public function placeOf(Department $department): ?PlaceInterface
    {
        return $this->find($department->getPlaceKind(), $department->getPlaceId());
    }

    /** What a kept place is called, null for nowhere, and the unoffered phrase for a kind no package answers for. */
    public function nameOf(?string $kind, ?string $id): ?string
    {
        if (null === $kind) {
            return null;
        }

        return $this->find($kind, $id)?->getName() ?? self::UNOFFERED;
    }

    /**
     * Refuses a place its kind's source does not offer now.
     *
     * @throws InvalidPlaceException
     */
    public function assertOffered(?PlaceInterface $place, string $field): void
    {
        if (null === $place) {
            return;
        }

        foreach ($this->source($place->getPlaceKind())?->places() ?? [] as $offered) {
            if ($offered->getPlaceId() === $place->getPlaceId()) {
                return;
            }
        }

        throw new InvalidPlaceException($field, \sprintf('%s is not a place people are posted at here: choose one of the places offered.', $place->getName()));
    }

    /**
     * A place as a form sends it, "<kind>:<id>", or none for an empty value.
     *
     * @throws InvalidPlaceException
     */
    public function typed(string $typed, string $field): ?PlaceInterface
    {
        if ('' === $typed) {
            return null;
        }

        [$kind, $id] = array_pad(explode(':', $typed, 2), 2, '');
        $place = Uuid::isValid($id) ? $this->find($kind, $id) : null;
        if (null === $place) {
            throw new InvalidPlaceException($field, 'Choose one of the places offered.');
        }
        $this->assertOffered($place, $field);

        return $place;
    }

    private function source(string $kind): ?PlaceSourceInterface
    {
        foreach ($this->sources as $source) {
            if ($source->kind() === $kind) {
                return $source;
            }
        }

        return null;
    }
}
