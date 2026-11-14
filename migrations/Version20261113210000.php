<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unique idempotency tokens to fault declarations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fault ADD submission_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9FD0DEA382A8DB99 ON fault (submission_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_9FD0DEA382A8DB99 ON fault');
        $this->addSql('ALTER TABLE fault DROP submission_token');
    }
}
