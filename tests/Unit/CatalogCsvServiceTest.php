<?php

namespace App\Tests\Unit;

use App\Entity\MachineModel;
use App\Entity\MachineModule;
use App\Entity\Part;
use App\Service\CatalogCsvService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class CatalogCsvServiceTest extends TestCase
{
    private const HEADER = "record_type;module_code;parent_module_code;name;part_number;quantity;description;action;frequency_days;frequency_hours;procedure;data_status\n";

    private function service(?EntityRepository $repository = null): CatalogCsvService
    {
        $repository ??= $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        return new CatalogCsvService($entityManager);
    }

    private function model(): MachineModel
    {
        return (new MachineModel())->setCode('DEMO')->setName('Demo')->setFamily('Test');
    }

    public function testExportEscapesFormulaLeadingTextAndPreviewDoesNotWrite(): void
    {
        $model = $this->model();
        $module = (new MachineModule())->setMachineModel($model)->setCode('M1')->setName('Module')->setPlanche('P1');
        $module->getParts()->add((new Part())->setModule($module)->setDescription('=FORMULA')->setPartNumber(null));
        $model->getModules()->add($module);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $service = new CatalogCsvService($entityManager);
        $csv = $service->export($model);
        self::assertStringContainsString("'=FORMULA", $csv);

        $preview = $service->preview($csv, $model);
        self::assertSame([], $preview['errors']);
        self::assertSame(2, $preview['counts']['create']);
    }

    public function testBlockingErrorsReportLineAndColumn(): void
    {
        $model = $this->model();
        $service = $this->service();
        $preview = $service->preview(
            "record_type;module_code;parent_module_code;name;part_number;quantity;description;action;frequency_days;frequency_hours;procedure;data_status\n".
            "task;UNKNOWN;;;;;;;0;;;\n",
            $model,
        );

        self::assertNotEmpty($preview['errors']);
        self::assertSame(2, $preview['errors'][0]['line']);
        self::assertContains('frequency_days', array_column($preview['errors'], 'column'));
    }

    /**
     * @dataProvider blockingCsvProvider
     */
    public function testEachBlockingCsvErrorReportsItsLineAndColumn(string $csv, string $column, int $line): void
    {
        $preview = $this->service()->preview(self::HEADER.$csv, $this->model());

        self::assertNotEmpty($preview['errors']);
        self::assertContains($line, array_column($preview['errors'], 'line'));
        self::assertContains($column, array_column($preview['errors'], 'column'));
    }

    /** @return iterable<string, array{0:string,1:string}> */
    public static function blockingCsvProvider(): iterable
    {
        yield 'unknown record type' => ["unknown;;;;;;;;;;;\n", 'record_type', 2];
        yield 'missing module code' => ["module;; ;Name;;;;;;;;\n", 'module_code', 2];
        yield 'missing part name' => ["part;M1;;;PN;1;;;;;;\n", 'name', 2];
        yield 'unknown module reference' => ["part;UNKNOWN;;Part;;1;;;;;;\n", 'module_code', 2];
        yield 'invalid status' => [implode(';', ['module', 'M1', '', 'Name', '', '', '', '', '', '', '', 'invalid'])."\n", 'data_status', 2];
        yield 'invalid quantity' => ["part;M1;;Part;;0;;;;;;\n", 'quantity', 2];
        yield 'missing task action' => ["task;M1;;;;;;;7;;;\n", 'action', 2];
        yield 'missing task frequency' => ["task;M1;;;;;;Action;;;;\n", 'frequency_days', 2];
        yield 'invalid task frequency' => ["task;M1;;;;;;Action;0;;;\n", 'frequency_days', 2];
        yield 'unknown parent' => ["module;M1;UNKNOWN;Name;;;;;;;;\n", 'parent_module_code', 2];
        yield 'duplicate module code' => ["module;M1;;Name;;;;;;;;\nmodule;M1;;Other;;;;;;;;\n", 'module_code', 3];
        yield 'parent loop' => ["module;M1;M2;One;;;;;;;;\nmodule;M2;M1;Two;;;;;;;;\n", 'parent_module_code', 2];
    }

    public function testHeaderAndRowShapeErrorsAreReported(): void
    {
        $service = $this->service();
        $header = $service->preview("wrong\n", $this->model());
        $shape = $service->preview(self::HEADER."module;M1;Name\n", $this->model());

        self::assertSame('header', $header['errors'][0]['column']);
        self::assertSame(2, $shape['errors'][0]['line']);
        self::assertSame('row', $shape['errors'][0]['column']);
    }

    public function testBomUtf8AndBinaryLimitsAreRejected(): void
    {
        $service = $this->service();
        self::assertSame([], $service->preview("\xEF\xBB\xBF".self::HEADER."module;M1;;Name;;;;;;;;\n", $this->model())['errors']);
        self::assertSame([], $service->preview("\xEF\xBB\xBF".self::HEADER, $this->model())['errors']);
        self::assertSame('header', $service->preview("\xFF".self::HEADER, $this->model())['errors'][0]['column']);
        self::assertSame('header', $service->preview("\0".self::HEADER, $this->model())['errors'][0]['column']);

        $rows = str_repeat("module;M1;;Name;;;;;;;;\n", 5001);
        self::assertStringContainsString('5 000', $service->preview(self::HEADER.$rows, $this->model())['errors'][0]['message']);
    }

    public function testFormulaRoundTripUnescapesOnlyFormulaPrefix(): void
    {
        $model = $this->model();
        $module = (new MachineModule())->setMachineModel($model)->setCode('M1')->setName('Module')->setPlanche('P1');
        $module->addPart((new Part())->setModule($module)->setDescription('=FORMULA')->setPartNumber(null));
        $model->addModule($module);

        $preview = $this->service()->preview($this->service()->export($model), $model);

        self::assertSame('=FORMULA', $preview['rows'][1]['name']);
    }

    public function testConfirmedWarningOnlyAppliesToDemoModels(): void
    {
        $service = $this->service();
        $csv = self::HEADER."module;M1;;Name;;;;;;;;confirmed\n";

        self::assertNotNull($service->preview($csv, $this->model())['warning']);
        self::assertNull($service->preview($csv, $this->model()->setDataStatus('confirmed'))['warning']);
    }
}
