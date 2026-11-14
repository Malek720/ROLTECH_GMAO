<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261113205742 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE maintenance_postponement (
              id INT AUTO_INCREMENT NOT NULL,
              reason LONGTEXT NOT NULL,
              previous_due_date DATE NOT NULL,
              new_due_date DATE NOT NULL,
              created_at DATETIME NOT NULL,
              schedule_id INT NOT NULL,
              postponed_by_id INT NOT NULL,
              INDEX IDX_FDFE7617A40BC2D5 (schedule_id),
              INDEX IDX_FDFE76171734B47F (postponed_by_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              maintenance_postponement
            ADD
              CONSTRAINT FK_FDFE7617A40BC2D5 FOREIGN KEY (schedule_id) REFERENCES maintenance_schedule (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              maintenance_postponement
            ADD
              CONSTRAINT FK_FDFE76171734B47F FOREIGN KEY (postponed_by_id) REFERENCES user (id)
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_MACHINE_SERIAL_NUMBER ON machine (serial_number)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE maintenance_postponement DROP FOREIGN KEY FK_FDFE7617A40BC2D5');
        $this->addSql('ALTER TABLE maintenance_postponement DROP FOREIGN KEY FK_FDFE76171734B47F');
        $this->addSql('DROP TABLE maintenance_postponement');
        $this->addSql('DROP INDEX UNIQ_MACHINE_SERIAL_NUMBER ON machine');
    }
}
