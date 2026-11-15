<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 6 — Add nullable latitude/longitude to site and backfill known Tunisian cities.
 *
 * Coordinates (WGS-84):
 *   Tunis     36.8065 / 10.1815
 *   Sousse    35.8256 / 10.6084
 *   Béja      36.7256 /  9.1817
 *   Jendouba  36.5011 /  8.7803
 *
 * down() is irreversible (data loss of backfilled coordinates is acceptable;
 * the columns are nullable and can be re-added).
 */
final class Version20261115130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nullable latitude/longitude to site; backfill known Tunisian cities.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site ADD latitude DOUBLE PRECISION DEFAULT NULL, ADD longitude DOUBLE PRECISION DEFAULT NULL');

        // Backfill by city name (COLLATE so the comparison works across tables).
        $backfill = [
            'Tunis'    => [36.8065, 10.1815],
            'Sousse'   => [35.8256, 10.6084],
            'Béja'     => [36.7256, 9.1817],
            'Jendouba' => [36.5011, 8.7803],
        ];
        foreach ($backfill as $city => [$lat, $lng]) {
            $this->addSql(
                'UPDATE site SET latitude = :lat, longitude = :lng WHERE city COLLATE utf8mb4_unicode_ci = :city',
                ['lat' => $lat, 'lng' => $lng, 'city' => $city]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site DROP COLUMN latitude, DROP COLUMN longitude');
    }
}
