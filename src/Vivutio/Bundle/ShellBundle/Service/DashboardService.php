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

namespace Vivutio\Bundle\ShellBundle\Service;

use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Vivutio\Contracts\Shell\DashboardCard;
use Vivutio\Contracts\Shell\DashboardFigure;
use Vivutio\Contracts\Shell\DashboardSourceInterface;

/**
 * The organization's dashboard as the installed packages make it: their
 * figures and cards, each kept only for whoever holds the pair it names and
 * whose page the installation mounts, in their order.
 */
final readonly class DashboardService
{
    /**
     * @param iterable<DashboardSourceInterface> $sources
     */
    public function __construct(
        private iterable $sources,
        private AuthorizationCheckerInterface $authorization,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * @return list<DashboardFigure>
     */
    public function figures(): array
    {
        $figures = [];
        foreach ($this->sources as $source) {
            foreach ($source->figures() as $figure) {
                if ($this->authorization->isGranted($figure->attribute)) {
                    $figures[] = $figure;
                }
            }
        }
        usort($figures, static fn (DashboardFigure $a, DashboardFigure $b): int => $a->order <=> $b->order);

        return $figures;
    }

    /**
     * The cards dealt into two columns in turn, the first left, the second right.
     *
     * @return array{0: list<DashboardCard>, 1: list<DashboardCard>}
     */
    public function columns(): array
    {
        $cards = [];
        foreach ($this->sources as $source) {
            foreach ($source->cards() as $card) {
                if ($this->mounted($card) && $this->authorization->isGranted($card->attribute)) {
                    $cards[] = $card;
                }
            }
        }
        usort($cards, static fn (DashboardCard $a, DashboardCard $b): int => $a->order <=> $b->order);

        $columns = [[], []];
        foreach ($cards as $i => $card) {
            $columns[$i % 2][] = $card;
        }

        return $columns;
    }

    private function mounted(DashboardCard $card): bool
    {
        try {
            $this->urls->generate($card->route, $card->routeParameters);
        } catch (RouteNotFoundException) {
            return false;
        } catch (MissingMandatoryParametersException) {
            return true;
        }

        return true;
    }
}
