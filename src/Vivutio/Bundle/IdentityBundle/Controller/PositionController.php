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
use Vivutio\Bundle\IdentityBundle\Entity\Position;
use Vivutio\Bundle\IdentityBundle\Exception\InvalidPositionException;
use Vivutio\Bundle\IdentityBundle\Exception\UngrantablePairsException;
use Vivutio\Bundle\IdentityBundle\Repository\PositionRepository;
use Vivutio\Bundle\IdentityBundle\Service\PositionMatrixService;
use Vivutio\Bundle\IdentityBundle\Service\PositionService;

/**
 * The positions, ported from uhifadhi's register, record and configure page:
 * read by whoever holds positions.read, added and changed by the tiers alone.
 */
final readonly class PositionController
{
    public const string REGISTER = 'identity_positions';
    public const string ADD = 'identity_position_add';
    public const string SHOW = 'identity_position';
    public const string CONFIGURE = 'identity_position_configure';

    public const string READ = 'positions.read';
    public const string CHANGE = 'positions.configure';

    private const string SAVED = 'position.saved';

    public function __construct(
        private Environment $twig,
        private PositionRepository $positions,
        private PositionService $service,
        private PositionMatrixService $matrix,
        private CsrfTokenManagerInterface $tokens,
        private UrlGeneratorInterface $urls,
    ) {
    }

    #[Route('/team/positions', name: self::REGISTER, methods: ['GET'])]
    #[IsGranted(self::READ)]
    public function register(): Response
    {
        return $this->registerPage();
    }

    /** A new position grants nothing; its configure page is where it is given what it grants. */
    #[Route('/team/positions', name: self::ADD, methods: ['POST'])]
    #[IsGranted(self::CHANGE)]
    public function add(Request $request): Response
    {
        $name = $request->getPayload()->getString('name');

        if (!$this->tokens->isTokenValid(new CsrfToken('position_add', $request->getPayload()->getString('_token')))) {
            return $this->registerPage(typed: $name, expired: true);
        }

        try {
            $position = $this->service->create($name, []);
        } catch (InvalidPositionException $refusal) {
            return $this->registerPage(typed: $name, wrong: $refusal->getMessage());
        }

        return new RedirectResponse($this->urls->generate(self::CONFIGURE, ['uuid' => $position->getUuid()]));
    }

    #[Route('/team/positions/{uuid}', name: self::SHOW, requirements: ['uuid' => Requirement::UUID], methods: ['GET'])]
    #[IsGranted(self::READ)]
    public function show(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])]
        Position $position,
    ): Response {
        $session = $request->getSession();
        $saved = $session instanceof FlashBagAwareSessionInterface && [] !== $session->getFlashBag()->get(self::SAVED);

        return new Response($this->twig->render('@Identity/positions/show.html.twig', [
            'position' => $position,
            'summary' => $this->matrix->summaries([$position])[0],
            'matrix' => $this->matrix->matrix(),
            'saved' => $saved,
        ]));
    }

    #[Route('/team/positions/{uuid}/configure', name: self::CONFIGURE, requirements: ['uuid' => Requirement::UUID], methods: ['GET', 'POST'])]
    #[IsGranted(self::CHANGE)]
    public function configure(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])]
        Position $position,
    ): Response {
        if (!$request->isMethod('POST')) {
            return $this->configurePage($position, (string) $position->getName(), $position->getGrants());
        }

        $payload = $request->getPayload();
        $name = $payload->getString('name');
        $grants = array_values(array_filter($payload->all('grants'), \is_string(...)));

        if (!$this->tokens->isTokenValid(new CsrfToken('position_configure', $payload->getString('_token')))) {
            return $this->configurePage($position, $name, $grants, expired: true);
        }

        try {
            $this->service->change($position, $name, $grants);
        } catch (InvalidPositionException $refusal) {
            return $this->configurePage($position, $name, $grants, wrong: ['name' => $refusal->getMessage()]);
        } catch (UngrantablePairsException $refusal) {
            return $this->configurePage($position, $name, $grants, wrong: ['grants' => $refusal->getMessage()]);
        }

        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add(self::SAVED, true);
        }

        return new RedirectResponse($this->urls->generate(self::SHOW, ['uuid' => $position->getUuid()]));
    }

    private function registerPage(string $typed = '', ?string $wrong = null, bool $expired = false): Response
    {
        return new Response($this->twig->render('@Identity/positions/index.html.twig', [
            'summaries' => $this->matrix->summaries($this->positions->findBy([], ['name' => 'ASC'])),
            'typed' => $typed,
            'wrong' => $wrong,
            'expired' => $expired,
        ]), null === $wrong && !$expired ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @param list<string>          $grants
     * @param array<string, string> $wrong
     */
    private function configurePage(Position $position, string $name, array $grants, array $wrong = [], bool $expired = false): Response
    {
        return new Response($this->twig->render('@Identity/positions/configure.html.twig', [
            'position' => $position,
            'name' => $name,
            'grants' => $grants,
            'matrix' => $this->matrix->matrix(),
            'wrong' => $wrong,
            'expired' => $expired,
        ]), [] === $wrong && !$expired ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
