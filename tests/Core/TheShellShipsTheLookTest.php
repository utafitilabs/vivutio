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

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * The look is the shell's, and every class a template uses is one the shell's
 * stylesheet defines.
 *
 * A class nobody defines falls back to the browser's own look: black text,
 * grey buttons. It passes every test that reads the markup and looks broken
 * on the screen, which is why it is held here, by reading both as text.
 */
#[CoversNothing]
final class TheShellShipsTheLookTest extends TestCase
{
    private const string SHELL = __DIR__.'/../../src/Vivutio/Bundle/ShellBundle/public';

    public function testTheStylesheetTheScriptAndTheTypefaceShip(): void
    {
        foreach (['vivutio.css', 'vivutio.js', 'fonts/Figtree-normal.woff2', 'fonts/OFL.txt'] as $file) {
            self::assertFileExists(self::SHELL.'/'.$file);
        }

        self::assertStringContainsString('url(fonts/Figtree-normal.woff2)', self::stylesheet(), 'the stylesheet loads the typeface it ships');
    }

    public function testEveryClassACoreTemplateUsesIsDefinedByTheShell(): void
    {
        $defined = [];
        preg_match_all('/\.([a-z][a-z0-9-]*)/', (string) preg_replace('/url\([^)]*\)/', '', self::stylesheet()), $matches);
        foreach ($matches[1] as $class) {
            $defined[$class] = true;
        }

        $undefined = [];
        foreach (self::templates() as $template) {
            preg_match_all('/class="([^"{]*)"/', (string) file_get_contents($template), $uses);
            foreach ($uses[1] as $list) {
                foreach (preg_split('/\s+/', trim($list)) ?: [] as $class) {
                    if ('' !== $class && !str_starts_with($class, 'icon-') && !isset($defined[$class])) {
                        $undefined[] = basename($template).': '.$class;
                    }
                }
            }
        }

        self::assertSame([], $undefined, "These classes are used and defined by nobody:\n".implode("\n", $undefined));
    }

    private static function stylesheet(): string
    {
        return (string) file_get_contents(self::SHELL.'/vivutio.css');
    }

    /**
     * @return list<string>
     */
    private static function templates(): array
    {
        $templates = glob(__DIR__.'/../../src/Vivutio/Bundle/*/templates/*.html.twig') ?: [];
        self::assertNotSame([], $templates);

        return $templates;
    }
}
