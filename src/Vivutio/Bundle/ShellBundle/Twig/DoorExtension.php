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

namespace Vivutio\Bundle\ShellBundle\Twig;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The one way a template decides whether to draw a control: `door('C.V',
 * subject)`, which asks the same voters the control's route asks.
 *
 * One helper rather than the framework's is_granted() everywhere, so that a
 * test can find every control a template draws and hold it to the
 * declarations: a door on a pair nothing declares, or a declared pair that
 * neither a route nor a door uses, fails the route walk.
 *
 * @see https://symfony.com/doc/current/templates.html#templates-twig-extension
 * @see vendor/symfony/twig-bridge/Extension/SecurityExtension.php — is_granted(), which this narrows to one name
 */
final class DoorExtension extends AbstractExtension
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $authorization,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('door', $this->door(...)),
        ];
    }

    public function door(string $attribute, mixed $subject = null): bool
    {
        return $this->authorization->isGranted($attribute, $subject);
    }
}
