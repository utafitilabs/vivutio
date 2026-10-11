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

namespace Vivutio\Bundle\ShellBundle\Service;

use UtafitiLabs\PdfScribe\Exception\PdfGenerationException;
use UtafitiLabs\PdfScribe\Service\PdfGeneratorService;
use Vivutio\Contracts\Shell\PdfPrinterInterface;

/**
 * Prints with the browser the installation names in PDF_BROWSER, through
 * utafitilabs/pdf-scribe-bundle's generator, built here rather than through
 * that bundle's configuration so an installation registers nothing more. A
 * browser that is not named, not there, or fails, prints nothing.
 */
final readonly class PdfPrinterService implements PdfPrinterInterface
{
    private const int TIMEOUT = 60;

    public function __construct(private ?string $browser)
    {
    }

    public function canPrint(): bool
    {
        return null !== $this->browser && '' !== $this->browser && is_file($this->browser) && is_executable($this->browser);
    }

    public function print(string $html): ?string
    {
        if (!$this->canPrint()) {
            return null;
        }

        try {
            return (new PdfGeneratorService((string) $this->browser, self::TIMEOUT, ['print-background' => true, 'no-pdf-header-footer' => true]))->fromHtml($html);
        } catch (PdfGenerationException) {
            return null;
        }
    }
}
