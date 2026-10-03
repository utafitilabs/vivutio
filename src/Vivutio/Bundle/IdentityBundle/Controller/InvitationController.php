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

namespace Vivutio\Bundle\IdentityBundle\Controller;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;
use Vivutio\Bundle\IdentityBundle\Entity\AccountLink;
use Vivutio\Bundle\IdentityBundle\Enum\LinkPurposeEnum;
use Vivutio\Bundle\IdentityBundle\Exception\InvalidPasswordException;
use Vivutio\Bundle\IdentityBundle\Exception\InvalidPersonException;
use Vivutio\Bundle\IdentityBundle\Service\AccountLinkService;

/**
 * Accepting an invitation, ported from uhifadhi's auth/accept: the person
 * names themselves, chooses a password, and is signed in. Under /login, which
 * an installation's firewall already opens to strangers.
 *
 * The token moves from the address into the session at once, as a reset
 * link's does.
 */
final readonly class InvitationController
{
    public const string LINK = 'identity_invitation_link';
    public const string ACCEPT = 'identity_invitation';

    private const string TOKEN = 'identity.invitation_token';

    public function __construct(
        private Environment $twig,
        private AccountLinkService $links,
        private CsrfTokenManagerInterface $tokens,
        private UrlGeneratorInterface $urls,
        private Security $security,
    ) {
    }

    #[Route('/login/join/{selector}/{verifier}', name: self::LINK, requirements: ['selector' => '[a-f0-9]{24}', 'verifier' => '[A-Za-z0-9_-]{20,64}'], methods: ['GET'])]
    public function link(Request $request, string $selector, string $verifier): Response
    {
        $request->getSession()->set(self::TOKEN, [$selector, $verifier]);

        return new RedirectResponse($this->urls->generate(self::ACCEPT));
    }

    #[Route('/login/join', name: self::ACCEPT, methods: ['GET', 'POST'])]
    public function accept(Request $request): Response
    {
        $link = $this->redeemable($request);
        if (null === $link) {
            return $this->page('@Identity/password/closed.html.twig');
        }

        if (!$request->isMethod('POST')) {
            return $this->page('@Identity/password/accept.html.twig', ['link' => $link]);
        }

        $payload = $request->getPayload();
        $typed = ['first_name' => $payload->getString('first_name'), 'last_name' => $payload->getString('last_name')];
        if (!$this->tokens->isTokenValid(new CsrfToken('identity_accept', $payload->getString('_token')))) {
            return $this->page('@Identity/password/accept.html.twig', ['link' => $link, 'typed' => $typed, 'expired' => true], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $account = $this->links->accept($link, $typed['first_name'], $typed['last_name'], $payload->getString('password'), $payload->getString('repeat'));
        } catch (InvalidPersonException|InvalidPasswordException $refusal) {
            return $this->page('@Identity/password/accept.html.twig', ['link' => $link, 'typed' => $typed, 'wrong' => [$refusal->field => $refusal->getMessage()]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $request->getSession()->remove(self::TOKEN);
        $this->security->login($account, 'form_login');

        return $this->page('@Identity/password/welcome.html.twig', ['account' => $account]);
    }

    private function redeemable(Request $request): ?AccountLink
    {
        $token = $request->getSession()->get(self::TOKEN);
        if (!\is_array($token) || !\is_string($token[0] ?? null) || !\is_string($token[1] ?? null)) {
            return null;
        }

        return $this->links->redeemable($token[0], $token[1], LinkPurposeEnum::Invitation);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function page(string $template, array $context = [], int $status = Response::HTTP_OK): Response
    {
        return new Response($this->twig->render($template, $context), $status);
    }
}
