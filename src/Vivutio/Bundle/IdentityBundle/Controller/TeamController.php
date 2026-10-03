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
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;
use Vivutio\Bundle\IdentityBundle\Entity\User;
use Vivutio\Bundle\IdentityBundle\Enum\TierEnum;
use Vivutio\Bundle\IdentityBundle\Model\TeamQuery;
use Vivutio\Bundle\IdentityBundle\Security\AccountVoter;
use Vivutio\Bundle\IdentityBundle\Service\GrantsNowService;
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

    public const string MEMBER = 'identity_team_member';

    public function __construct(
        private Environment $twig,
        private TeamDirectoryService $directory,
        private AuthorizationCheckerInterface $authorization,
        private GrantsNowService $grants,
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

    /**
     * One person's record. What they may do is shown only where their tier
     * may be seen, since "everything, by tier" names it, and on one's own.
     */
    #[Route('/team/{uuid}', name: self::MEMBER, requirements: ['uuid' => Requirement::UUID], methods: ['GET'])]
    #[IsGranted('directory.read')]
    public function member(
        #[MapEntity(mapping: ['uuid' => 'uuid'])]
        User $person,
        #[CurrentUser]
        ?User $viewer,
    ): Response {
        $seesTier = $this->authorization->isGranted(AccountVoter::SEE_TIER, $person);
        $own = null !== $viewer && $viewer->getId() === $person->getId();
        $showGrants = $seesTier || $own;

        return new Response($this->twig->render('@Identity/team/member.html.twig', [
            'person' => $person,
            'sees_tier' => $seesTier,
            'show_grants' => $showGrants,
            'grants' => $showGrants && !$person->getTier()->holdsEveryPermission() ? $this->grants->of($person) : [],
        ]));
    }

    private function withAddresses(): bool
    {
        return $this->authorization->isGranted('personal_details.read');
    }
}
