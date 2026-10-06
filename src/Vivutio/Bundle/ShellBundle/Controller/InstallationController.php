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

namespace Vivutio\Bundle\ShellBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;
use Vivutio\Bundle\ShellBundle\Service\InstallationService;

/**
 * Settings › Installation: what this installation runs and on what, read by
 * the tiers like the rest of Settings.
 */
final readonly class InstallationController
{
    public const string INSTALLATION = 'shell_settings_installation';

    public function __construct(
        private Environment $twig,
        private InstallationService $installation,
    ) {
    }

    #[Route('/settings/installation', name: self::INSTALLATION, methods: ['GET'])]
    #[IsGranted('settings.read')]
    public function installation(): Response
    {
        $packages = $this->installation->packages();

        return new Response($this->twig->render('@Shell/settings/installation.html.twig', [
            'packages' => $packages,
            'core' => $packages[0] ?? null,
            'modules' => \count(array_filter($packages, static fn ($package): bool => str_ends_with($package->name, '-module'))),
            'runtime' => $this->installation->runtime(),
        ]));
    }
}
