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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;
use Vivutio\Bundle\IdentityBundle\Entity\Department;
use Vivutio\Bundle\IdentityBundle\Entity\Office;
use Vivutio\Bundle\IdentityBundle\Entity\Position;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Model\DeletionPageContext;
use Vivutio\Bundle\IdentityBundle\Repository\DeletionRecordRepository;
use Vivutio\Bundle\IdentityBundle\Security\DeletionVoter;
use Vivutio\Bundle\IdentityBundle\Service\DeletionPageService;

/**
 * The core's records deleted by a Super Admin, each from a page of its own
 * reached from its Configure page's Danger card, and Settings › Deletions,
 * the line kept of each delete, by day.
 */
final readonly class DeletionController
{
    public const string PERSON = 'identity_person_delete';
    public const string POSITION = 'identity_position_delete';
    public const string DEPARTMENT = 'identity_department_delete';
    public const string OFFICE = 'identity_office_delete';
    public const string DELETIONS = 'identity_settings_deletions';

    public function __construct(
        private Environment $twig,
        private DeletionPageService $pages,
        private DeletionRecordRepository $lines,
        private UrlGeneratorInterface $urls,
    ) {
    }

    #[Route('/team/{uuid}/delete', name: self::PERSON, requirements: ['uuid' => Requirement::UUID], methods: ['GET', 'POST'])]
    #[IsGranted(DeletionVoter::DELETE, subject: 'person')]
    public function person(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])]
        User $person,
    ): Response {
        $record = $this->urls->generate(TeamController::MEMBER, ['uuid' => $person->getUuid()]);

        return $this->pages->respond($request, $person, new DeletionPageContext('team', [['label' => 'Team', 'url' => $this->urls->generate(TeamController::TEAM)], ['label' => $person->getFullName(), 'url' => $record]], $this->urls->generate(PersonController::CONFIGURE, ['uuid' => $person->getUuid()]), $this->urls->generate(TeamController::TEAM)));
    }

    #[Route('/team/positions/{uuid}/delete', name: self::POSITION, requirements: ['uuid' => Requirement::UUID], methods: ['GET', 'POST'])]
    #[IsGranted(DeletionVoter::DELETE, subject: 'position')]
    public function position(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])]
        Position $position,
    ): Response {
        return $this->pages->respond($request, $position, new DeletionPageContext('positions', [['label' => 'Positions', 'url' => $this->urls->generate(PositionController::REGISTER)], ['label' => (string) $position->getName(), 'url' => $this->urls->generate(PositionController::SHOW, ['uuid' => $position->getUuid()])]], $this->urls->generate(PositionController::CONFIGURE, ['uuid' => $position->getUuid()]), $this->urls->generate(PositionController::REGISTER)));
    }

    #[Route('/team/departments/{uuid}/delete', name: self::DEPARTMENT, requirements: ['uuid' => Requirement::UUID], methods: ['GET', 'POST'])]
    #[IsGranted(DeletionVoter::DELETE, subject: 'department')]
    public function department(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])]
        Department $department,
    ): Response {
        return $this->pages->respond($request, $department, new DeletionPageContext('departments', [['label' => 'Departments', 'url' => $this->urls->generate(DepartmentController::REGISTER)], ['label' => $department->getName(), 'url' => $this->urls->generate(DepartmentController::SHOW, ['uuid' => $department->getUuid()])]], $this->urls->generate(DepartmentController::CONFIGURE, ['uuid' => $department->getUuid()]), $this->urls->generate(DepartmentController::REGISTER)));
    }

    #[Route('/team/offices/{uuid}/delete', name: self::OFFICE, requirements: ['uuid' => Requirement::UUID], methods: ['GET', 'POST'])]
    #[IsGranted(DeletionVoter::DELETE, subject: 'office')]
    public function office(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])]
        Office $office,
    ): Response {
        return $this->pages->respond($request, $office, new DeletionPageContext('offices', [['label' => 'Offices', 'url' => $this->urls->generate(OfficeController::REGISTER)], ['label' => $office->getName(), 'url' => $this->urls->generate(OfficeController::SHOW, ['uuid' => $office->getUuid()])]], $this->urls->generate(OfficeController::CONFIGURE, ['uuid' => $office->getUuid()]), $this->urls->generate(OfficeController::REGISTER)));
    }

    #[Route('/settings/deletions', name: self::DELETIONS, methods: ['GET'])]
    #[IsGranted(DeletionVoter::DELETE)]
    public function deletions(): Response
    {
        $days = [];
        foreach ($this->lines->findBy([], ['deletedAt' => 'DESC', 'id' => 'DESC']) as $line) {
            $days[$line->getDeletedAt()->format('Y-m-d')][] = $line;
        }

        return new Response($this->twig->render('@Identity/settings/deletions.html.twig', ['days' => $days]));
    }
}
