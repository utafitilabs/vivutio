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

namespace Vivutio\Bundle\IdentityBundle\EventListener;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Vivutio\Bundle\IdentityBundle\Access\ConcernCatalogue;
use Vivutio\Bundle\IdentityBundle\Entity\AuditEvent;
use Vivutio\Bundle\IdentityBundle\Entity\Department;
use Vivutio\Bundle\IdentityBundle\Entity\Organization;
use Vivutio\Bundle\IdentityBundle\Entity\Position;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\AuditKindEnum;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;
use Vivutio\Bundle\IdentityBundle\Service\AuditTrail;

/**
 * Writes the audit trail's line for every change a flush makes to who may do
 * what: an account added or changed (tier, position, department, posting,
 * name, address, password, deactivated), a position added or its grants or
 * name changed, a department added or its allowances, head or name changed,
 * and the organization recorded or its settings changed. Deletes have their
 * own log. Lines of one flush are written accounts, positions, departments,
 * then the organization.
 *
 * @see https://symfony.com/doc/current/doctrine/events.html#doctrine-lifecycle-listeners
 */
final readonly class AuditTrailListener
{
    private const array ORDER = [User::class, Position::class, Department::class, Organization::class];

    private const array ORGANIZATION_FIELDS = ['name' => 'name', 'shortName' => 'short name', 'timeZone' => 'time zone', 'country' => 'country'];

    public function __construct(
        private AuditTrail $trail,
        private ConcernCatalogue $catalogue,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();
        $lines = [];
        foreach (self::ORDER as $class) {
            foreach ($uow->getScheduledEntityInsertions() as $entity) {
                if ($entity instanceof $class) {
                    $lines[] = $this->added($entity);
                }
            }
            foreach ($uow->getScheduledEntityUpdates() as $entity) {
                if ($entity instanceof $class) {
                    array_push($lines, ...$this->changed($entity, array_filter($uow->getEntityChangeSet($entity), 'is_array')));
                }
            }
        }

        $meta = $em->getClassMetadata(AuditEvent::class);
        foreach (array_filter($lines) as $line) {
            $em->persist($line);
            $uow->computeChangeSet($meta, $line);
        }
    }

    private function added(object $entity): ?AuditEvent
    {
        return match (true) {
            $entity instanceof User => $this->trail->line(AuditKindEnum::Account, \sprintf('Added «%s» as %s%s', $entity->getFullName(), $entity->getTier()->label(), null === $entity->getDepartment() ? '' : ' in '.$entity->getDepartment()->getName())),
            $entity instanceof Position => $this->trail->line(AuditKindEnum::Position, \sprintf('Added the position «%s»', $entity->getName())),
            $entity instanceof Department => $this->trail->line(AuditKindEnum::Department, \sprintf('Added the department «%s»', $entity->getName())),
            $entity instanceof Organization => $this->trail->line(AuditKindEnum::Settings, \sprintf('Recorded the organization «%s»', $entity->getName())),
            default => null,
        };
    }

    /**
     * @param array<string, array{mixed, mixed}> $changes by field: before, after
     *
     * @return list<AuditEvent>
     */
    private function changed(object $entity, array $changes): array
    {
        $said = match (true) {
            $entity instanceof User => $this->account($entity, $changes),
            $entity instanceof Position => $this->position($entity, $changes),
            $entity instanceof Department => $this->department($entity, $changes),
            $entity instanceof Organization => $this->organization($changes),
            default => [],
        };
        $kind = match (true) {
            $entity instanceof User => AuditKindEnum::Account,
            $entity instanceof Position => AuditKindEnum::Position,
            $entity instanceof Department => AuditKindEnum::Department,
            default => AuditKindEnum::Settings,
        };

        return array_map(fn (string $line): AuditEvent => $this->trail->line($kind, $line), $said);
    }

    /**
     * @param array<string, array{mixed, mixed}> $changes
     *
     * @return list<string>
     */
    private function account(User $user, array $changes): array
    {
        $name = $user->getFullName();
        $said = [];
        if (isset($changes['firstName']) || isset($changes['lastName'])) {
            $before = trim(self::text($changes['firstName'][0] ?? $user->getFirstName()).' '.self::text($changes['lastName'][0] ?? $user->getLastName()));
            $said[] = \sprintf('Renamed %s to «%s»', $before, $name);
        }
        $from = self::tier($changes['tier'][0] ?? null);
        $to = self::tier($changes['tier'][1] ?? null);
        if (null !== $from && null !== $to) {
            $said[] = \sprintf('%s «%s» from %s to %s', self::rank($to) > self::rank($from) ? 'Raised' : 'Lowered', $name, $from->label(), $to->label());
        }
        if (isset($changes['position'])) {
            [$from, $to] = $changes['position'];
            if ($from instanceof Position && $to instanceof Position) {
                $said[] = \sprintf('Moved «%s» from the %s seat to the «%s» seat', $name, $from->getName(), $to->getName());
            } elseif ($to instanceof Position) {
                $said[] = \sprintf('Gave «%s» the «%s» seat', $name, $to->getName());
            } elseif ($from instanceof Position) {
                $said[] = \sprintf('Took «%s» out of the «%s» seat', $name, $from->getName());
            }
        }
        if (isset($changes['department'])) {
            [$from, $to] = $changes['department'];
            if ($from instanceof Department && $to instanceof Department) {
                $said[] = \sprintf('Moved «%s» from %s to «%s»', $name, $from->getName(), $to->getName());
            } elseif ($to instanceof Department) {
                $said[] = \sprintf('Put «%s» in «%s»', $name, $to->getName());
            } elseif ($from instanceof Department) {
                $said[] = \sprintf('Took «%s» out of «%s»', $name, $from->getName());
            }
        }
        if (isset($changes['postedKind']) || isset($changes['postedId'])) {
            $said[] = \sprintf('Changed where «%s» is posted', $name);
        }
        if (isset($changes['active'])) {
            $said[] = \sprintf('%s «%s»', true === $changes['active'][1] ? 'Reactivated' : 'Deactivated', $name);
        }
        if (isset($changes['email'])) {
            $said[] = \sprintf('Changed «%s»’s address from %s to «%s»', $name, self::text($changes['email'][0]), self::text($changes['email'][1]));
        }
        if (isset($changes['password'])) {
            $said[] = \sprintf('Changed «%s»’s password', $name);
        }

        return $said;
    }

    /**
     * @param array<string, array{mixed, mixed}> $changes
     *
     * @return list<string>
     */
    private function position(Position $position, array $changes): array
    {
        $said = [];
        if (isset($changes['name'])) {
            $said[] = \sprintf('Renamed the position %s to «%s»', self::text($changes['name'][0]), self::text($changes['name'][1]));
        }
        if (isset($changes['grants']) && '' !== ($diff = $this->diff($changes['grants']))) {
            $said[] = \sprintf('Changed what «%s» grants: %s', $position->getName(), $diff);
        }

        return $said;
    }

    /**
     * @param array<string, array{mixed, mixed}> $changes
     *
     * @return list<string>
     */
    private function department(Department $department, array $changes): array
    {
        $name = $department->getName();
        $said = [];
        if (isset($changes['name'])) {
            $said[] = \sprintf('Renamed the department %s to «%s»', self::text($changes['name'][0]), self::text($changes['name'][1]));
        }
        if (isset($changes['allows']) && '' !== ($diff = $this->diff($changes['allows']))) {
            $said[] = \sprintf('Changed what «%s» allows: %s', $name, $diff);
        }
        if (isset($changes['head'])) {
            $head = $changes['head'][1];
            $said[] = $head instanceof Position ? \sprintf('Made «%s»’s head the «%s» seat', $name, $head->getName()) : \sprintf('Left «%s» without a head', $name);
        }
        if (isset($changes['placeKind']) || isset($changes['placeId'])) {
            $said[] = \sprintf('Changed where «%s» works', $name);
        }

        return $said;
    }

    /**
     * @param array<string, array{mixed, mixed}> $changes
     *
     * @return list<string>
     */
    private function organization(array $changes): array
    {
        $said = [];
        foreach ($changes as $field => [$from, $to]) {
            $label = self::ORGANIZATION_FIELDS[$field] ?? mb_strtolower((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $field));
            $said[] = \sprintf('Changed the organization’s %s from %s to %s', $label, self::text($from, 'not set'), self::text($to, 'not set'));
        }

        return $said;
    }

    /**
     * What a list of pairs gained and lost: "added Positions · read; took away Notes · record".
     *
     * @param array{mixed, mixed} $change
     */
    private function diff(array $change): string
    {
        $from = \is_array($change[0]) ? array_filter($change[0], 'is_string') : [];
        $to = \is_array($change[1]) ? array_filter($change[1], 'is_string') : [];
        $parts = [];
        if ([] !== ($added = array_values(array_diff($to, $from)))) {
            $parts[] = 'added '.implode(', ', array_map($this->pair(...), $added));
        }
        if ([] !== ($gone = array_values(array_diff($from, $to)))) {
            $parts[] = 'took away '.implode(', ', array_map($this->pair(...), $gone));
        }

        return implode('; ', $parts);
    }

    /** "notes.record" as "Notes · record". */
    private function pair(string $pair): string
    {
        [$key, $verb] = array_pad(explode('.', $pair, 2), 2, '');
        $concern = $this->catalogue->concern($key);

        return null === $concern ? $pair : $concern->label().' · '.$verb;
    }

    private static function tier(mixed $value): ?TierEnum
    {
        return $value instanceof TierEnum ? $value : (\is_string($value) ? TierEnum::tryFrom($value) : null);
    }

    private static function rank(TierEnum $tier): int
    {
        return match ($tier) {
            TierEnum::SuperAdmin => 3,
            TierEnum::Admin => 2,
            TierEnum::Staff => 1,
        };
    }

    private static function text(mixed $value, string $empty = ''): string
    {
        return \is_scalar($value) && '' !== (string) $value ? (string) $value : $empty;
    }
}
