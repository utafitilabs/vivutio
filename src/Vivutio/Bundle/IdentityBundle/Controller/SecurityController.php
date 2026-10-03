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

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Twig\Environment;

/**
 * Signing in and out: the two addresses a stranger and a leaver reach.
 *
 * No authentication happens here. The installation's firewall, `form_login`,
 * answers the form's POST before any controller runs, and its `logout` key
 * answers the sign-out address entirely. What is left is drawing the form,
 * and sending somebody already signed in where a fresh sign-in would.
 *
 * It extends nothing and is handed what it uses, as the framework's own
 * controllers are: a reusable bundle is not autoconfigured.
 *
 * @see https://symfony.com/doc/current/security.html#form-login
 * @see vendor/symfony/framework-bundle/Controller/TemplateController.php — a controller that extends nothing
 */
final readonly class SecurityController
{
    public const string SIGN_IN = 'identity_login';
    public const string SIGN_OUT = 'identity_logout';

    /**
     * @param string $afterSignInPath where somebody already signed in is sent
     *                                when they ask for the form. A path, not a route name: this bundle cannot
     *                                know what an installation calls its front page. The firewall's own
     *                                `default_target_path` decides where a fresh sign-in lands.
     */
    public function __construct(
        private Environment $twig,
        private AuthenticationUtils $authenticationUtils,
        private TokenStorageInterface $tokens,
        private string $afterSignInPath = '/',
    ) {
    }

    #[Route('/login', name: self::SIGN_IN, methods: ['GET', 'POST'])]
    public function signIn(): Response
    {
        if ($this->tokens->getToken()?->getUser() instanceof UserInterface) {
            return new RedirectResponse($this->afterSignInPath);
        }

        return new Response($this->twig->render('@Identity/login.html.twig', [
            'last_username' => $this->authenticationUtils->getLastUsername(),
            'error' => $this->authenticationUtils->getLastAuthenticationError(),
        ]));
    }

    /**
     * Never runs: the firewall's logout listener answers this address. The
     * route exists so the listener has something to match and so a link to
     * it can be generated.
     */
    #[Route('/logout', name: self::SIGN_OUT, methods: ['GET'])]
    public function signOut(): never
    {
        throw new \LogicException('The firewall\'s logout key answers this address; the installation\'s security.yaml has to name it.');
    }
}
