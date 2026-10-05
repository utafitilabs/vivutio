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

namespace Vivutio\Bundle\IdentityBundle\Exception;

/**
 * A delete that is not done: the reference was not typed as it is, or the
 * record cannot go.
 */
final class DeletionRefusedException extends \DomainException
{
    public static function referenceMismatch(string $reference): self
    {
        return new self(\sprintf('Type %s exactly, to say it is the one to delete.', $reference));
    }

    public static function yourself(): self
    {
        return new self('You cannot delete yourself: another Super Admin can.');
    }
}
