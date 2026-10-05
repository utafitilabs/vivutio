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

namespace Vivutio\Bundle\IdentityBundle\Service;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;
use Vivutio\Bundle\IdentityBundle\Exception\DeletionRefusedException;
use Vivutio\Bundle\IdentityBundle\Model\DeletionPageContext;

/**
 * A record's delete page, the same for the core's records and every
 * package's: what goes and what stays, counted; the reference typed to
 * confirm; then the register, with a message. A package's controller gates
 * its route with identity.delete on the record and hands the request here.
 */
final readonly class DeletionPageService
{
    public const string DELETED = 'vivutio.deleted';
    private const string TOKEN = 'identity_delete';

    public function __construct(
        private Environment $twig,
        private DeletionService $deletions,
        private CsrfTokenManagerInterface $tokens,
    ) {
    }

    public function respond(Request $request, object $record, DeletionPageContext $context): Response
    {
        if (!$request->isMethod('POST')) {
            return $this->page($record, $context);
        }
        $payload = $request->getPayload();
        if (!$this->tokens->isTokenValid(new CsrfToken(self::TOKEN, $payload->getString('_token')))) {
            return $this->page($record, $context, expired: true);
        }

        try {
            $line = $this->deletions->delete($record, $payload->getString('reference'));
        } catch (DeletionRefusedException $refusal) {
            return $this->page($record, $context, $refusal->getMessage());
        }

        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add(self::DELETED, \sprintf('%s %s is deleted.', ucfirst($line->getKind()), $line->getTitle()));
        }

        return new RedirectResponse($context->done);
    }

    private function page(object $record, DeletionPageContext $context, ?string $wrong = null, bool $expired = false): Response
    {
        return new Response($this->twig->render('@Identity/deletion/delete.html.twig', [
            'plan' => $this->deletions->plan($record),
            'context' => $context,
            'wrong' => $wrong,
            'expired' => $expired,
        ]), null === $wrong && !$expired ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
