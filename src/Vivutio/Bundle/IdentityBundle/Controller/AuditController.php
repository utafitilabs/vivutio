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

use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;
use Twig\Extension\CoreExtension;
use Vivutio\Bundle\IdentityBundle\Enum\AuditKindEnum;
use Vivutio\Bundle\IdentityBundle\Repository\AuditEventRepository;
use Vivutio\Bundle\IdentityBundle\Security\AuditVoter;

/**
 * Settings › Audit log, as ruled (11 October 2026) and drawn (vivutio-designs
 * settings/audit): who changed who may do what, and every sign-in, newest
 * first, by day in the organization's time zone; found by words, kind and
 * time. Super Admins alone read it.
 */
final readonly class AuditController
{
    public const string LOG = 'identity_settings_audit';
    public const int PER_PAGE = 50;
    public const array TIMES = ['today' => 'Today', 'week' => 'The last 7 days', 'month' => 'The last 30 days'];

    public function __construct(
        private Environment $twig,
        private AuditEventRepository $events,
        private ClockInterface $clock,
    ) {
    }

    #[Route('/settings/audit', name: self::LOG, methods: ['GET'])]
    #[IsGranted(AuditVoter::READ)]
    public function log(Request $request): Response
    {
        $zone = $this->twig->getExtension(CoreExtension::class)->getTimezone();
        $kind = AuditKindEnum::tryFrom($request->query->getString('kind'));
        $time = \array_key_exists($request->query->getString('time'), self::TIMES) ? $request->query->getString('time') : null;
        $words = trim($request->query->getString('q'));
        $page = max(1, $request->query->getInt('page', 1));
        $since = $this->since($time, $zone);

        $matching = $this->events->countMatching($kind, $since, $words);
        $days = [];
        foreach ($this->events->findMatching($kind, $since, $words, self::PER_PAGE, ($page - 1) * self::PER_PAGE) as $event) {
            $days[$event->getAt()->setTimezone($zone)->format('Y-m-d')][] = $event;
        }
        $kinds = [];
        foreach (AuditKindEnum::cases() as $case) {
            $kinds[$case->value] = $this->events->countMatching($case, $since, $words);
        }
        $times = [];
        foreach (array_keys(self::TIMES) as $key) {
            $times[$key] = $this->events->countMatching($kind, $this->since($key, $zone), $words);
        }

        return new Response($this->twig->render('@Identity/settings/audit.html.twig', [
            'days' => $days,
            'query' => ['kind' => $kind?->value, 'time' => $time, 'q' => $words],
            'kind' => $kind,
            'time' => $time,
            'kinds' => AuditKindEnum::cases(),
            'kind_counts' => $kinds,
            'any_kind' => $this->events->countMatching(null, $since, $words),
            'times' => self::TIMES,
            'time_counts' => $times,
            'any_time' => $this->events->countMatching($kind, null, $words),
            'shown' => array_sum(array_map('count', $days)),
            'matching' => $matching,
            'total' => $this->events->countMatching(null, null, ''),
            'page' => $page,
            'pages' => max(1, (int) ceil($matching / self::PER_PAGE)),
            'per_page' => self::PER_PAGE,
        ]));
    }

    /** Where a time choice starts: the organization's midnight today, or seven or thirty days before. */
    private function since(?string $time, \DateTimeZone $zone): ?\DateTimeImmutable
    {
        if (null === $time) {
            return null;
        }
        $midnight = $this->clock->now()->setTimezone($zone)->setTime(0, 0);

        return match ($time) {
            'week' => $midnight->modify('-6 days'),
            'month' => $midnight->modify('-29 days'),
            default => $midnight,
        };
    }
}
