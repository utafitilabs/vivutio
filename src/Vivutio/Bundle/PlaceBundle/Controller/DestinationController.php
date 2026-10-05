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

namespace Vivutio\Bundle\PlaceBundle\Controller;

use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;
use Vivutio\Bundle\PlaceBundle\Entity\Destination;
use Vivutio\Bundle\PlaceBundle\Entity\DestinationFee;
use Vivutio\Bundle\PlaceBundle\Enum\DestinationKindEnum;
use Vivutio\Bundle\PlaceBundle\Enum\FeeKindEnum;
use Vivutio\Bundle\PlaceBundle\Enum\FeePerEnum;
use Vivutio\Bundle\PlaceBundle\Enum\GuestEnum;
use Vivutio\Bundle\PlaceBundle\Enum\ResidencyEnum;
use Vivutio\Bundle\PlaceBundle\Exception\InvalidDestinationException;
use Vivutio\Bundle\PlaceBundle\Repository\DestinationRepository;
use Vivutio\Bundle\PlaceBundle\Service\DestinationFeeService;
use Vivutio\Bundle\PlaceBundle\Service\DestinationService;

/**
 * The destinations, by country, and a destination's page with its fees. Read
 * with destinations.read; adding a destination and its fees is the tiers'.
 */
final readonly class DestinationController
{
    public const string LIST = 'place_destinations';
    public const string ADD = 'place_destination_add';
    public const string SHOW = 'place_destination';
    public const string ADD_FEE = 'place_destination_fee_add';
    public const string REMOVE_FEE = 'place_destination_fee_remove';

    public const string READ = 'destinations.read';
    public const string CHANGE = 'destinations.configure';

    private const string KEY = '[a-z0-9-]+';

    public function __construct(
        private Environment $twig,
        private DestinationService $destinations,
        private DestinationFeeService $fees,
        private DestinationRepository $repository,
        private CsrfTokenManagerInterface $tokens,
        private UrlGeneratorInterface $urls,
    ) {
    }

    #[Route('/destinations', name: self::LIST, methods: ['GET'])]
    #[IsGranted(self::READ)]
    public function list(): Response
    {
        return $this->listPage();
    }

    #[Route('/destinations', name: self::ADD, methods: ['POST'])]
    #[IsGranted(self::CHANGE)]
    public function add(Request $request): Response
    {
        $payload = $request->getPayload();
        $typed = ['name' => $payload->getString('name'), 'kind' => $payload->getString('kind'), 'country' => $payload->getString('country')];
        if (!$this->tokens->isTokenValid(new CsrfToken('place_destination_add', $payload->getString('_token')))) {
            return $this->listPage($typed, expired: true);
        }

        try {
            $destination = $this->destinations->create($typed['name'], $typed['kind'], $typed['country']);
        } catch (InvalidDestinationException $refusal) {
            return $this->listPage($typed, [$refusal->field => $refusal->getMessage()]);
        }

        return new RedirectResponse($this->urls->generate(self::SHOW, ['key' => $destination->getKey()]));
    }

    #[Route('/destinations/{key}', name: self::SHOW, requirements: ['key' => self::KEY], methods: ['GET'])]
    #[IsGranted(self::READ)]
    public function show(
        #[MapEntity(mapping: ['key' => 'key'])]
        Destination $destination,
    ): Response {
        return $this->showPage($destination);
    }

    #[Route('/destinations/{key}/fees', name: self::ADD_FEE, requirements: ['key' => self::KEY], methods: ['POST'])]
    #[IsGranted(self::CHANGE)]
    public function addFee(
        Request $request,
        #[MapEntity(mapping: ['key' => 'key'])]
        Destination $destination,
    ): Response {
        $payload = $request->getPayload();
        $typed = [];
        foreach (['kind', 'guest', 'residency', 'per', 'amount', 'currency', 'valid_from', 'valid_to'] as $field) {
            $typed[$field] = $payload->getString($field);
        }
        if (!$this->tokens->isTokenValid(new CsrfToken('place_destination_fee_add', $payload->getString('_token')))) {
            return $this->showPage($destination, $typed, expired: true);
        }

        try {
            $this->fees->add($destination, $typed);
        } catch (InvalidDestinationException $refusal) {
            return $this->showPage($destination, $typed, [$refusal->field => $refusal->getMessage()]);
        }

        return new RedirectResponse($this->urls->generate(self::SHOW, ['key' => $destination->getKey()]));
    }

    #[Route('/destinations/{key}/fees/{fee}/remove', name: self::REMOVE_FEE, requirements: ['key' => self::KEY, 'fee' => '\d+'], methods: ['POST'])]
    #[IsGranted(self::CHANGE)]
    public function removeFee(
        Request $request,
        #[MapEntity(mapping: ['key' => 'key'])]
        Destination $destination,
        #[MapEntity(id: 'fee')]
        DestinationFee $fee,
    ): Response {
        if ($fee->getDestination() !== $destination) {
            throw new NotFoundHttpException();
        }
        if ($this->tokens->isTokenValid(new CsrfToken('place_destination_fee_remove', $request->getPayload()->getString('_token')))) {
            $this->fees->remove($fee);
        }

        return new RedirectResponse($this->urls->generate(self::SHOW, ['key' => $destination->getKey()]));
    }

    /**
     * @param array<string, string> $typed
     * @param array<string, string> $wrong
     */
    private function listPage(array $typed = [], array $wrong = [], bool $expired = false): Response
    {
        $byCountry = [];
        foreach ($this->repository->findBy([], ['name' => 'ASC']) as $destination) {
            $byCountry[Countries::getName($destination->getCountry())][] = $destination;
        }
        ksort($byCountry);

        return new Response($this->twig->render('@Place/destinations/index.html.twig', [
            'by_country' => $byCountry,
            'kinds' => DestinationKindEnum::cases(),
            'countries' => Countries::getNames(),
            'typed' => [...['name' => '', 'kind' => DestinationKindEnum::NationalPark->value, 'country' => ''], ...$typed],
            'wrong' => $wrong,
            'expired' => $expired,
        ]), [] === $wrong && !$expired ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @param array<string, string> $typed
     * @param array<string, string> $wrong
     */
    private function showPage(Destination $destination, array $typed = [], array $wrong = [], bool $expired = false): Response
    {
        return new Response($this->twig->render('@Place/destinations/show.html.twig', [
            'destination' => $destination,
            'country' => Countries::getName($destination->getCountry()),
            'kinds' => FeeKindEnum::cases(),
            'guests' => GuestEnum::cases(),
            'residencies' => ResidencyEnum::cases(),
            'pers' => FeePerEnum::cases(),
            'typed' => [...['kind' => 'entry', 'guest' => 'adult', 'residency' => 'non_resident', 'per' => 'person_day', 'amount' => '', 'currency' => 'USD', 'valid_from' => '', 'valid_to' => ''], ...$typed],
            'wrong' => $wrong,
            'expired' => $expired,
        ]), [] === $wrong && !$expired ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
