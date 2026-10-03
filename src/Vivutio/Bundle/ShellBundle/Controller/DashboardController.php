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
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

/**
 * Where everybody lands: the organization's dashboard for whoever holds
 * `dashboard.read`, and their own for everybody else.
 *
 * The grant decides which page, never whether there is one, so neither route
 * names a pair; the route walk lists both with that reason. What fills the
 * dashboards is contributed by the packages installed, and the shell names
 * none of them.
 *
 * It extends nothing and is handed what it uses, as the framework's own
 * controllers are.
 *
 * @see vendor/symfony/framework-bundle/Controller/TemplateController.php
 */
final readonly class DashboardController
{
    public const string HOME = 'shell_dashboard';
    public const string MINE = 'shell_my_dashboard';
    public const string READ = 'dashboard.read';

    public function __construct(
        private Environment $twig,
        private AuthorizationCheckerInterface $authorization,
    ) {
    }

    #[Route('/', name: self::HOME, methods: ['GET'])]
    public function home(): Response
    {
        if (!$this->authorization->isGranted(self::READ)) {
            return $this->mine();
        }

        return new Response($this->twig->render('@Shell/dashboard/organization.html.twig'));
    }

    /** One's own dashboard, at its own address for whoever lands on the organization's. */
    #[Route('/me', name: self::MINE, methods: ['GET'])]
    public function mine(): Response
    {
        return new Response($this->twig->render('@Shell/dashboard/mine.html.twig'));
    }
}
