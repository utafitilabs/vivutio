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

namespace Vivutio\Contracts\Settings;

/**
 * Whose installation this is: the name it is known by, and where in the
 * world it is.
 *
 * Every field states its consequence on the screen, which is why they are
 * separate fields rather than a bag: the short name is drawn where the full
 * one will not fit, the time zone is the one a page reads an instant in when
 * the viewer has none of their own, the country is where the organization
 * works. A field with no stated consequence is one nobody can decide.
 *
 * A null is "not set", and the screen says so. The logo is null until the
 * core can store files; the screen then draws vivutio's mark.
 */
final readonly class OrganizationIdentity
{
    /**
     * @param string      $name      the full name, heading the menu and the dashboard
     * @param string|null $shortName drawn where the full name will not fit
     * @param string|null $logo      a URL to the organization's mark, or null for vivutio's
     * @param string|null $timeZone  an IANA zone name, e.g. "Africa/Dar_es_Salaam"
     * @param string|null $country   where it works, in the reader's words
     */
    public function __construct(
        public string $name,
        public ?string $shortName = null,
        public ?string $logo = null,
        public ?string $timeZone = null,
        public ?string $country = null,
    ) {
        if ('' === trim($name)) {
            throw new \InvalidArgumentException('An organization is known by its name: it cannot be empty.');
        }
    }
}
