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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;
use Vivutio\Bundle\IdentityBundle\Exception\InvalidOrganizationIdentityException;
use Vivutio\Bundle\IdentityBundle\Service\OrganizationService;
use Vivutio\Contracts\Settings\OrganizationIdentitySourceInterface;

/**
 * Settings › Organization: whose installation this is, read as plain values
 * and changed on its Configure page, where nothing is saved until Save.
 *
 * Here and not in the Shell because the record is this bundle's; the Shell
 * declares the settings pairs every Settings page checks.
 *
 * Extends nothing and takes what it needs, as the framework's own controllers
 * do; a reusable bundle is not autoconfigured, so AbstractController's
 * container would never be set.
 *
 * @see vendor/symfony/framework-bundle/Controller/TemplateController.php
 */
final readonly class SettingsController
{
    public const string ORGANIZATION = 'identity_settings_organization';
    public const string CONFIGURE_ORGANIZATION = 'identity_settings_configure_organization';
    public const string READ = 'settings.read';
    public const string CONFIGURE = 'settings.configure';

    private const string TOKEN = 'settings_organization';
    private const string SAVED = 'settings.saved';

    public function __construct(
        private Environment $twig,
        private OrganizationIdentitySourceInterface $identities,
        private OrganizationService $organizations,
        private CsrfTokenManagerInterface $tokens,
        private UrlGeneratorInterface $urls,
    ) {
    }

    #[Route('/settings/organization', name: self::ORGANIZATION, methods: ['GET'])]
    #[IsGranted(self::READ)]
    public function organization(Request $request): Response
    {
        $session = $request->getSession();

        return new Response($this->twig->render('@Identity/settings/organization.html.twig', [
            'identity' => $this->identities->identity(),
            'saved' => $session instanceof FlashBagAwareSessionInterface && [] !== $session->getFlashBag()->get(self::SAVED),
            'now' => new \DateTimeImmutable(),
        ]));
    }

    #[Route('/settings/configure/organization', name: self::CONFIGURE_ORGANIZATION, methods: ['GET', 'POST'])]
    #[IsGranted(self::CONFIGURE)]
    public function configureOrganization(Request $request): Response
    {
        $identity = $this->identities->identity();
        $values = [
            'name' => $identity->name ?? '',
            'short_name' => $identity->shortName ?? '',
            'time_zone' => $identity->timeZone ?? '',
            'country' => $identity->country ?? '',
        ];

        if (!$request->isMethod('POST')) {
            return $this->form($values);
        }

        $payload = $request->getPayload();
        foreach (array_keys($values) as $field) {
            $values[$field] = $payload->getString($field);
        }

        if (!$this->tokens->isTokenValid(new CsrfToken(self::TOKEN, $payload->getString('_token')))) {
            return $this->form($values, expired: true);
        }

        try {
            $this->organizations->record($values['name'], $values['short_name'], $values['time_zone'], $values['country']);
        } catch (InvalidOrganizationIdentityException $refusal) {
            return $this->form($values, wrong: [str_replace(' ', '_', $refusal->field) => $refusal->getMessage()]);
        }

        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add(self::SAVED, true);
        }

        return new RedirectResponse($this->urls->generate(self::ORGANIZATION));
    }

    /**
     * @param array<string, string> $values the fields as they stand
     * @param array<string, string> $wrong  a refusal, keyed by the field it is about
     */
    private function form(array $values, array $wrong = [], bool $expired = false): Response
    {
        return new Response($this->twig->render('@Identity/settings/configure_organization.html.twig', [
            'values' => $values,
            'wrong' => $wrong,
            'expired' => $expired,
            'zones' => self::zonesByRegion(),
            'token' => self::TOKEN,
        ]), [] === $wrong && !$expired ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Every zone the server knows, under the region its name starts with.
     *
     * @return array<string, list<string>>
     */
    private static function zonesByRegion(): array
    {
        $regions = [];
        foreach (\DateTimeZone::listIdentifiers() as $zone) {
            $regions[explode('/', $zone, 2)[0]][] = $zone;
        }

        return $regions;
    }
}
