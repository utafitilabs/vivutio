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
 * A person's work number. Added beside the rows already there, empty for each.
 */
final class Version20261003000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'identity_user.phone';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity_user ADD phone VARCHAR(32) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity_user DROP phone');
    }
}
