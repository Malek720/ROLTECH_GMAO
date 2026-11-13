<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261108112255 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow safe deletion of nested machine modules';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE machine_module DROP FOREIGN KEY `FK_C501896D727ACA70`');
        $this->addSql('ALTER TABLE machine_module ADD CONSTRAINT FK_C501896D727ACA70 FOREIGN KEY (parent_id) REFERENCES machine_module (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE machine_module DROP FOREIGN KEY FK_C501896D727ACA70');
        $this->addSql('ALTER TABLE machine_module ADD CONSTRAINT `FK_C501896D727ACA70` FOREIGN KEY (parent_id) REFERENCES machine_module (id)');
    }
}
