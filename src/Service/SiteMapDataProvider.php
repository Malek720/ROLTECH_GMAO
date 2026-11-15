<?php

namespace App\Service;

use App\Entity\Site;
use App\Entity\User;
use App\Repository\SiteRepository;

/**
 * Builds the data array consumed by the Leaflet map.
 *
 * Scoping rule (rule 26): admin and responsable see every site;
 * a technicien sees only their own site (or nothing if they have no site).
 *
 * Marker colour by worst machine state (rule 9):
 *   red    — any machine "en panne" OR any open "critique" fault
 *   orange — any active (non-archived) schedule in state "echue" or "proche"
 *   green  — otherwise
 *
 * The method is pure (no side effects) and returns plain arrays so the
 * controller can pass them directly to the Twig template as JSON.
 */
final class SiteMapDataProvider
{
    public function __construct(private readonly SiteRepository $siteRepository) {}

    /**
     * @return list<array{
     *   id: int,
     *   name: string,
     *   city: string,
     *   latitude: float|null,
     *   longitude: float|null,
     *   color: string,
     *   machine_counts: array<string, int>,
     *   model_counts: array<string, int>,
     *   open_fault_count: int,
     * }>
     */
    public function forUser(User $user): array
    {
        $sites = $this->visibleSites($user);

        $result = [];
        foreach ($sites as $site) {
            $result[] = $this->buildSiteData($site);
        }

        return $result;
    }

    /**
     * Whether a user may view a given site page.
     * Admin / responsable: any site. Technicien: only their own site.
     */
    public function canViewSite(User $user, Site $site): bool
    {
        if ($this->seesAllSites($user)) {
            return true;
        }

        return $user->getSite() !== null && $user->getSite()->getId() === $site->getId();
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /** @return list<Site> */
    private function visibleSites(User $user): array
    {
        if ($this->seesAllSites($user)) {
            return $this->siteRepository->findAll();
        }

        $site = $user->getSite();

        return $site !== null ? [$site] : [];
    }

    private function seesAllSites(User $user): bool
    {
        $roles = $user->getRoles();

        return in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_RESPONSABLE', $roles, true);
    }

    /**
     * @return array{
     *   id: int,
     *   name: string,
     *   city: string,
     *   latitude: float|null,
     *   longitude: float|null,
     *   color: string,
     *   machine_counts: array<string, int>,
     *   model_counts: array<string, int>,
     *   open_fault_count: int,
     * }
     */
    private function buildSiteData(Site $site): array
    {
        $machineCounts = [];
        $modelCounts   = [];
        $openFaultCount = 0;
        $color = 'green';

        foreach ($site->getMachines() as $machine) {
            // Status counts
            $status = $machine->getStatus() ?? 'inconnu';
            $machineCounts[$status] = ($machineCounts[$status] ?? 0) + 1;

            // Model counts
            $modelName = $machine->getMachineModel()?->getName() ?? 'Inconnu';
            $modelCounts[$modelName] = ($modelCounts[$modelName] ?? 0) + 1;

            // Red: machine "en panne"
            if ($machine->getStatus() === 'en panne') {
                $color = $this->worstColor($color, 'red');
            }

            // Red: open critique fault
            foreach ($machine->getFaults() as $fault) {
                if ($fault->getStatus() !== 'resolue') {
                    ++$openFaultCount;
                    if ($fault->getSeverity() === 'critique') {
                        $color = $this->worstColor($color, 'red');
                    }
                }
            }

            // Orange: active schedule echue or proche
            if ($color !== 'red') {
                foreach ($machine->getMaintenanceSchedules() as $schedule) {
                    if ($schedule->getArchivedAt() !== null) {
                        continue;
                    }
                    if (in_array($schedule->getState(), ['echue', 'proche'], true)) {
                        $color = $this->worstColor($color, 'orange');
                    }
                }
            }
        }

        return [
            'id'              => (int) $site->getId(),
            'name'            => (string) $site->getName(),
            'city'            => (string) $site->getCity(),
            'latitude'        => $site->getLatitude(),
            'longitude'       => $site->getLongitude(),
            'color'           => $color,
            'machine_counts'  => $machineCounts,
            'model_counts'    => $modelCounts,
            'open_fault_count'=> $openFaultCount,
        ];
    }

    /**
     * Returns the most severe of two colours (red > orange > green).
     */
    public function worstColor(string $current, string $candidate): string
    {
        $rank = ['green' => 0, 'orange' => 1, 'red' => 2];

        return ($rank[$candidate] ?? 0) > ($rank[$current] ?? 0) ? $candidate : $current;
    }
}
