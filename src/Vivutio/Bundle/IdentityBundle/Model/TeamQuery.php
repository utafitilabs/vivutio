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

namespace Vivutio\Bundle\IdentityBundle\Model;

use Symfony\Component\HttpFoundation\Request;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;

/**
 * What the team list is asked for, read from the address: a filtered list is
 * one somebody can bookmark, share and come back to.
 *
 * It holds what was asked, not what the viewer may use: whether a tier or an
 * address search is honoured is decided where the list is drawn.
 */
final readonly class TeamQuery
{
    public const string NO_POSITION = 'none';
    public const string ACTIVE = 'active';
    public const string DEACTIVATED = 'deactivated';

    public function __construct(
        public string $q = '',
        public ?TierEnum $tier = null,
        public ?string $position = null,
        public ?string $account = null,
        public int $page = 1,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $account = $request->query->getString('account');
        $position = $request->query->getString('position');

        return new self(
            q: trim($request->query->getString('q')),
            tier: TierEnum::tryFrom($request->query->getString('tier')),
            position: '' === $position ? null : $position,
            account: \in_array($account, [self::ACTIVE, self::DEACTIVATED], true) ? $account : null,
            page: max(1, $request->query->getInt('page', 1)),
        );
    }

    /**
     * The address parameters of this query with some replaced, for a link
     * that changes one thing and keeps the rest.
     *
     * @param array<string, string|int|null> $replace
     *
     * @return array<string, string|int>
     */
    public function toParams(array $replace = []): array
    {
        $params = array_merge([
            'q' => $this->q,
            'tier' => $this->tier?->value,
            'position' => $this->position,
            'account' => $this->account,
            'page' => 1 === $this->page ? null : $this->page,
        ], $replace);

        return array_filter($params, static fn (string|int|null $value): bool => null !== $value && '' !== $value);
    }
}
