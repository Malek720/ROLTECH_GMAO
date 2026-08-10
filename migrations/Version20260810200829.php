<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260810200829 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE fault (id INT AUTO_INCREMENT NOT NULL, reported_at DATETIME NOT NULL, description LONGTEXT NOT NULL, severity VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, machine_id INT NOT NULL, reported_by_id INT NOT NULL, INDEX IDX_9FD0DEA3F6B75B26 (machine_id), INDEX IDX_9FD0DEA371CE806 (reported_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE intervention (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(255) NOT NULL, start_date DATETIME NOT NULL, end_date DATETIME DEFAULT NULL, status VARCHAR(255) NOT NULL, comment LONGTEXT DEFAULT NULL, fault_id INT NOT NULL, technician_id INT NOT NULL, INDEX IDX_D11814AB24171CD3 (fault_id), INDEX IDX_D11814ABE6C5D496 (technician_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE machine (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, model VARCHAR(255) NOT NULL, serial_number VARCHAR(255) NOT NULL, installation_date DATE NOT NULL, category VARCHAR(255) NOT NULL, location VARCHAR(255) DEFAULT NULL, status VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, full_name VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE fault ADD CONSTRAINT FK_9FD0DEA3F6B75B26 FOREIGN KEY (machine_id) REFERENCES machine (id)');
        $this->addSql('ALTER TABLE fault ADD CONSTRAINT FK_9FD0DEA371CE806 FOREIGN KEY (reported_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE intervention ADD CONSTRAINT FK_D11814AB24171CD3 FOREIGN KEY (fault_id) REFERENCES fault (id)');
        $this->addSql('ALTER TABLE intervention ADD CONSTRAINT FK_D11814ABE6C5D496 FOREIGN KEY (technician_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE fault DROP FOREIGN KEY FK_9FD0DEA3F6B75B26');
        $this->addSql('ALTER TABLE fault DROP FOREIGN KEY FK_9FD0DEA371CE806');
        $this->addSql('ALTER TABLE intervention DROP FOREIGN KEY FK_D11814AB24171CD3');
        $this->addSql('ALTER TABLE intervention DROP FOREIGN KEY FK_D11814ABE6C5D496');
        $this->addSql('DROP TABLE fault');
        $this->addSql('DROP TABLE intervention');
        $this->addSql('DROP TABLE machine');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
