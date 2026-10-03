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
 * Invitations: whether an account has named itself and chosen a password,
 * and who invited it when. Every account already there was made with a
 * password, so it is verified.
 */
final class Version20261003000400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'identity_user.verified, invited_at, invited_by_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity_user ADD verified BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('ALTER TABLE identity_user ADD invited_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE identity_user ADD invited_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE identity_user ADD CONSTRAINT FK_INVITED_BY FOREIGN KEY (invited_by_id) REFERENCES identity_user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_39E1FCDA7B4A7E3 ON identity_user (invited_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity_user DROP CONSTRAINT FK_INVITED_BY');
        $this->addSql('DROP INDEX IDX_39E1FCDA7B4A7E3');
        $this->addSql('ALTER TABLE identity_user DROP verified, DROP invited_at, DROP invited_by_id');
    }
}
