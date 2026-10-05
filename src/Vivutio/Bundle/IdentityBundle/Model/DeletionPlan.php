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

use Vivutio\Contracts\Deletion\DeletionLine;
use Vivutio\Contracts\Deletion\DeletionSubject;

/**
 * What deleting a record would take and leave, said before anything happens.
 */
final readonly class DeletionPlan
{
    /**
     * @param list<DeletionLine> $goes
     * @param list<DeletionLine> $stays
     */
    public function __construct(
        public DeletionSubject $subject,
        public array $goes,
        public array $stays,
    ) {
    }

    /**
     * @return list<string>
     */
    public function whatGoes(): array
    {
        return array_map(static fn (DeletionLine $line): string => $line->text(), $this->goes);
    }

    /**
     * @return list<string>
     */
    public function whatStays(): array
    {
        return array_map(static fn (DeletionLine $line): string => $line->text(), $this->stays);
    }
}
