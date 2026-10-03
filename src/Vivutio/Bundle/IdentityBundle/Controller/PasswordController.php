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
use Vivutio\Bundle\IdentityBundle\Service\AccountLinkService;
use Vivutio\Bundle\IdentityBundle\Service\MailAvailability;

/**
 * A forgotten password, ported from uhifadhi's settled pages (auth/forgot and
 * auth/reset). Open routes: whoever forgot their password is signed out.
 *
 * The link's token is moved from the address into the session at once, so it
 * is never sent on as a referrer nor left in the history, and who the link is
 * for is read from the token, never from anything the visitor can retype.
 *
 * @see https://symfony.com/bundles/SymfonyCastsResetPasswordBundle/current/index.html — the same token-in-session step
 */
final readonly class PasswordController
{
    public const string FORGOT = 'identity_forgot';
    public const string LINK = 'identity_reset_link';
    public const string RESET = 'identity_reset';

    private const string TOKEN = 'identity.reset_token';

    public function __construct(
        private Environment $twig,
        private AccountLinkService $links,
        private MailAvailability $mail,
        private CsrfTokenManagerInterface $tokens,
        private UrlGeneratorInterface $urls,
        private Security $security,
    ) {
    }

    #[Route('/login/forgot', name: self::FORGOT, methods: ['GET', 'POST'])]
    public function forgot(Request $request): Response
    {
        if (!$this->mail->isAvailable()) {
            return $this->page('@Identity/password/no_mail.html.twig');
        }

        if (!$request->isMethod('POST')) {
            return $this->page('@Identity/password/forgot.html.twig');
        }

        $email = $request->getPayload()->getString('email');
        if (!$this->tokens->isTokenValid(new CsrfToken('identity_forgot', $request->getPayload()->getString('_token')))) {
            return $this->page('@Identity/password/forgot.html.twig', ['email' => $email, 'expired' => true], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->links->requestReset($email);

        return $this->page('@Identity/password/sent.html.twig', ['email' => $email]);
    }

    #[Route('/login/reset/{selector}/{verifier}', name: self::LINK, requirements: ['selector' => '[a-f0-9]{24}', 'verifier' => '[A-Za-z0-9_-]{20,64}'], methods: ['GET'])]
    public function link(Request $request, string $selector, string $verifier): Response
    {
        $request->getSession()->set(self::TOKEN, [$selector, $verifier]);

        return new RedirectResponse($this->urls->generate(self::RESET));
    }

    #[Route('/login/reset', name: self::RESET, methods: ['GET', 'POST'])]
    public function reset(Request $request): Response
    {
        $link = $this->redeemable($request);
        if (null === $link) {
            return $this->page('@Identity/password/expired.html.twig');
        }

        if (!$request->isMethod('POST')) {
            return $this->page('@Identity/password/reset.html.twig', ['link' => $link]);
        }

        $payload = $request->getPayload();
        if (!$this->tokens->isTokenValid(new CsrfToken('identity_reset', $payload->getString('_token')))) {
            return $this->page('@Identity/password/reset.html.twig', ['link' => $link, 'expired' => true], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $account = $this->links->setPassword($link, $payload->getString('password'), $payload->getString('repeat'));
        } catch (InvalidPasswordException $refusal) {
            return $this->page('@Identity/password/reset.html.twig', ['link' => $link, 'wrong' => [$refusal->field => $refusal->getMessage()]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $request->getSession()->remove(self::TOKEN);
        $this->security->login($account, 'form_login');

        return $this->page('@Identity/password/done.html.twig');
    }

    private function redeemable(Request $request): ?AccountLink
    {
        $token = $request->getSession()->get(self::TOKEN);
        if (!\is_array($token) || !\is_string($token[0] ?? null) || !\is_string($token[1] ?? null)) {
            return null;
        }

        return $this->links->redeemable($token[0], $token[1], LinkPurposeEnum::Reset);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function page(string $template, array $context = [], int $status = Response::HTTP_OK): Response
    {
        return new Response($this->twig->render($template, $context), $status);
    }
}
