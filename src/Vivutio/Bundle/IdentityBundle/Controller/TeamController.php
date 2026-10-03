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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;
use Vivutio\Bundle\IdentityBundle\Model\TeamQuery;
use Vivutio\Bundle\IdentityBundle\Security\AccountVoter;
use Vivutio\Bundle\IdentityBundle\Service\TeamDirectoryService;

/**
 * The team list.
 *
 * What a viewer sees of each person is asked of the voters: whether they see
 * tiers, and whether they may read addresses. The list and its filters follow
 * the same two answers, so a column a viewer is not shown is never a filter.
 *
 * It extends nothing and is handed what it uses, as the framework's own
 * controllers are.
 *
 * @see vendor/symfony/framework-bundle/Controller/TemplateController.php
 */
final readonly class TeamController
{
    public const string TEAM = 'identity_team';

    public function __construct(
        private Environment $twig,
        private TeamDirectoryService $directory,
        private AuthorizationCheckerInterface $authorization,
    ) {
    }

    #[Route('/team', name: self::TEAM, methods: ['GET'])]
    #[IsGranted('directory.read')]
    public function team(Request $request): Response
    {
        $query = TeamQuery::fromRequest($request);

        // The search reads addresses only for whoever may read them; the
        // template draws them through door() on the same pair.
        $withTiers = $this->authorization->isGranted(AccountVoter::SEE_TIERS);

        return new Response($this->twig->render('@Identity/team/index.html.twig', [
            'query' => $query,
            'page' => $this->directory->page($query, $withTiers, $this->withAddresses()),
            'with_tiers' => $withTiers,
            'tiers' => TierEnum::cases(),
        ]));
    }

    private function withAddresses(): bool
    {
        return $this->authorization->isGranted('personal_details.read');
    }
}
