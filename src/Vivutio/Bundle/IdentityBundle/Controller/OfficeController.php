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

use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;
use Vivutio\Bundle\IdentityBundle\Entity\Office;
use Vivutio\Bundle\IdentityBundle\Exception\InvalidOfficeException;
use Vivutio\Bundle\IdentityBundle\Service\OfficeDirectoryService;
use Vivutio\Bundle\IdentityBundle\Service\OfficeService;

/**
 * The offices, after uhifadhi's stations: a register, an office's page with
 * who is posted there and which departments sit there, and its configure
 * page. Read with offices.read; added and changed by the tiers alone.
 */
final readonly class OfficeController
{
    public const string REGISTER = 'identity_offices';
    public const string ADD = 'identity_office_add';
    public const string SHOW = 'identity_office';
    public const string CONFIGURE = 'identity_office_configure';

    public const string READ = 'offices.read';
    public const string CHANGE = 'offices.configure';

    private const string SAVED = 'office.saved';

    public function __construct(
        private Environment $twig,
        private OfficeService $service,
        private OfficeDirectoryService $directory,
        private CsrfTokenManagerInterface $tokens,
        private UrlGeneratorInterface $urls,
    ) {
    }

    #[Route('/team/offices', name: self::REGISTER, methods: ['GET'])]
    #[IsGranted(self::READ)]
    public function register(): Response
    {
        return $this->registerPage();
    }

    #[Route('/team/offices', name: self::ADD, methods: ['POST'])]
    #[IsGranted(self::CHANGE)]
    public function add(Request $request): Response
    {
        $typed = $this->typed($request);

        if (!$this->tokens->isTokenValid(new CsrfToken('office_add', $request->getPayload()->getString('_token')))) {
            return $this->registerPage(typed: $typed, expired: true);
        }

        try {
            $office = $this->service->create($typed['name'], $typed['city'], $typed['country']);
        } catch (InvalidOfficeException $refusal) {
            return $this->registerPage(typed: $typed, wrong: [$refusal->field => $refusal->getMessage()]);
        }

        return new RedirectResponse($this->urls->generate(self::SHOW, ['uuid' => $office->getUuid()]));
    }

    #[Route('/team/offices/{uuid}', name: self::SHOW, requirements: ['uuid' => Requirement::UUID], methods: ['GET'])]
    #[IsGranted(self::READ)]
    public function show(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])]
        Office $office,
    ): Response {
        $session = $request->getSession();
        $saved = $session instanceof FlashBagAwareSessionInterface && [] !== $session->getFlashBag()->get(self::SAVED);

        return new Response($this->twig->render('@Identity/offices/show.html.twig', [
            'summary' => $this->directory->summary($office),
            'saved' => $saved,
        ]));
    }

    #[Route('/team/offices/{uuid}/configure', name: self::CONFIGURE, requirements: ['uuid' => Requirement::UUID], methods: ['GET', 'POST'])]
    #[IsGranted(self::CHANGE)]
    public function configure(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])]
        Office $office,
    ): Response {
        if (!$request->isMethod('POST')) {
            return $this->configurePage($office, ['name' => $office->getName(), 'city' => $office->getCity(), 'country' => $office->getCountry()]);
        }

        $typed = $this->typed($request);
        if (!$this->tokens->isTokenValid(new CsrfToken('office_configure', $request->getPayload()->getString('_token')))) {
            return $this->configurePage($office, $typed, expired: true);
        }

        try {
            $this->service->change($office, $typed['name'], $typed['city'], $typed['country']);
        } catch (InvalidOfficeException $refusal) {
            return $this->configurePage($office, $typed, wrong: [$refusal->field => $refusal->getMessage()]);
        }

        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add(self::SAVED, true);
        }

        return new RedirectResponse($this->urls->generate(self::SHOW, ['uuid' => $office->getUuid()]));
    }

    /**
     * @return array{name: string, city: string, country: string}
     */
    private function typed(Request $request): array
    {
        $payload = $request->getPayload();

        return ['name' => $payload->getString('name'), 'city' => $payload->getString('city'), 'country' => $payload->getString('country')];
    }

    /**
     * @param array<string, string> $typed
     * @param array<string, string> $wrong
     */
    private function registerPage(array $typed = [], array $wrong = [], bool $expired = false): Response
    {
        return new Response($this->twig->render('@Identity/offices/index.html.twig', [
            'summaries' => $this->directory->summaries(),
            'typed' => $typed,
            'wrong' => $wrong,
            'expired' => $expired,
        ]), [] === $wrong && !$expired ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @param array<string, string> $typed
     * @param array<string, string> $wrong
     */
    private function configurePage(Office $office, array $typed, array $wrong = [], bool $expired = false): Response
    {
        return new Response($this->twig->render('@Identity/offices/configure.html.twig', [
            'office' => $office,
            'typed' => $typed,
            'wrong' => $wrong,
            'expired' => $expired,
        ]), [] === $wrong && !$expired ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
