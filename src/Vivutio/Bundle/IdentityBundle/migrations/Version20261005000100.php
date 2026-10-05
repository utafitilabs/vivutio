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
 * Places: a posting and where a department sits are kept as the place's kind
 * and id, so a package's place, a property, is posted at like an office.
 * Postings and department places at offices are not carried over; they are
 * chosen anew.
 */
final class Version20261005000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'identity_user.posted_kind, posted_id and identity_department.place_kind, place_id in place of the office links';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity_user DROP CONSTRAINT FK_USER_POSTED_AT');
        $this->addSql('DROP INDEX IDX_39E1FCDEFA78038');
        $this->addSql('ALTER TABLE identity_user DROP posted_at_id');
        $this->addSql('UPDATE identity_user SET posted_since = NULL');
        $this->addSql('ALTER TABLE identity_user ADD posted_kind VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE identity_user ADD posted_id UUID DEFAULT NULL');

        $this->addSql('ALTER TABLE identity_department DROP CONSTRAINT FK_DEPT_OFFICE');
        $this->addSql('DROP INDEX IDX_B5F8D3B9FFA0C224');
        $this->addSql('ALTER TABLE identity_department DROP office_id');
        $this->addSql('ALTER TABLE identity_department ADD place_kind VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE identity_department ADD place_id UUID DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity_department DROP place_kind, DROP place_id');
        $this->addSql('ALTER TABLE identity_department ADD office_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE identity_department ADD CONSTRAINT FK_DEPT_OFFICE FOREIGN KEY (office_id) REFERENCES identity_office (id)');
        $this->addSql('CREATE INDEX IDX_B5F8D3B9FFA0C224 ON identity_department (office_id)');
        $this->addSql('ALTER TABLE identity_user DROP posted_kind, DROP posted_id');
        $this->addSql('ALTER TABLE identity_user ADD posted_at_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE identity_user ADD CONSTRAINT FK_USER_POSTED_AT FOREIGN KEY (posted_at_id) REFERENCES identity_office (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_39E1FCDEFA78038 ON identity_user (posted_at_id)');
    }
}
