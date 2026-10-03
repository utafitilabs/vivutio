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

namespace Vivutio\Bundle\IdentityBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The organization the installation belongs to: one row, under a fixed key.
 */
final class Version20261003000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'identity_organization';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE identity_organization (
              id INT NOT NULL,
              name VARCHAR(160) NOT NULL,
              short_name VARCHAR(12) DEFAULT NULL,
              time_zone VARCHAR(64) DEFAULT NULL,
              country VARCHAR(80) DEFAULT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              PRIMARY KEY (id)
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE identity_organization');
    }
}
