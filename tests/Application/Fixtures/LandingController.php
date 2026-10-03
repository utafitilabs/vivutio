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

namespace Vivutio\Core\Tests\Application\Fixtures;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Where a fresh sign-in lands in this application, standing in for an
 * installation's front page: it says who is signed in, and nothing else.
 */
final readonly class LandingController
{
    public function __construct(
        private TokenStorageInterface $tokens,
    ) {
    }

    #[Route('/', name: 'test_landing', methods: ['GET'])]
    public function __invoke(): Response
    {
        return new Response('Signed in as '.($this->tokens->getToken()?->getUserIdentifier() ?? 'nobody'));
    }
}
