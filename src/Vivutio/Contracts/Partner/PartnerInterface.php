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

namespace Vivutio\Contracts\Partner;

/**
 * An organization the installation's organization trades with, as a module
 * reads it: a booking made by a tour operator, a room requested from a camp.
 * Partners never sign in; they are records the organization keeps.
 */
interface PartnerInterface
{
    /** Its stable id, the one a module stores. */
    public function getPartnerId(): string;

    public function getName(): string;

    /** What it is to the organization: tour_operator, travel_agent, accommodation or supplier. */
    public function getPartnerKind(): string;

    /** The discount it trades at, a share to the cent: "12.50"; "0.00" for none. */
    public function getDiscount(): string;

    /** The days it has to pay, 0 for on booking. */
    public function getCreditDays(): int;

    /** The address a request to it leaves for, by the manual channel. */
    public function getEmail(): string;

    /** Whether it is offered for new business; an archived partner keeps what it has. */
    public function isActive(): bool;
}
