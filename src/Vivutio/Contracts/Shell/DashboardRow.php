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

namespace Vivutio\Contracts\Shell;

/**
 * One line of a dashboard card: what it is about in bold with a line under
 * it, then plain cells, then a chip at the end.
 */
final readonly class DashboardRow
{
    public const array TONES = ['', 'ok', 'warn', 'danger'];

    /**
     * @param list<string> $cells plain text, in order
     * @param string       $tone  the chip's: '' plain, 'ok', 'warn' or 'danger'
     */
    public function __construct(
        public string $title,
        public string $detail = '',
        public array $cells = [],
        public ?string $chip = null,
        public string $tone = '',
    ) {
        if (!\in_array($tone, self::TONES, true)) {
            throw new \InvalidArgumentException(\sprintf('A chip is plain, "ok", "warn" or "danger", not "%s".', $tone));
        }
    }
}
