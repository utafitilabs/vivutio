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
 * The Shell's PDF service: a page, drawn in the Shell's print layout
 * (@Shell/print.html.twig), printed to a PDF. An installation that names no
 * browser to print with prints nothing, and a module goes on without the
 * document: it never fails for want of one.
 */
interface PdfPrinterInterface
{
    public const string SERVICE = 'shell.pdf_printer';

    /** Whether this installation can print: a browser is named and is there. */
    public function canPrint(): bool;

    /** The page as a PDF's bytes, or null when it cannot be printed. */
    public function print(string $html): ?string;
}
