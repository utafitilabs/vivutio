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

namespace Vivutio\Core\Tests\Core;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;
use Vivutio\Bundle\ShellBundle\Service\PdfPrinterService;
use Vivutio\Contracts\Shell\PdfPrinterInterface;
use Vivutio\Core\Tests\Application\Kernel;

/**
 * The Shell's PDF service (the core's packaging table, #7): a page printed
 * to a PDF by the browser the installation names in PDF_BROWSER, drawn in a
 * layout that carries everything it needs, since the browser prints it from
 * a file of its own. Without a browser nothing is printed and nothing fails:
 * a module sends its message without the document.
 */
final class ThePdfPrinterTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testAPageIsPrintedByTheBrowserTheInstallationNames(): void
    {
        $printer = static::getContainer()->get(PdfPrinterInterface::class);
        self::assertInstanceOf(PdfPrinterInterface::class, $printer);
        self::assertTrue($printer->canPrint(), 'PDF_BROWSER names a browser in this environment');

        $pdf = $printer->print($this->letter());
        self::assertNotNull($pdf);
        self::assertStringStartsWith('%PDF-', $pdf);
    }

    public function testWithoutABrowserNothingIsPrintedAndNothingFails(): void
    {
        foreach (['', '/nowhere/a-browser'] as $browser) {
            $printer = new PdfPrinterService($browser);
            self::assertFalse($printer->canPrint(), $browser);
            self::assertNull($printer->print('<p>A request</p>'), $browser);
        }
    }

    /** The layout carries its type and its styles: the browser prints a file that can reach nothing else. */
    public function testThePrintLayoutCarriesEverythingItNeeds(): void
    {
        $html = $this->letter();

        self::assertStringContainsString('<title>RQ-0001</title>', $html);
        self::assertStringContainsString('@page', $html);
        self::assertStringContainsString('src:url(data:font/woff2;base64,', $html);
        self::assertStringNotContainsString('<link', $html);
        self::assertStringNotContainsString('<script', $html);
    }

    private function letter(): string
    {
        $twig = static::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        return $twig->createTemplate("{% extends '@Shell/print.html.twig' %}{% block title %}RQ-0001{% endblock %}{% block document %}<h1>Room request RQ-0001</h1>{% endblock %}")->render();
    }
}
