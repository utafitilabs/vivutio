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
use Vivutio\Bundle\IdentityBundle\Entity\Department;
use Vivutio\Bundle\IdentityBundle\Exception\InvalidDepartmentException;
use Vivutio\Bundle\IdentityBundle\Exception\UngrantablePairsException;
use Vivutio\Bundle\IdentityBundle\Repository\OfficeRepository;
use Vivutio\Bundle\IdentityBundle\Repository\PositionRepository;
use Vivutio\Bundle\IdentityBundle\Service\DepartmentDirectoryService;
use Vivutio\Bundle\IdentityBundle\Service\DepartmentService;
use Vivutio\Bundle\IdentityBundle\Service\PositionMatrixService;

/**
 * The departments, ported from uhifadhi's register, record and configure
 * page: read by whoever holds departments.read, added and changed by the
 * tiers alone.
 */
final readonly class DepartmentController
{
    public const string REGISTER = 'identity_departments';
    public const string ADD = 'identity_department_add';
    public const string SHOW = 'identity_department';
    public const string CONFIGURE = 'identity_department_configure';

    public const string READ = 'departments.read';
    public const string CHANGE = 'departments.configure';

    private const string SAVED = 'department.saved';

    public function __construct(
        private Environment $twig,
        private DepartmentService $service,
        private DepartmentDirectoryService $directory,
        private PositionMatrixService $matrix,
        private PositionRepository $positions,
        private OfficeRepository $offices,
        private CsrfTokenManagerInterface $tokens,
        private UrlGeneratorInterface $urls,
    ) {
    }

    #[Route('/team/departments', name: self::REGISTER, methods: ['GET'])]
    #[IsGranted(self::READ)]
    public function register(): Response
    {
        return $this->registerPage();
    }

    /** A new department allows nothing; its configure page is where it is told what it allows. */
    #[Route('/team/departments', name: self::ADD, methods: ['POST'])]
    #[IsGranted(self::CHANGE)]
    public function add(Request $request): Response
    {
        $name = $request->getPayload()->getString('name');

        if (!$this->tokens->isTokenValid(new CsrfToken('department_add', $request->getPayload()->getString('_token')))) {
            return $this->registerPage(typed: $name, expired: true);
        }

        try {
            $department = $this->service->create($name);
        } catch (InvalidDepartmentException $refusal) {
            return $this->registerPage(typed: $name, wrong: $refusal->getMessage());
        }

        return new RedirectResponse($this->urls->generate(self::CONFIGURE, ['uuid' => $department->getUuid()]));
    }

    #[Route('/team/departments/{uuid}', name: self::SHOW, requirements: ['uuid' => Requirement::UUID], methods: ['GET'])]
    #[IsGranted(self::READ)]
    public function show(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])]
        Department $department,
    ): Response {
        $session = $request->getSession();
        $saved = $session instanceof FlashBagAwareSessionInterface && [] !== $session->getFlashBag()->get(self::SAVED);

        return new Response($this->twig->render('@Identity/departments/show.html.twig', [
            'summary' => $this->directory->summary($department),
            'matrix' => $this->matrix->matrix(modulesOnly: true),
            'saved' => $saved,
        ]));
    }

    #[Route('/team/departments/{uuid}/configure', name: self::CONFIGURE, requirements: ['uuid' => Requirement::UUID], methods: ['GET', 'POST'])]
    #[IsGranted(self::CHANGE)]
    public function configure(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])]
        Department $department,
    ): Response {
        if (!$request->isMethod('POST')) {
            return $this->configurePage($department, $department->getName(), (string) $department->getHead()?->getUuid(), $department->getAllows(), (string) $department->getOffice()?->getUuid());
        }

        $payload = $request->getPayload();
        $name = $payload->getString('name');
        $head = $payload->getString('head');
        $allows = array_values(array_filter($payload->all('allows'), \is_string(...)));
        $sitsAt = $payload->getString('office');

        if (!$this->tokens->isTokenValid(new CsrfToken('department_configure', $payload->getString('_token')))) {
            return $this->configurePage($department, $name, $head, $allows, $sitsAt, expired: true);
        }

        $office = null;
        if ('' !== $sitsAt) {
            $office = 1 === preg_match('{^'.Requirement::UUID.'$}D', $sitsAt) ? $this->offices->findOneBy(['uuid' => $sitsAt]) : null;
            if (null === $office) {
                return $this->configurePage($department, $name, $head, $allows, $sitsAt, wrong: ['office' => 'Choose the organization or one of the offices offered.']);
            }
        }

        $position = null;
        if ('' !== $head) {
            $position = 1 === preg_match('{^'.Requirement::UUID.'$}D', $head) ? $this->positions->findOneBy(['uuid' => $head]) : null;
            if (null === $position) {
                return $this->configurePage($department, $name, $head, $allows, $sitsAt, wrong: ['head' => 'Choose one of the positions offered.']);
            }
        }

        try {
            $this->service->moveTo($department, $office);
            $this->service->rename($department, $name);
            $this->service->changeHead($department, $position);
            $this->service->changeAllows($department, $allows);
        } catch (InvalidDepartmentException $refusal) {
            return $this->configurePage($department, $name, $head, $allows, $sitsAt, wrong: [$refusal->field => $refusal->getMessage()]);
        } catch (UngrantablePairsException $refusal) {
            return $this->configurePage($department, $name, $head, $allows, $sitsAt, wrong: ['allows' => $refusal->getMessage()]);
        }

        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add(self::SAVED, true);
        }

        return new RedirectResponse($this->urls->generate(self::SHOW, ['uuid' => $department->getUuid()]));
    }

    private function registerPage(string $typed = '', ?string $wrong = null, bool $expired = false): Response
    {
        return new Response($this->twig->render('@Identity/departments/index.html.twig', [
            'summaries' => $this->directory->summaries(),
            'typed' => $typed,
            'wrong' => $wrong,
            'expired' => $expired,
        ]), null === $wrong && !$expired ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @param list<string>          $allows
     * @param array<string, string> $wrong
     */
    private function configurePage(Department $department, string $name, string $head, array $allows, string $office, array $wrong = [], bool $expired = false): Response
    {
        return new Response($this->twig->render('@Identity/departments/configure.html.twig', [
            'department' => $department,
            'name' => $name,
            'head' => $head,
            'allows' => $allows,
            'office' => $office,
            'offices' => $this->offices->findBy([], ['name' => 'ASC']),
            'heads' => $this->directory->headOptions($department),
            'matrix' => $this->matrix->matrix(modulesOnly: true),
            'wrong' => $wrong,
            'expired' => $expired,
        ]), [] === $wrong && !$expired ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
