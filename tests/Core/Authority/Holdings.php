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

namespace Vivutio\Core\Tests\Core\Authority;

/**
 * What everybody holds at one moment: each account's tier, whether it can
 * sign in, and the pairs it holds. A deactivated account holds nothing.
 *
 * Compared before and after a write, it says whether anybody came out holding
 * more than the person who sent the write could already grant, which is
 * nothing beyond what they hold themselves.
 */
final readonly class Holdings
{
    private const array RANK = ['staff' => 0, 'admin' => 1, 'super_admin' => 2];

    /**
     * @param array<int, array{tier: string, active: bool, pairs: list<string>}> $people by account id
     */
    public function __construct(
        public array $people,
    ) {
    }

    /**
     * What one account holds: nothing at all when it cannot sign in.
     *
     * @return list<string>
     */
    public function pairsOf(?int $id): array
    {
        $person = null === $id ? null : ($this->people[$id] ?? null);

        return null !== $person && $person['active'] ? $person['pairs'] : [];
    }

    /**
     * Every way somebody holds more now than they did before, beyond what the
     * sender held before the write.
     *
     * @param int|null $sender the account the write was sent as, null for a stranger
     *
     * @return list<string> one sentence for each escalation
     */
    public function escalationsSince(self $before, ?int $sender): array
    {
        $grantable = $before->pairsOf($sender);
        $senderRank = null === $sender || !($before->people[$sender]['active'] ?? false) ? -1 : self::RANK[$before->people[$sender]['tier']];
        $found = [];

        foreach ($this->people as $id => $now) {
            if (!$now['active']) {
                continue;
            }

            $was = $before->people[$id] ?? null;
            $wasActive = null !== $was && $was['active'];
            $who = null === $was ? \sprintf('a new account %d', $id) : \sprintf('account %d', $id);

            $rankBefore = $wasActive ? self::RANK[$was['tier']] : -1;
            if (self::RANK[$now['tier']] > max($rankBefore, 0) && self::RANK[$now['tier']] > $senderRank) {
                $found[] = \sprintf('%s now holds the tier %s', $who, $now['tier']);
            }
            if (!$wasActive && null !== $was) {
                $found[] = \sprintf('%s can sign in again', $who);
            }

            $gained = array_values(array_diff($now['pairs'], $before->pairsOf($id), $grantable));
            if ([] !== $gained) {
                $found[] = \sprintf('%s gained %s', $who, implode(', ', $gained));
            }
        }

        return $found;
    }
}
