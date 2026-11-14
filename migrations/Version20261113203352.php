<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113203352 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add intervention workflow state and replaced parts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE intervention_part (id INT AUTO_INCREMENT NOT NULL, quantity INT NOT NULL, intervention_id INT NOT NULL, part_id INT NOT NULL, INDEX IDX_46B068048EAE3863 (intervention_id), INDEX IDX_46B068044CE34BEC (part_id), UNIQUE INDEX UNIQ_INTERVENTION_PART (intervention_id, part_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE intervention_part ADD CONSTRAINT FK_46B068048EAE3863 FOREIGN KEY (intervention_id) REFERENCES intervention (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE intervention_part ADD CONSTRAINT FK_46B068044CE34BEC FOREIGN KEY (part_id) REFERENCES part (id)');
        $this->addSql('ALTER TABLE intervention ADD started_at DATETIME DEFAULT NULL, ADD lockout_confirmed_at DATETIME DEFAULT NULL, ADD checklist_progress JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE intervention_part DROP FOREIGN KEY FK_46B068048EAE3863');
        $this->addSql('ALTER TABLE intervention_part DROP FOREIGN KEY FK_46B068044CE34BEC');
        $this->addSql('DROP TABLE intervention_part');
        $this->addSql('ALTER TABLE intervention DROP started_at, DROP lockout_confirmed_at, DROP checklist_progress');
    }
}
