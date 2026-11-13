<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261108110609 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add sites, OC-CA1100 modules, parts, maintenance planning and notifications';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE machine_module (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(50) NOT NULL, name VARCHAR(255) NOT NULL, planche VARCHAR(255) NOT NULL, model VARCHAR(255) NOT NULL, parent_id INT DEFAULT NULL, INDEX IDX_C501896D727ACA70 (parent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE maintenance_schedule (id INT AUTO_INCREMENT NOT NULL, last_done_date DATE NOT NULL, last_done_hours INT NOT NULL, next_due_date DATE NOT NULL, next_due_hours INT NOT NULL, state VARCHAR(50) NOT NULL, machine_id INT NOT NULL, task_id INT NOT NULL, INDEX IDX_444015D6F6B75B26 (machine_id), INDEX IDX_444015D68DB60186 (task_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE maintenance_task (id INT AUTO_INCREMENT NOT NULL, action LONGTEXT NOT NULL, frequency_days INT DEFAULT NULL, frequency_hours INT DEFAULT NULL, procedure_checklist LONGTEXT DEFAULT NULL, module_id INT DEFAULT NULL, part_id INT DEFAULT NULL, INDEX IDX_9D6DBBE9AFC2B591 (module_id), INDEX IDX_9D6DBBE94CE34BEC (part_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, message LONGTEXT NOT NULL, type VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, read_at DATETIME DEFAULT NULL, recipient_id INT NOT NULL, fault_id INT DEFAULT NULL, intervention_id INT DEFAULT NULL, INDEX IDX_BF5476CAE92F8F78 (recipient_id), INDEX IDX_BF5476CA24171CD3 (fault_id), INDEX IDX_BF5476CA8EAE3863 (intervention_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE part (id INT AUTO_INCREMENT NOT NULL, part_number VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, quantity INT DEFAULT NULL, module_id INT NOT NULL, INDEX IDX_490F70C6AFC2B591 (module_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE site (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, city VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql("INSERT INTO site (name, city) VALUES ('Site par défaut', 'Non renseignée')");
        $this->addSql('ALTER TABLE machine_module ADD CONSTRAINT FK_C501896D727ACA70 FOREIGN KEY (parent_id) REFERENCES machine_module (id)');
        $this->addSql('ALTER TABLE maintenance_schedule ADD CONSTRAINT FK_444015D6F6B75B26 FOREIGN KEY (machine_id) REFERENCES machine (id)');
        $this->addSql('ALTER TABLE maintenance_schedule ADD CONSTRAINT FK_444015D68DB60186 FOREIGN KEY (task_id) REFERENCES maintenance_task (id)');
        $this->addSql('ALTER TABLE maintenance_task ADD CONSTRAINT FK_9D6DBBE9AFC2B591 FOREIGN KEY (module_id) REFERENCES machine_module (id)');
        $this->addSql('ALTER TABLE maintenance_task ADD CONSTRAINT FK_9D6DBBE94CE34BEC FOREIGN KEY (part_id) REFERENCES part (id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAE92F8F78 FOREIGN KEY (recipient_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA24171CD3 FOREIGN KEY (fault_id) REFERENCES fault (id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA8EAE3863 FOREIGN KEY (intervention_id) REFERENCES intervention (id)');
        $this->addSql('ALTER TABLE part ADD CONSTRAINT FK_490F70C6AFC2B591 FOREIGN KEY (module_id) REFERENCES machine_module (id)');
        $this->addSql('ALTER TABLE fault ADD symptom VARCHAR(255) DEFAULT NULL, ADD module_id INT DEFAULT NULL, ADD part_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE fault ADD CONSTRAINT FK_9FD0DEA3AFC2B591 FOREIGN KEY (module_id) REFERENCES machine_module (id)');
        $this->addSql('ALTER TABLE fault ADD CONSTRAINT FK_9FD0DEA34CE34BEC FOREIGN KEY (part_id) REFERENCES part (id)');
        $this->addSql('CREATE INDEX IDX_9FD0DEA3AFC2B591 ON fault (module_id)');
        $this->addSql('CREATE INDEX IDX_9FD0DEA34CE34BEC ON fault (part_id)');
        $this->addSql('ALTER TABLE intervention ADD schedule_id INT DEFAULT NULL, CHANGE fault_id fault_id INT DEFAULT NULL, CHANGE technician_id technician_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE intervention ADD CONSTRAINT FK_D11814ABA40BC2D5 FOREIGN KEY (schedule_id) REFERENCES maintenance_schedule (id)');
        $this->addSql('CREATE INDEX IDX_D11814ABA40BC2D5 ON intervention (schedule_id)');
        $this->addSql('ALTER TABLE machine ADD operating_hours_correction INT DEFAULT 0, ADD site_id INT DEFAULT NULL');
        $this->addSql("UPDATE machine SET site_id = (SELECT id FROM site WHERE name = 'Site par défaut' ORDER BY id ASC LIMIT 1) WHERE site_id IS NULL");
        $this->addSql('ALTER TABLE machine CHANGE operating_hours_correction operating_hours_correction INT NOT NULL');
        $this->addSql('ALTER TABLE machine ADD CONSTRAINT FK_1505DF84F6BD1646 FOREIGN KEY (site_id) REFERENCES site (id)');
        $this->addSql('CREATE INDEX IDX_1505DF84F6BD1646 ON machine (site_id)');
        $this->addSql('ALTER TABLE user ADD site_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649F6BD1646 FOREIGN KEY (site_id) REFERENCES site (id)');
        $this->addSql('CREATE INDEX IDX_8D93D649F6BD1646 ON user (site_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE machine_module DROP FOREIGN KEY FK_C501896D727ACA70');
        $this->addSql('ALTER TABLE maintenance_schedule DROP FOREIGN KEY FK_444015D6F6B75B26');
        $this->addSql('ALTER TABLE maintenance_schedule DROP FOREIGN KEY FK_444015D68DB60186');
        $this->addSql('ALTER TABLE maintenance_task DROP FOREIGN KEY FK_9D6DBBE9AFC2B591');
        $this->addSql('ALTER TABLE maintenance_task DROP FOREIGN KEY FK_9D6DBBE94CE34BEC');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAE92F8F78');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA24171CD3');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA8EAE3863');
        $this->addSql('ALTER TABLE part DROP FOREIGN KEY FK_490F70C6AFC2B591');
        $this->addSql('DROP TABLE machine_module');
        $this->addSql('DROP TABLE maintenance_schedule');
        $this->addSql('DROP TABLE maintenance_task');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE part');
        $this->addSql('DROP TABLE site');
        $this->addSql('ALTER TABLE fault DROP FOREIGN KEY FK_9FD0DEA3AFC2B591');
        $this->addSql('ALTER TABLE fault DROP FOREIGN KEY FK_9FD0DEA34CE34BEC');
        $this->addSql('DROP INDEX IDX_9FD0DEA3AFC2B591 ON fault');
        $this->addSql('DROP INDEX IDX_9FD0DEA34CE34BEC ON fault');
        $this->addSql('ALTER TABLE fault DROP symptom, DROP module_id, DROP part_id');
        $this->addSql('ALTER TABLE intervention DROP FOREIGN KEY FK_D11814ABA40BC2D5');
        $this->addSql('DROP INDEX IDX_D11814ABA40BC2D5 ON intervention');
        $this->addSql('ALTER TABLE intervention DROP schedule_id, CHANGE fault_id fault_id INT NOT NULL, CHANGE technician_id technician_id INT NOT NULL');
        $this->addSql('ALTER TABLE machine DROP FOREIGN KEY FK_1505DF84F6BD1646');
        $this->addSql('DROP INDEX IDX_1505DF84F6BD1646 ON machine');
        $this->addSql('ALTER TABLE machine DROP operating_hours_correction, DROP site_id');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649F6BD1646');
        $this->addSql('DROP INDEX IDX_8D93D649F6BD1646 ON user');
        $this->addSql('ALTER TABLE user DROP site_id');
    }
}
