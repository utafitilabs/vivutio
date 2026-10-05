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

namespace Vivutio\Bundle\PartnerBundle\Controller;

use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;
use Vivutio\Bundle\PartnerBundle\Entity\Partner;
use Vivutio\Bundle\PartnerBundle\Enum\PartnerKindEnum;
use Vivutio\Bundle\PartnerBundle\Enum\PartnerStatusEnum;
use Vivutio\Bundle\PartnerBundle\Exception\InvalidPartnerException;
use Vivutio\Bundle\PartnerBundle\Repository\PartnerRepository;
use Vivutio\Bundle\PartnerBundle\Service\PartnerService;

/**
 * The partners: the register by status, a partner's page, its Configure page,
 * and archiving. Read with partners.read, kept with partners.manage.
 */
final readonly class PartnerController
{
    public const string REGISTER = 'partner_partners';
    public const string ADD = 'partner_partner_add';
    public const string SHOW = 'partner_partner';
    public const string CONFIGURE = 'partner_partner_configure';
    public const string ARCHIVE = 'partner_partner_archive';
    public const string REACTIVATE = 'partner_partner_reactivate';

    public const string READ = 'partners.read';
    public const string MANAGE = 'partners.manage';

    private const string SAVED = 'partner_saved';
    private const array FIELDS = ['name', 'kind', 'country', 'email', 'contact', 'phone', 'discount', 'credit_days', 'notes'];

    public function __construct(
        private Environment $twig,
        private PartnerService $service,
        private PartnerRepository $partners,
        private CsrfTokenManagerInterface $tokens,
        private UrlGeneratorInterface $urls,
    ) {
    }

    #[Route('/partners', name: self::REGISTER, methods: ['GET'])]
    #[IsGranted(self::READ)]
    public function register(Request $request): Response
    {
        return $this->registerPage(PartnerStatusEnum::tryFrom($request->query->getString('status')) ?? PartnerStatusEnum::Active);
    }

    #[Route('/partners', name: self::ADD, methods: ['POST'])]
    #[IsGranted(self::MANAGE)]
    public function add(Request $request): Response
    {
        $payload = $request->getPayload();
        $typed = ['name' => $payload->getString('name'), 'kind' => $payload->getString('kind'), 'country' => $payload->getString('country'), 'email' => $payload->getString('email')];
        if (!$this->tokens->isTokenValid(new CsrfToken('partner_add', $payload->getString('_token')))) {
            return $this->registerPage(PartnerStatusEnum::Active, $typed, expired: true);
        }

        try {
            $partner = $this->service->create($typed['name'], $typed['kind'], $typed['country'], $typed['email']);
        } catch (InvalidPartnerException $refusal) {
            return $this->registerPage(PartnerStatusEnum::Active, $typed, [$refusal->field => $refusal->getMessage()]);
        }

        return new RedirectResponse($this->urls->generate(self::CONFIGURE, ['uuid' => $partner->getUuid()]));
    }

    #[Route('/partners/{uuid}', name: self::SHOW, requirements: ['uuid' => Requirement::UUID], methods: ['GET'])]
    #[IsGranted(self::READ)]
    public function show(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])]
        Partner $partner,
    ): Response {
        $session = $request->getSession();
        $saved = $session instanceof FlashBagAwareSessionInterface && [] !== $session->getFlashBag()->get(self::SAVED);

        return new Response($this->twig->render('@Partner/partners/show.html.twig', [
            'partner' => $partner,
            'country' => Countries::getName($partner->getCountry()),
            'saved' => $saved,
        ]));
    }

    #[Route('/partners/{uuid}/configure', name: self::CONFIGURE, requirements: ['uuid' => Requirement::UUID], methods: ['GET', 'POST'])]
    #[IsGranted(self::MANAGE)]
    public function configure(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])]
        Partner $partner,
    ): Response {
        if (!$request->isMethod('POST')) {
            return $this->configurePage($partner, [
                'name' => $partner->getName(),
                'kind' => $partner->getKind()->value,
                'country' => $partner->getCountry(),
                'email' => $partner->getEmail(),
                'contact' => $partner->getContact(),
                'phone' => $partner->getPhone(),
                'discount' => '0.00' === $partner->getDiscount() ? '' : rtrim(rtrim($partner->getDiscount(), '0'), '.'),
                'credit_days' => 0 === $partner->getCreditDays() ? '' : (string) $partner->getCreditDays(),
                'notes' => $partner->getNotes(),
            ]);
        }

        $payload = $request->getPayload();
        $typed = [];
        foreach (self::FIELDS as $field) {
            $typed[$field] = $payload->getString($field);
        }
        if (!$this->tokens->isTokenValid(new CsrfToken('partner_configure', $payload->getString('_token')))) {
            return $this->configurePage($partner, $typed, expired: true);
        }

        try {
            $this->service->configure($partner, $typed);
        } catch (InvalidPartnerException $refusal) {
            return $this->configurePage($partner, $typed, [$refusal->field => $refusal->getMessage()]);
        }

        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add(self::SAVED, true);
        }

        return new RedirectResponse($this->urls->generate(self::SHOW, ['uuid' => $partner->getUuid()]));
    }

    #[Route('/partners/{uuid}/archive', name: self::ARCHIVE, requirements: ['uuid' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(self::MANAGE)]
    public function archive(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])]
        Partner $partner,
    ): Response {
        if ($this->tokens->isTokenValid(new CsrfToken('partner_status', $request->getPayload()->getString('_token')))) {
            $this->service->archive($partner);
        }

        return new RedirectResponse($this->urls->generate(self::SHOW, ['uuid' => $partner->getUuid()]));
    }

    #[Route('/partners/{uuid}/reactivate', name: self::REACTIVATE, requirements: ['uuid' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(self::MANAGE)]
    public function reactivate(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])]
        Partner $partner,
    ): Response {
        if ($this->tokens->isTokenValid(new CsrfToken('partner_status', $request->getPayload()->getString('_token')))) {
            $this->service->reactivate($partner);
        }

        return new RedirectResponse($this->urls->generate(self::SHOW, ['uuid' => $partner->getUuid()]));
    }

    /**
     * @param array<string, string> $typed
     * @param array<string, string> $wrong
     */
    private function registerPage(PartnerStatusEnum $status, array $typed = [], array $wrong = [], bool $expired = false): Response
    {
        $counts = [];
        foreach (PartnerStatusEnum::cases() as $case) {
            $counts[$case->value] = $this->partners->count(['status' => $case]);
        }

        return new Response($this->twig->render('@Partner/partners/index.html.twig', [
            'status' => $status,
            'statuses' => PartnerStatusEnum::cases(),
            'counts' => $counts,
            'partners' => $this->partners->findBy(['status' => $status], ['name' => 'ASC']),
            'kinds' => PartnerKindEnum::cases(),
            'countries' => Countries::getNames(),
            'typed' => [...['name' => '', 'kind' => PartnerKindEnum::TourOperator->value, 'country' => '', 'email' => ''], ...$typed],
            'wrong' => $wrong,
            'expired' => $expired,
        ]), [] === $wrong && !$expired ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @param array<string, string> $typed
     * @param array<string, string> $wrong
     */
    private function configurePage(Partner $partner, array $typed, array $wrong = [], bool $expired = false): Response
    {
        return new Response($this->twig->render('@Partner/partners/configure.html.twig', [
            'partner' => $partner,
            'kinds' => PartnerKindEnum::cases(),
            'countries' => Countries::getNames(),
            'typed' => $typed,
            'wrong' => $wrong,
            'expired' => $expired,
        ]), [] === $wrong && !$expired ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
