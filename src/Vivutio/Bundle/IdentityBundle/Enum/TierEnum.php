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

namespace Vivutio\Bundle\IdentityBundle\Enum;

/**
 * Who may administer the organization's people: three tiers, and only three.
 *
 * A tier is not a position. A position bundles the permissions a person works
 * with, and can never confer a tier, whatever it holds. A tier says who stands
 * above the matrix of positions and who is inside it.
 *
 * It is not ownership either. Whoever owns the business and whoever runs the
 * system are often different people, and an owner who does not run it is Staff
 * in a position that carries the business's permissions.
 *
 * The rules between tiers are here, as questions a tier answers about another,
 * so that every place that enforces one asks the same thing.
 */
enum TierEnum: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::Staff => 'Staff',
        };
    }

    /** One sentence saying what the tier is, for whoever is about to give it. */
    public function description(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Above the matrix. Holds every permission by tier, and may sign in as another person to see what they see.',
            self::Admin => 'Above the matrix. Holds every permission by tier, so that a position composed wrongly can always be put right.',
            self::Staff => 'Everybody else, including the people who run the place. Holds exactly what their position grants.',
        };
    }

    /** Whether the tier holds every declared permission without a position granting it. */
    public function holdsEveryPermission(): bool
    {
        return self::Staff !== $this;
    }

    public function maySignInAsAnother(): bool
    {
        return self::SuperAdmin === $this;
    }

    /**
     * Whether somebody of this tier may act on an account of that tier:
     * change its name or address, send it a reset link, deactivate it or
     * bring it back.
     *
     * Nobody reaches upward. So no permission over the directory is a way to
     * change an administrator's address and send the reset link to it.
     *
     * For Staff acting on Staff this is the tier's answer only; whether their
     * position grants the act is a separate question.
     */
    public function mayActOnAccountOf(self $target): bool
    {
        return match ($target) {
            self::SuperAdmin => self::SuperAdmin === $this,
            self::Admin => self::Staff !== $this,
            self::Staff => true,
        };
    }

    /**
     * Whether somebody of this tier may change an account from the tier it
     * holds to the tier it would be given.
     *
     * Only a Super Admin makes a Super Admin or changes one. Admins are peers:
     * they make and unmake Admins, the one who made them included. Staff
     * change no tier.
     */
    public function mayChangeTier(self $held, self $given): bool
    {
        return match ($this) {
            self::SuperAdmin => true,
            self::Admin => self::SuperAdmin !== $held && self::SuperAdmin !== $given,
            self::Staff => false,
        };
    }
}
