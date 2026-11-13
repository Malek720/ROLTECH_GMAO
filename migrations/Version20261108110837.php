<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261108110837 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Record intervention assignment source and responsable';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("ALTER TABLE intervention ADD assignment_source VARCHAR(30) NOT NULL DEFAULT 'systeme', ADD assigned_by_id INT DEFAULT NULL");
        $this->addSql('ALTER TABLE intervention ADD CONSTRAINT FK_D11814AB6E6F1246 FOREIGN KEY (assigned_by_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_D11814AB6E6F1246 ON intervention (assigned_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE intervention DROP FOREIGN KEY FK_D11814AB6E6F1246');
        $this->addSql('DROP INDEX IDX_D11814AB6E6F1246 ON intervention');
        $this->addSql('ALTER TABLE intervention DROP assignment_source, DROP assigned_by_id');
    }
}
