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
 * The team list and its export.
 *
 * What a viewer sees of each person is asked of the voters here, once, and
 * passed on: whether they see tiers and whether they may read addresses. The
 * list, its filters and its export follow the same two answers, so a column a
 * viewer is not shown is never a filter or a cell of the file either.
 *
 * It extends nothing and is handed what it uses, as the framework's own
 * controllers are.
 *
 * @see vendor/symfony/framework-bundle/Controller/TemplateController.php
 */
final readonly class TeamController
{
    public const string TEAM = 'identity_team';
    public const string EXPORT = 'identity_team_export';

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

    /**
     * Everybody the list's filter matches, on every page, as a file: the
     * columns the viewer is shown, and no other.
     */
    #[Route('/team/export', name: self::EXPORT, methods: ['GET'])]
    #[IsGranted('directory.export')]
    public function export(Request $request): Response
    {
        $withTiers = $this->authorization->isGranted(AccountVoter::SEE_TIERS);
        $withAddresses = $this->withAddresses();

        $rows = [['Name', ...$withAddresses ? ['Email'] : [], ...$withTiers ? ['Tier'] : [], 'Position', 'Account']];
        foreach ($this->directory->all(TeamQuery::fromRequest($request), $withTiers, $withAddresses) as $person) {
            $rows[] = [
                $person->getFullName(),
                ...$withAddresses ? [(string) $person->getEmail()] : [],
                ...$withTiers ? [$person->getTier()->label()] : [],
                (string) $person->getPosition()?->getName(),
                $person->isActive() ? 'Active' : 'Deactivated',
            ];
        }

        return new Response(self::csv($rows), Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="team.csv"',
        ]);
    }

    private function withAddresses(): bool
    {
        return $this->authorization->isGranted('personal_details.read');
    }

    /**
     * A spreadsheet runs a cell that starts with = + - or @ as a formula, so
     * such a cell is written as text.
     *
     * @param list<list<string>> $rows
     *
     * @see https://owasp.org/www-community/attacks/CSV_Injection
     */
    private static function csv(array $rows): string
    {
        $file = fopen('php://temp', 'r+');
        \assert(false !== $file);

        foreach ($rows as $row) {
            fputcsv($file, array_map(
                static fn (string $cell): string => 1 === preg_match('/^[=+\-@]/', $cell) ? "'".$cell : $cell,
                $row,
            ), escape: '');
        }

        rewind($file);
        $csv = (string) stream_get_contents($file);
        fclose($file);

        return $csv;
    }
}
