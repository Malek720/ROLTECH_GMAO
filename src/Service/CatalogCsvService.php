<?php

namespace App\Service;

use App\Entity\MachineModel;
use App\Entity\MachineModule;
use App\Entity\MaintenanceTask;
use App\Entity\Part;
use Doctrine\ORM\EntityManagerInterface;

final class CatalogCsvService
{
    public const HEADER = ['record_type', 'module_code', 'parent_module_code', 'name', 'part_number', 'quantity', 'description', 'action', 'frequency_days', 'frequency_hours', 'procedure', 'data_status'];

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function export(MachineModel $model): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, self::HEADER, ';');
        foreach ($model->getModules() as $module) {
            $this->write($handle, ['module', $module->getCode(), $module->getParent()?->getCode(), $module->getName(), '', '', '', '', '', '', '', $module->getDataStatus()]);
            foreach ($module->getParts() as $part) {
                $this->write($handle, ['part', $module->getCode(), '', $part->getDescription(), $part->getPartNumber(), $part->getQuantity(), $part->getDescription(), '', '', '', '', $part->getDataStatus()]);
            }
        }
        foreach ($model->getMaintenanceTasks() as $task) {
            $this->write($handle, ['task', $task->getModule()?->getCode(), '', '', '', '', '', $task->getAction(), $task->getFrequencyDays(), $task->getFrequencyHours(), str_replace(["\r\n", "\n"], ' | ', $task->getProcedureChecklist() ?? ''), $task->getDataStatus()]);
        }
        rewind($handle);
        return stream_get_contents($handle) ?: '';
    }

    /**
     * @return array{rows:list<array<string,mixed>>,errors:list<array{line:int,column:string,message:string}>,counts:array{create:int,update:int,skip:int},warning:?string}
     */
    public function preview(string $contents, MachineModel $model): array
    {
        $rows = [];
        $errors = [];
        try {
            $rows = $this->parse($contents);
        } catch (\InvalidArgumentException $exception) {
            $line = 1;
            $column = 'header';
            if (preg_match('/^La ligne (\d+)/u', $exception->getMessage(), $matches) === 1) {
                $line = (int) $matches[1];
                $column = 'row';
            }
            $errors[] = ['line' => $line, 'column' => $column, 'message' => $exception->getMessage()];
        }
        $knownModules = [];
        foreach ($model->getModules() as $module) {
            $knownModules[$module->getCode()] = true;
        }
        $fileModules = [];
        $parentOf = [];
        foreach ($rows as $row) {
            $line = $row['_line'];
            $type = $row['record_type'];
            if (!in_array($type, ['module', 'part', 'task'], true)) {
                $errors[] = ['line' => $line, 'column' => 'record_type', 'message' => 'Type de ligne inconnu.'];
                continue;
            }
            if ($type === 'module') {
                if ($row['module_code'] === '') $errors[] = ['line' => $line, 'column' => 'module_code', 'message' => 'Le code module est obligatoire.'];
                if ($row['name'] === '') $errors[] = ['line' => $line, 'column' => 'name', 'message' => 'Le nom du module est obligatoire.'];
                if (isset($fileModules[$row['module_code']])) $errors[] = ['line' => $line, 'column' => 'module_code', 'message' => 'Code module dupliqué dans le fichier.'];
                $fileModules[$row['module_code']] = true;
                if ($row['parent_module_code'] !== '') $parentOf[$row['module_code']] = $row['parent_module_code'];
            }
            if ($row['parent_module_code'] !== '' && !isset($knownModules[$row['parent_module_code']]) && !isset($fileModules[$row['parent_module_code']])) {
                $errors[] = ['line' => $line, 'column' => 'parent_module_code', 'message' => 'Le module parent doit exister dans le fichier ou le modèle.'];
            }
            if (in_array($type, ['part', 'task'], true) && $row['module_code'] !== '' && !isset($knownModules[$row['module_code']]) && !isset($fileModules[$row['module_code']])) {
                $errors[] = ['line' => $line, 'column' => 'module_code', 'message' => 'Le module indiqué est inconnu.'];
            }
            $status = $row['data_status'] === '' ? 'demo' : $row['data_status'];
            if (!in_array($status, ['confirmed', 'demo'], true)) {
                $errors[] = ['line' => $line, 'column' => 'data_status', 'message' => 'Le statut doit être confirmed ou demo.'];
            }
            if ($type === 'part') {
                if ($row['module_code'] === '') $errors[] = ['line' => $line, 'column' => 'module_code', 'message' => 'Le module est obligatoire pour une pièce.'];
                if ($row['name'] === '') $errors[] = ['line' => $line, 'column' => 'name', 'message' => 'Le nom est obligatoire pour une pièce.'];
                if ($row['quantity'] !== '' && (!ctype_digit($row['quantity']) || (int) $row['quantity'] <= 0)) $errors[] = ['line' => $line, 'column' => 'quantity', 'message' => 'La quantité doit être un entier positif.'];
            }
            if ($type === 'task') {
                if ($row['action'] === '') $errors[] = ['line' => $line, 'column' => 'action', 'message' => 'L’action est obligatoire.'];
                if ($row['frequency_days'] === '' && $row['frequency_hours'] === '') $errors[] = ['line' => $line, 'column' => 'frequency_days', 'message' => 'Une fréquence en jours ou en heures est obligatoire.'];
                foreach (['frequency_days', 'frequency_hours'] as $column) {
                    if ($row[$column] !== '' && (!ctype_digit($row[$column]) || (int) $row[$column] <= 0)) $errors[] = ['line' => $line, 'column' => $column, 'message' => 'La fréquence doit être un entier positif.'];
                }
            }
        }
        foreach (array_keys($parentOf) as $code) {
            $seen = [];
            $current = $code;
            while (isset($parentOf[$current])) {
                if (isset($seen[$current])) {
                    $errors[] = ['line' => $this->lineForModule($rows, $code), 'column' => 'parent_module_code', 'message' => 'La hiérarchie des modules contient une boucle.'];
                    break;
                }
                $seen[$current] = true;
                $current = $parentOf[$current];
            }
        }
        $errorLines = [];
        foreach ($errors as $error) {
            $errorLines[$error['line']] = true;
        }
        $counts = ['create' => 0, 'update' => 0, 'skip' => 0];
        foreach ($rows as $row) {
            if (isset($errorLines[$row['_line']]) || !in_array($row['record_type'], ['module', 'part', 'task'], true)) {
                $counts['skip']++;
            } elseif ($this->findExisting($row, $model) === null) {
                $counts['create']++;
            } else {
                $counts['update']++;
            }
        }
        $hasConfirmed = array_reduce($rows, static fn (bool $carry, array $row): bool => $carry || $row['data_status'] === 'confirmed', false);
        return ['rows' => $rows, 'errors' => $errors, 'counts' => $counts, 'warning' => $model->getDataStatus() === 'demo' && $hasConfirmed ? 'Le modèle est en démonstration : des lignes confirmées vont être importées.' : null];
    }

    /** @return array{created:int,updated:int} */
    public function import(string $contents, MachineModel $model): array
    {
        $preview = $this->preview($contents, $model);
        if ($preview['errors'] !== []) throw new \InvalidArgumentException('Le fichier contient des erreurs bloquantes.');
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $moduleMap = [];
            foreach ($model->getModules() as $module) $moduleMap[$module->getCode()] = $module;
            $created = $updated = 0;
            foreach ($preview['rows'] as $row) {
                if ($row['record_type'] !== 'module') continue;
                $entity = $moduleMap[$row['module_code']] ?? new MachineModule();
                if ($entity->getId() === null) { $created++; $this->entityManager->persist($entity); } else $updated++;
                $entity->setMachineModel($model)->setCode($row['module_code'])->setName($row['name'])->setDataStatus($row['data_status'] ?: 'demo');
                if ($entity->getPlanche() === null) $entity->setPlanche('');
                $model->addModule($entity);
                $moduleMap[$row['module_code']] = $entity;
            }
            foreach ($preview['rows'] as $row) {
                if ($row['record_type'] === 'module' && $row['parent_module_code'] !== '') $moduleMap[$row['module_code']]->setParent($moduleMap[$row['parent_module_code']] ?? null);
                if ($row['record_type'] === 'part') {
                    $module = $moduleMap[$row['module_code']];
                    $entity = $this->findExisting($row, $model) ?? new Part();
                    if ($entity->getId() === null) { $created++; $this->entityManager->persist($entity); } else $updated++;
                    $entity->setModule($module)->setPartNumber($row['part_number'] ?: null)->setDescription($row['name'])->setQuantity($row['quantity'] === '' ? null : (int) $row['quantity'])->setDataStatus($row['data_status'] ?: 'demo');
                    $module->addPart($entity);
                }
                if ($row['record_type'] === 'task') {
                    $entity = $this->findExisting($row, $model) ?? new MaintenanceTask();
                    if ($entity->getId() === null) { $created++; $this->entityManager->persist($entity); } else $updated++;
                    $entity->setMachineModel($model)->setModule($row['module_code'] === '' ? null : $moduleMap[$row['module_code']])->setAction($row['action'])->setFrequencyDays($row['frequency_days'] === '' ? null : (int) $row['frequency_days'])->setFrequencyHours($row['frequency_hours'] === '' ? null : (int) $row['frequency_hours'])->setProcedureChecklist($row['procedure'] === '' ? null : str_replace(' | ', "\n", $row['procedure']))->setDataStatus($row['data_status'] ?: 'demo');
                    $model->addMaintenanceTask($entity);
                }
            }
            $this->entityManager->flush();
            $connection->commit();
            return ['created' => $created, 'updated' => $updated];
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    /** @return list<array<string,mixed>> */
    private function parse(string $contents): array
    {
        if (str_contains($contents, "\0")) throw new \InvalidArgumentException('Le fichier contient des octets binaires interdits.');
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        if (!mb_check_encoding($contents, 'UTF-8')) throw new \InvalidArgumentException('Le fichier doit être encodé en UTF-8.');
        $lines = preg_split('/\r\n|\r|\n/', trim($contents)) ?: [];
        if ($lines === []) throw new \InvalidArgumentException('Le fichier est vide.');
        $delimiter = str_contains($lines[0], ';') ? ';' : ',';
        $header = str_getcsv(array_shift($lines), $delimiter);
        if ($header !== self::HEADER) throw new \InvalidArgumentException('L’en-tête CSV doit contenir exactement les colonnes attendues, dans le bon ordre.');
        if (count($lines) > 5000) throw new \InvalidArgumentException('Le fichier ne peut pas contenir plus de 5 000 lignes.');
        $rows = [];
        foreach ($lines as $offset => $line) {
            if (trim($line) === '') continue;
            $values = str_getcsv($line, $delimiter);
            if (count($values) !== count(self::HEADER)) throw new \InvalidArgumentException(sprintf('La ligne %d ne contient pas le bon nombre de colonnes.', $offset + 2));
            $row = ['_line' => $offset + 2];
            foreach (self::HEADER as $index => $column) {
                $value = trim((string) $values[$index]);
                $row[$column] = strlen($value) > 1 && $value[0] === "'" && in_array($value[1], ['=', '+', '-', '@'], true) ? substr($value, 1) : $value;
            }
            $rows[] = $row;
        }
        return $rows;
    }

    private function write($handle, array $values): void
    {
        fputcsv($handle, array_map(static function ($value): string {
            $value = (string) ($value ?? '');
            return $value !== '' && in_array($value[0], ['=', '+', '-', '@'], true) ? "'".$value : $value;
        }, $values), ';');
    }

    private function findExisting(array $row, MachineModel $model): MachineModule|Part|MaintenanceTask|null
    {
        if ($row['record_type'] === 'module') return $this->entityManager->getRepository(MachineModule::class)->findOneBy(['machineModel' => $model, 'code' => $row['module_code']]);
        $module = $row['module_code'] === '' ? null : $this->entityManager->getRepository(MachineModule::class)->findOneBy(['machineModel' => $model, 'code' => $row['module_code']]);
        if ($row['record_type'] === 'part') return $module === null ? null : ($row['part_number'] !== '' ? $this->entityManager->getRepository(Part::class)->findOneBy(['module' => $module, 'partNumber' => $row['part_number']]) : $this->entityManager->getRepository(Part::class)->findOneBy(['module' => $module, 'description' => $row['name']]));
        return $this->entityManager->getRepository(MaintenanceTask::class)->findOneBy(['machineModel' => $model, 'module' => $module, 'action' => $row['action']]);
    }

    private function lineForModule(array $rows, string $code): int
    {
        foreach ($rows as $row) if ($row['module_code'] === $code) return (int) $row['_line'];
        return 0;
    }
}
