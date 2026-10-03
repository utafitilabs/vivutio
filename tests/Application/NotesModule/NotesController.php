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

namespace Vivutio\Core\Tests\Application\NotesModule;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * A stand-in module's pages, so the authority test base is shown to hold a
 * module to the same proofs as the core: a list anybody holding notes.read
 * may open, and a write that records nothing.
 */
final readonly class NotesController
{
    public const string LIST = 'notes_list';

    public const string WRITE = 'notes_write';

    #[Route('/notes', name: self::LIST, methods: ['GET'])]
    #[IsGranted('notes.read')]
    public function list(): Response
    {
        return new Response('<!doctype html><title>Notes</title><p>No notes yet.</p>');
    }

    #[Route('/notes', name: self::WRITE, methods: ['POST'])]
    #[IsGranted('notes.record')]
    public function write(): Response
    {
        return new RedirectResponse('/notes');
    }
}
