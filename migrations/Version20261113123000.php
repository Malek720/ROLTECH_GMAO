<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add machine models, migrate existing model text, add model-owned catalog data and archiveable schedules';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE machine_model (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(100) NOT NULL, name VARCHAR(255) NOT NULL, family VARCHAR(255) NOT NULL, max_load_kg INT DEFAULT NULL, data_status VARCHAR(20) NOT NULL, notes LONGTEXT DEFAULT NULL, UNIQUE INDEX UNIQ_MACHINE_MODEL_CODE (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql("INSERT INTO machine_model (code, name, family, max_load_kg, data_status, notes) VALUES ('OC-CA1100', 'Convoyeur aérien OC-CA1100', 'Convoyeur aérien (crochets)', 15, 'confirmed', NULL), ('AUTRE', 'Autre / à classer', 'À confirmer', NULL, 'demo', 'Modèle créé pour préserver une ancienne valeur non reconnue.')");

        $this->addSql('ALTER TABLE machine ADD machine_model_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE machine_module ADD data_status VARCHAR(20) NOT NULL DEFAULT \'demo\', ADD machine_model_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE maintenance_task ADD data_status VARCHAR(20) NOT NULL DEFAULT \'demo\', ADD machine_model_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE maintenance_schedule ADD archived_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE part ADD data_status VARCHAR(20) NOT NULL DEFAULT \'demo\', CHANGE part_number part_number VARCHAR(255) DEFAULT NULL');

        $this->addSql("UPDATE machine m INNER JOIN machine_model mm ON mm.code COLLATE utf8mb4_unicode_ci = m.model COLLATE utf8mb4_unicode_ci SET m.machine_model_id = mm.id WHERE m.model = 'OC-CA1100'");
        $this->addSql("UPDATE machine SET machine_model_id = (SELECT id FROM machine_model WHERE code = 'AUTRE') WHERE machine_model_id IS NULL");
        $this->addSql("UPDATE machine_module m INNER JOIN machine_model mm ON mm.code COLLATE utf8mb4_unicode_ci = m.model COLLATE utf8mb4_unicode_ci SET m.machine_model_id = mm.id WHERE m.model = 'OC-CA1100'");
        $this->addSql("UPDATE machine_module SET machine_model_id = (SELECT id FROM machine_model WHERE code = 'AUTRE') WHERE machine_model_id IS NULL");
        $this->addSql("UPDATE maintenance_task t INNER JOIN machine_module m ON t.module_id = m.id SET t.machine_model_id = m.machine_model_id WHERE t.module_id IS NOT NULL");
        $this->addSql("UPDATE maintenance_task t INNER JOIN part p ON t.part_id = p.id INNER JOIN machine_module m ON p.module_id = m.id SET t.machine_model_id = m.machine_model_id WHERE t.machine_model_id IS NULL AND t.part_id IS NOT NULL");
        $this->addSql("UPDATE maintenance_task SET machine_model_id = (SELECT id FROM machine_model WHERE code = 'AUTRE') WHERE machine_model_id IS NULL");
        $this->addSql("UPDATE machine_module SET data_status = CASE WHEN machine_model_id = (SELECT id FROM machine_model WHERE code = 'OC-CA1100') THEN 'confirmed' ELSE 'demo' END");
        $this->addSql("UPDATE part p INNER JOIN machine_module m ON p.module_id = m.id SET p.data_status = CASE WHEN m.machine_model_id = (SELECT id FROM machine_model WHERE code = 'OC-CA1100') THEN 'confirmed' ELSE 'demo' END");
        $this->addSql("UPDATE maintenance_task SET data_status = CASE WHEN machine_model_id = (SELECT id FROM machine_model WHERE code = 'OC-CA1100') THEN 'confirmed' ELSE 'demo' END");

        $this->addSql('ALTER TABLE machine DROP model, CHANGE machine_model_id machine_model_id INT NOT NULL');
        $this->addSql('ALTER TABLE machine ADD CONSTRAINT FK_1505DF84B2127BFB FOREIGN KEY (machine_model_id) REFERENCES machine_model (id)');
        $this->addSql('CREATE INDEX IDX_1505DF84B2127BFB ON machine (machine_model_id)');

        $this->addSql('ALTER TABLE machine_module DROP model, CHANGE machine_model_id machine_model_id INT NOT NULL');
        $this->addSql('ALTER TABLE machine_module ADD CONSTRAINT FK_C501896DB2127BFB FOREIGN KEY (machine_model_id) REFERENCES machine_model (id)');
        $this->addSql('CREATE INDEX IDX_C501896DB2127BFB ON machine_module (machine_model_id)');
        $legacyIndexes = $this->connection->fetchAllAssociative("SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'machine_module' AND NON_UNIQUE = 0 AND INDEX_NAME <> 'PRIMARY' AND COLUMN_NAME = 'code'");
        foreach ($legacyIndexes as $legacyIndex) {
            $this->addSql('DROP INDEX '.$this->connection->quoteIdentifier($legacyIndex['INDEX_NAME']).' ON machine_module');
        }
        $this->addSql('CREATE UNIQUE INDEX UNIQ_MACHINE_MODULE_MODEL_CODE ON machine_module (machine_model_id, code)');

        $this->addSql('ALTER TABLE maintenance_task CHANGE machine_model_id machine_model_id INT NOT NULL');
        $this->addSql('ALTER TABLE maintenance_task ADD CONSTRAINT FK_9D6DBBE9B2127BFB FOREIGN KEY (machine_model_id) REFERENCES machine_model (id)');
        $this->addSql('CREATE INDEX IDX_9D6DBBE9B2127BFB ON maintenance_task (machine_model_id)');
        $this->addSql('ALTER TABLE machine_module ALTER data_status DROP DEFAULT');
        $this->addSql('ALTER TABLE part ALTER data_status DROP DEFAULT');
        $this->addSql('ALTER TABLE maintenance_task ALTER data_status DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        throw new \RuntimeException('Migration irréversible : restaurez la sauvegarde de la base de données.');
    }
}
