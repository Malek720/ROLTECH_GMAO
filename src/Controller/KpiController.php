<?php

namespace App\Controller;

use App\Service\AdminStatsProvider;
use App\Service\CsvCellEscaper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * KPI page and CSV export (phase 8, chunk 8b). Both routes are GET and read-only: they only
 * render and download, they change nothing, so there is no CSRF token (rule 37 applies to writes).
 *
 * The old window is validated once by {@see \App\Service\KpiCalculator::resolveWindow()} inside
 * {@see AdminStatsProvider::getKpiReport()}, so the page and the CSV always use the same dates.
 * A technicien is rejected by #[IsGranted] and by the /indicateurs rule in security.yaml.
 */
#[IsGranted('ROLE_RESPONSABLE')]
class KpiController extends AbstractController
{
    private const CSV_HEADER = [
        'type',
        'site',
        'machine',
        'modele',
        'statut_donnees',
        'periode',
        'nombre',
        'valeur',
        'resolution_sans_horodatage',
    ];

    public function __construct(private AdminStatsProvider $statsProvider)
    {
    }

    #[Route('/indicateurs', name: 'kpi_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $report = $this->statsProvider->getKpiReport($this->queryValue($request, 'from'), $this->queryValue($request, 'to'));

        return $this->render('kpi/index.html.twig', [
            'report' => $report,
            'chartData' => [
                'window' => $report['window'],
                'faultsPerSite' => $report['faultsPerSite'],
                'faultsPerModel' => $report['faultsPerModel'],
            ],
        ]);
    }

    #[Route('/indicateurs.csv', name: 'kpi_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $report = $this->statsProvider->getKpiReport($this->queryValue($request, 'from'), $this->queryValue($request, 'to'));

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        $this->writeRow($handle, self::CSV_HEADER);
        foreach ($this->csvRows($report) as $row) {
            $this->writeRow($handle, $row);
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        $filename = 'indicateurs_'.str_replace('-', '', $report['window']['to']).'.csv';
        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');

        return $response;
    }

    /**
     * A single CSV file holds the four tables, prefixed by a "type" column. Every cell goes through
     * {@see CsvCellEscaper} (rule 34) and the file keeps the UTF-8 BOM and the ";" delimiter.
     *
     * @param array<string,mixed> $report
     *
     * @return list<list<string|int|float|null>>
     */
    private function csvRows(array $report): array
    {
        $window = $report['window'];
        $period = $window['from'].' → '.$window['to'];
        $rows = [];

        foreach ($report['availability'] as $row) {
            $rows[] = [
                'Disponibilité',
                $row['siteName'] ?? '',
                $row['machineName'] ?? '',
                $row['modelCode'] ?? '',
                $row['dataStatus'] ?? '',
                $period,
                $row['openCritiqueFaults'] ?? 0,
                $row['availability'] === null ? 'n/d' : $row['availability'],
                $row['faultsWithoutResolutionTimestamp'] ?? 0,
            ];
        }

        $mean = $report['meanRepairTime'];
        $rows[] = [
            'Temps de réparation moyen',
            '',
            '',
            '',
            '',
            $period,
            $mean['count'],
            $mean['meanHours'] === null ? 'n/d' : $mean['meanHours'],
            '',
        ];

        foreach ($report['faultsPerSite'] as $row) {
            $rows[] = ['Pannes par site', $row['group'], '', '', '', $row['month'], $row['count'], '', ''];
        }

        foreach ($report['faultsPerModel'] as $row) {
            $rows[] = ['Pannes par modèle', '', '', $row['group'], $row['dataStatus'] ?? '', $row['month'], $row['count'], '', ''];
        }

        return $rows;
    }

    /**
     * @param resource $handle
     * @param list<string|int|float|null> $values
     */
    private function writeRow($handle, array $values): void
    {
        fputcsv($handle, array_map([CsvCellEscaper::class, 'escape'], $values), ';');
    }

    private function queryValue(Request $request, string $key): ?string
    {
        $value = $request->query->get($key);

        return \is_string($value) ? $value : null;
    }
}
