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

namespace Vivutio\Bundle\PlaceBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A destination's fee may be for an activity there, named.
 */
final class Version20261007000800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'place_destination_fee.activity';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE place_destination_fee ADD activity VARCHAR(60) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE place_destination_fee DROP activity');
    }
}
