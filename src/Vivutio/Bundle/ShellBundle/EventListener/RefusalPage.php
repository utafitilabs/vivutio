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

namespace Vivutio\Bundle\ShellBundle\EventListener;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Twig\Environment;

/**
 * A signed-in person who opens a page their position does not reach is
 * answered inside the frame, "There is nothing here you can open", with a
 * 403, instead of the framework's bare error page.
 *
 * A listener rather than an error template: the documented override of the
 * error templates is the installation's file, and renders only without debug,
 * where nobody trying a position would see it. The exception event is the
 * documented seam for taking over an exception's response.
 *
 * Only a page. The firewall's own listener, at a higher priority, has already
 * sent a stranger to sign in and turned a signed-in refusal into an
 * AccessDeniedHttpException; this one answers only a GET that asked for HTML,
 * so a refused form keeps its own answer.
 *
 * @see https://symfony.com/doc/current/controller/error_pages.html#working-with-the-kernel-exception-event
 * @see vendor/symfony/security-http/Firewall/ExceptionListener.php — the firewall's listener, priority 1
 */
final readonly class RefusalPage
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    public function onException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest()
            || !$event->getThrowable() instanceof AccessDeniedHttpException
            || !$request->isMethod('GET')
            || 'html' !== $request->getPreferredFormat()) {
            return;
        }

        $event->setResponse(new Response($this->twig->render('@Shell/refusal.html.twig'), Response::HTTP_FORBIDDEN));
    }
}
