<?php

namespace App\DataFixtures;

use App\Entity\Fault;
use App\Entity\Intervention;
use App\Entity\Machine;
use App\Entity\MachineModel;
use App\Entity\MachineModule;
use App\Entity\MaintenanceSchedule;
use App\Entity\MaintenanceTask;
use App\Entity\Part;
use App\Entity\Site;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $sites = $this->createSites($manager);
        $admin = $this->user($manager, 'admin@roltechsystems.com', 'Ahmed Ben Salah', ['ROLE_ADMIN']);
        $tech1 = $this->user($manager, 'karim.technicien@roltechsystems.com', 'Karim Trabelsi', ['ROLE_TECHNICIEN'], $sites['Sousse']);
        $tech2 = $this->user($manager, 'sami.technicien@roltechsystems.com', 'Sami Gharbi', ['ROLE_TECHNICIEN'], $sites['Béja']);
        $resp = $this->user($manager, 'malek.responsable@roltechsystems.com', 'Malek Ben Sassi', ['ROLE_RESPONSABLE'], $sites['Sousse']);

        $models = $this->createModels($manager);
        $manager->flush();
        $modules = $this->createModules($manager, $models);
        $manager->flush();
        $parts = $this->createParts($manager, $modules);
        $manager->flush();
        $tasks = $this->createTasks($manager, $models, $modules);
        $manager->flush();

        $machines = $this->createMachines($manager, $sites, $models);
        $manager->flush();

        $ocModules = $modules['OC-CA1100'];
        $fault1 = (new Fault())->setMachine($machines['SERIAL #BJ-1100-002'])->setReportedBy($tech2)
            ->setReportedAt(new \DateTime('-5 days'))
            ->setDescription('Le convoyeur ne démarre pas et le moteur s’arrête fréquemment.')
            ->setSymptom('Conveyor does not start or motor stops frequently')
            ->setSeverity('critique')->setStatus('ouverte')->setModule($ocModules['M5']);
        $fault2 = (new Fault())->setMachine($machines['SERIAL #SO-1100-002'])->setReportedBy($tech1)
            ->setReportedAt(new \DateTime('-2 days'))
            ->setDescription('Bruit de craquement observé au niveau des disques de renvoi.')
            ->setSymptom('Cracking noise at the return discs (disques de renvoi)')
            ->setSeverity('moyenne')->setStatus('en cours')->setModule($ocModules['M1']);
        $fault3 = (new Fault())->setMachine($machines['SERIAL #SO-1100-001'])->setReportedBy($resp)
            ->setReportedAt(new \DateTime('-30 days'))
            ->setDescription('Le moteur ou le réducteur chauffe anormalement.')
            ->setSymptom('Motor or reducer overheating')
            ->setSeverity('faible')->setStatus('resolue')->setModule($ocModules['M5']);
        $manager->persist($fault1);
        $manager->persist($fault2);
        $manager->persist($fault3);

        $intervention1 = (new Intervention())->setFault($fault1)->setTechnician($tech2)
            ->setType('curative')->setStartDate(new \DateTime('-4 days'))->setStatus('en cours')
            ->setComment('Diagnostic en cours.');
        $intervention2 = (new Intervention())->setFault($fault2)->setTechnician($tech1)
            ->setType('curative')->setStartDate(new \DateTime('-2 days'))->setStatus('planifiee');
        $intervention3 = (new Intervention())->setFault($fault3)->setTechnician($tech1)
            ->setType('curative')->setStartDate(new \DateTime('-29 days'))->setEndDate(new \DateTime('-29 days'))
            ->setStatus('terminee')->setComment('Contrôle terminé.');
        $manager->persist($intervention1);
        $manager->persist($intervention2);
        $manager->persist($intervention3);

        $firstSchedule = $machines['SERIAL #SO-1100-001']->getMaintenanceSchedules()->first();
        if ($firstSchedule instanceof MaintenanceSchedule) {
            $preventive = (new Intervention())->setSchedule($firstSchedule)->setTechnician($tech1)
                ->setType('preventive')->setStartDate(new \DateTime('-1 day'))->setEndDate(new \DateTime('-1 day'))
                ->setStatus('terminee')->setComment('Contrôle préventif terminé.');
            $manager->persist($preventive);
        }
        $manager->flush();
    }

    /** @return array<string, Site> */
    private function createSites(ObjectManager $manager): array
    {
        $sites = [];
        foreach (['Sousse', 'Béja', 'Jendouba', 'Tunis'] as $name) {
            $site = (new Site())->setName($name)->setCity($name);
            $manager->persist($site);
            $sites[$name] = $site;
        }

        return $sites;
    }

    /** @return array<string, MachineModel> */
    private function createModels(ObjectManager $manager): array
    {
        $data = [
            ['OC-CA1100', 'Convoyeur aérien OC-CA1100', 'Convoyeur aérien (crochets)', 15, 'confirmed'],
            ['HLS-CA1100', 'Système logistique de bobines de câble', 'Logistique bobines', 60, 'demo'],
            ['HLS-PXL-CA1100', 'Système logistique de gros faisceaux de câbles', 'Logistique faisceaux', 80, 'demo'],
            ['HLS-PXL-CA1100-01', 'Système logistique de caisses', 'Logistique caisses', 25, 'demo'],
            ['PPXXL-CA1100', 'Convoyeur de ligne de peinture', 'Ligne de peinture', 20, 'demo'],
            ['HP-2000X', 'Presse hydraulique HP-2000X', 'Presse', null, 'demo'],
            ['AirMax-300', 'Compresseur d’air AirMax-300', 'Utilitaire', null, 'demo'],
        ];
        $models = [];
        foreach ($data as [$code, $name, $family, $maxLoadKg, $dataStatus]) {
            $model = (new MachineModel())->setCode($code)->setName($name)->setFamily($family)
                ->setMaxLoadKg($maxLoadKg)->setDataStatus($dataStatus);
            $manager->persist($model);
            $models[$code] = $model;
        }

        return $models;
    }

    /** @return array<string, array<string, MachineModule>> */
    private function createModules(ObjectManager $manager, array $models): array
    {
        $modules = [];
        $conveyor = [
            'M1' => ['Groupe de renvoi 180°', 'M1', null],
            'M2' => ['Groupe de renvoi 90°', 'M2', null],
            'M3' => ['Groupe motorisation tendeur', 'M3', null],
            'M4' => ['Module poulie', 'M4', 'M3'],
            'M5' => ['Tronçon', 'M5', null],
            'M6' => ['Trolley', 'M6', null],
            'M7' => ['Porteur de charge', 'M7', null],
        ];
        foreach (['HLS-CA1100', 'HLS-PXL-CA1100', 'HLS-PXL-CA1100-01', 'PPXXL-CA1100'] as $code) {
            $modules[$code] = $this->moduleSet($manager, $models[$code], $conveyor);
        }
        $modules['PPXXL-CA1100']['M8'] = $this->module($manager, $models['PPXXL-CA1100'], 'M8', 'Zone de séchage', 'M8');
        $modules['OC-CA1100'] = $this->moduleSet($manager, $models['OC-CA1100'], [
            'M1' => ['Groupe de renvoi 180° (GR 180 1100)', 'OC1100-01', null],
            'M2' => ['Groupe de renvoi 90° (GR 90 1100)', 'OC1100-02', null],
            'M3' => ['Groupe Motorisation Tendeur (GM T 200 1100)', 'OC1100-03', null],
            'M4' => ['Module poulie (GM-800 01 1100)', 'OC1100-04', 'M3'],
            'M5' => ['Module support moteur (GM-700 02 1100)', 'OC1100-05', 'M3'],
            'M6' => ['Tronçon (section)', 'OC1100-06', null],
            'M7' => ['Trolley (T-200-1100)', 'OC1100-07', 'M6'],
        ], 'confirmed');
        $modules['HP-2000X'] = $this->moduleSet($manager, $models['HP-2000X'], [
            'M1' => ['Groupe hydraulique', 'M1', null],
            'M2' => ['Vérin principal', 'M2', null],
            'M3' => ['Armoire électrique', 'M3', null],
            'M4' => ['Châssis et outillage', 'M4', null],
        ]);
        $modules['AirMax-300'] = $this->moduleSet($manager, $models['AirMax-300'], [
            'M1' => ['Bloc de compression', 'M1', null],
            'M2' => ['Filtration', 'M2', null],
            'M3' => ['Réservoir et purge', 'M3', null],
            'M4' => ['Moteur et commande', 'M4', null],
        ]);

        return $modules;
    }

    /** @param array<string, array{0: string, 1: string, 2: ?string}> $data
     * @return array<string, MachineModule> */
    private function moduleSet(ObjectManager $manager, MachineModel $model, array $data, string $status = 'demo'): array
    {
        $result = [];
        foreach ($data as $code => [$name, $planche, $parentCode]) {
            $result[$code] = $this->module($manager, $model, $code, $name, $planche, $status);
        }
        foreach ($data as $code => [, , $parentCode]) {
            if ($parentCode !== null) {
                $result[$code]->setParent($result[$parentCode]);
            }
        }

        return $result;
    }

    private function module(ObjectManager $manager, MachineModel $model, string $code, string $name, string $planche, string $status = 'demo'): MachineModule
    {
        $module = (new MachineModule())->setCode($code)->setName($name)->setPlanche($planche)
            ->setMachineModel($model)->setDataStatus($status);
        $manager->persist($module);

        return $module;
    }

    /** @return array<string, array<string, Part>> */
    private function createParts(ObjectManager $manager, array $modules): array
    {
        $parts = [];
        $demoParts = [
            'M1' => [['carter', 1], ['moyeu', 1], ['disque supérieur', 1], ['disque inférieur', 1], ['roulement', 2], ['arbre', 1]],
            'M2' => [['carter', 1], ['moyeu', 1], ['disque supérieur', 1], ['disque inférieur', 1], ['roulement', 2], ['arbre', 1]],
            'M3' => [['moteur-réducteur', 1], ['courroie', 1], ['rail de tension', 2], ['coulisse de rail', 1]],
            'M4' => [['poulie', 1], ['roulement', 2], ['plaque', 2], ['arbre', 1]],
            'M5' => [['élément de traction', 1], ['rail', 1], ['support', 1]],
            'M6' => [['galet', 2], ['flasque', 2], ['axe', 1]],
        ];
        foreach (['HLS-CA1100', 'HLS-PXL-CA1100', 'HLS-PXL-CA1100-01', 'PPXXL-CA1100'] as $modelCode) {
            foreach ($demoParts as $moduleCode => $items) {
                $parts[$modelCode][$moduleCode] = $this->partsForModule($manager, $modules[$modelCode][$moduleCode], $modelCode, $moduleCode, $items);
            }
            $carrier = match ($modelCode) {
                'HLS-CA1100' => [['support de bobine', 1], ['axe de bobine', 1], ['verrou de sécurité', 1]],
                'HLS-PXL-CA1100' => [['porte-faisceau', 1], ['traverse renforcée', 1], ['verrou de sécurité', 1]],
                'HLS-PXL-CA1100-01' => [['porte-caisse', 1], ['griffe de maintien', 2]],
                default => [['palonnier de peinture', 1], ['crochet résistant à la chaleur', 1]],
            };
            $parts[$modelCode]['M7'] = $this->partsForModule($manager, $modules[$modelCode]['M7'], $modelCode, 'M7', $carrier, count($parts[$modelCode], COUNT_RECURSIVE));
            if ($modelCode === 'PPXXL-CA1100') {
                $parts[$modelCode]['M8'] = $this->partsForModule($manager, $modules[$modelCode]['M8'], $modelCode, 'M8', [['ventilateur', 1], ['sonde de température', 1], ['thermostat', 1]]);
            }
        }
        $parts['OC-CA1100'] = $this->createOcParts($manager, $modules['OC-CA1100']);
        $parts['HP-2000X'] = $this->partsForModuleSets($manager, $modules['HP-2000X'], [
            'M1' => [['pompe', 1], ['réservoir', 1], ['filtre à huile', 1], ['joint de piston', 1]],
            'M2' => [['vérin', 1], ['kit de joints', 1], ['tige', 1]],
            'M3' => [['contacteur', 2], ['relais thermique', 1], ['capteur de pression', 1]],
            'M4' => [['colonne', 4], ['plateau', 1], ['garde de sécurité', 1]],
        ]);
        $parts['AirMax-300'] = $this->partsForModuleSets($manager, $modules['AirMax-300'], [
            'M1' => [['bloc à vis', 1], ['courroie', 1], ['roulement', 2]],
            'M2' => [['filtre à air', 1], ['filtre à huile', 1], ['séparateur air-huile', 1]],
            'M3' => [['réservoir', 1], ['purge automatique', 1], ['soupape de sécurité', 1]],
            'M4' => [['moteur', 1], ['pressostat', 1], ['contacteur', 1]],
        ]);

        return $parts;
    }

    /** @param list<array{0: string, 1: int}> $items @return array<string, Part> */
    private function partsForModule(ObjectManager $manager, MachineModule $module, string $modelCode, string $moduleCode, array $items, int $offset = 0): array
    {
        $result = [];
        foreach ($items as $index => [$description, $quantity]) {
            $number = sprintf('DEMO-%s-%s-%02d', $modelCode, $moduleCode, $index + 1 + $offset);
            $part = (new Part())->setPartNumber($number)->setDescription($description)->setQuantity($quantity)
                ->setModule($module)->setDataStatus('demo');
            $manager->persist($part);
            $result[$description] = $part;
        }

        return $result;
    }

    /** @param array<string, list<array{0: string, 1: int}>> $sets */
    private function partsForModuleSets(ObjectManager $manager, array $modules, array $sets): array
    {
        $result = [];
        foreach ($sets as $moduleCode => $items) {
            $result[$moduleCode] = $this->partsForModule($manager, $modules[$moduleCode], $modules[$moduleCode]->getMachineModel()?->getCode() ?? 'DEMO', $moduleCode, $items);
        }

        return $result;
    }

    /** @return array<string, Part> */
    private function createOcParts(ObjectManager $manager, array $modules): array
    {
        $data = [
            'M1' => [['GR-180-100 1100', 'housing', 1], ['TF-108-100 1100', 'part', 1], ['PL-600-100 1100', 'part', 1], ['TF-203-100 1100', 'part', 1], ['PL-600-317 1100', 'part', 1], ['DIN 628 SKF - SKF 7206 BE', 'bearing', 2], ['TF-103-100 1100', 'part', 1], ['T2001', 'part', 1]],
            'M2' => [['GR-90-100 1100', 'housing', 1], ['TF-108-100 1100', 'part', 1], ['PL-450-100 1100', 'part', 1], ['TF-203-100 1100', 'part', 1], ['PL-450-308 1100', 'part', 1], ['DIN 628 SKF - SKF 7206 BE', 'bearing', 2], ['TF-103-100 1100', 'part', 1], ['T1001', 'part', 1]],
            'M3' => [['GM-800 01 1100', 'module poulie', 1], ['RT-300 1100', 'rail de tension', 2], ['V-Belt 1800', 'belt', 1], ['TK-550 120 01', 'coulisse de rail', 1], ['GM-700 02 1100', 'module support moteur', 1]],
            'M4' => [['GM-800-100 1100', 'part', 1], ['TF-108-100 1100', 'part', 1], ['PL-600-100 1100', 'part', 1], ['TF-203-100 1100', 'part', 1], ['DIN 628 SKF - SKF 7206 BE', 'bearing', 2], ['T3001', 'part', 1], ['PL-600-317 1100', 'part', 1], ['PR 450 B17 11 40 001', 'pulley', 1], ['TF-213-100 1100', 'part', 1]],
            'M5' => [['GM-700-100-CM63', 'part', 1], ['GM-700-200-CM63', 'part', 1], ['TF-204-CM63', 'part', 1], ['GM-700-400-UCF206', 'part', 1], ['UCF206', 'bearing unit', 1], ['GM-700-300-UCF206', 'part', 1], ['MR-0.75-CM63-100', 'motor-reducer 0.75', 1], ['TF-501-1100', 'shaft', 1], ['PR 200 B17 11 40', 'pulley', 1]],
            'M6' => [['T-200-1100', 'trolley = M7', null], ['Corde D14', 'rope', null], ['Croche 300', 'hook', null], ['R-1952 1100', 'rail', null], ['S-6001', 'support', null]],
            'M7' => [['T-200-6200', 'wheel', 2], ['T-200-337', 'plate', 2], ['T-200-3210', 'part', 1]],
        ];
        $result = [];
        foreach ($data as $moduleCode => $items) {
            foreach ($items as [$number, $description, $quantity]) {
                $part = (new Part())->setPartNumber($number)->setDescription($description)->setQuantity($quantity)
                    ->setModule($modules[$moduleCode])->setDataStatus('confirmed');
                $manager->persist($part);
                $result[$number] = $part;
            }
        }

        return $result;
    }

    /** @return array<string, list<MaintenanceTask>> */
    private function createTasks(ObjectManager $manager, array $models, array $modules): array
    {
        $tasks = [];
        $ocData = [
            ['vérifier le bruit', 'M5', 7, 168], ['vérifier la température', 'M5', 7, 168],
            ['vérifier le montage des boulons', 'M5', 30, 720], ["vérifier l'état", 'M6', 7, 168],
            ['vérifier la tension', 'M6', 30, 720], ['vérifier le bruit', 'M1', 7, 168],
            ['vérifier le montage des boulons', 'M1', 30, 720], ['graissage des roulements', 'M1', 90, 2160],
            ['contrôle général', 'M6', 30, 720], ['vérifier le montage des boulons', 'M6', 30, 720],
            ['vérifier le bruit', 'M7', 7, 168], ['vérifier le montage', 'M7', 30, 720],
            ['vérifier que les galets tournent librement', 'M7', 30, 720],
        ];
        foreach ($ocData as [$action, $moduleCode, $days, $hours]) {
            $task = $this->task($manager, $models['OC-CA1100'], $modules['OC-CA1100'][$moduleCode], $action, $days, $hours, 'confirmed');
            $tasks['OC-CA1100'][] = $task;
        }
        $demoConveyor = [
            ['vérifier le bruit', 'M3', 7, 168], ['vérifier la température', 'M3', 7, 168],
            ['vérifier le montage des boulons', 'M3', 30, 720], ["vérifier l'état", 'M5', 7, 168],
            ['vérifier la tension', 'M5', 30, 720], ['vérifier le bruit', 'M1', 7, 168],
            ['vérifier les boulons', 'M1', 30, 720], ['graissage', 'M1', 90, 2160],
            ['contrôle général', 'M5', 30, 720], ['vérifier les boulons', 'M5', 30, 720],
            ['vérifier le bruit', 'M6', 7, 168], ['vérifier le montage', 'M6', 30, 720],
            ['vérifier que les galets tournent librement', 'M6', 30, 720],
            ['vérifier l’état et la fixation du porteur de charge', 'M7', 7, 168],
        ];
        foreach (['HLS-CA1100', 'HLS-PXL-CA1100', 'HLS-PXL-CA1100-01', 'PPXXL-CA1100'] as $modelCode) {
            foreach ($demoConveyor as [$action, $moduleCode, $days, $hours]) {
                $tasks[$modelCode][] = $this->task($manager, $models[$modelCode], $modules[$modelCode][$moduleCode], $action, $days, $hours);
            }
            if ($modelCode === 'PPXXL-CA1100') {
                $tasks[$modelCode][] = $this->task($manager, $models[$modelCode], $modules[$modelCode]['M8'], 'vérifier le ventilateur', 30, 720);
                $tasks[$modelCode][] = $this->task($manager, $models[$modelCode], $modules[$modelCode]['M8'], 'étalonner la sonde de température', 90, 2160);
            }
        }
        $press = [
            ['contrôle de la pression', 'M1', null, 168], ['vérifier la garde de sécurité', 'M4', null, 168],
            ['remplacer le filtre à huile', 'M1', null, 500], ['inspecter les joints du vérin', 'M2', null, 1000],
            ["remplacer l'huile hydraulique", 'M1', null, 2000], ['remplacer le kit de joints du vérin', 'M2', null, 4000],
        ];
        foreach ($press as [$action, $moduleCode, $days, $hours]) {
            $tasks['HP-2000X'][] = $this->task($manager, $models['HP-2000X'], $modules['HP-2000X'][$moduleCode], $action, $days, $hours);
        }
        $air = [
            ['contrôler la purge de condensat', 'M3', 7, 168], ['inspecter la courroie', 'M1', null, 1000],
            ["remplacer le filtre à air", 'M2', null, 1000], ["remplacer le filtre à huile", 'M2', null, 2000],
            ['tester la soupape de sécurité', 'M3', 90, 2160], ['remplacer le séparateur air-huile', 'M2', null, 4000],
        ];
        foreach ($air as [$action, $moduleCode, $days, $hours]) {
            $tasks['AirMax-300'][] = $this->task($manager, $models['AirMax-300'], $modules['AirMax-300'][$moduleCode], $action, $days, $hours);
        }

        return $tasks;
    }

    private function task(ObjectManager $manager, MachineModel $model, MachineModule $module, string $action, ?int $days, ?int $hours, string $status = 'demo'): MaintenanceTask
    {
        $task = (new MaintenanceTask())->setMachineModel($model)->setModule($module)->setAction($action)
            ->setFrequencyDays($days)->setFrequencyHours($hours)->setDataStatus($status);
        $manager->persist($task);

        return $task;
    }

    /** @return array<string, Machine> */
    private function createMachines(ObjectManager $manager, array $sites, array $models): array
    {
        $data = [
            ['Sousse', 'OC-CA1100 Sousse 01', 'OC-CA1100', 'SERIAL #SO-1100-001', 'Atelier A', 'en service', '2021-03-15'],
            ['Sousse', 'OC-CA1100 Sousse 02', 'OC-CA1100', 'SERIAL #SO-1100-002', 'Atelier B', 'en maintenance', '2022-06-20'],
            ['Béja', 'OC-CA1100 Béja 01', 'OC-CA1100', 'SERIAL #BJ-1100-001', 'Ligne 1', 'en service', '2020-01-10'],
            ['Béja', 'OC-CA1100 Béja 02', 'OC-CA1100', 'SERIAL #BJ-1100-002', 'Ligne 2', 'en panne', '2019-11-02'],
            ['Jendouba', 'OC-CA1100 Jendouba 01', 'OC-CA1100', 'SERIAL #JE-1100-001', 'Atelier A', 'en service', '2021-09-12'],
            ['Jendouba', 'OC-CA1100 Jendouba 02', 'OC-CA1100', 'SERIAL #JE-1100-002', 'Atelier B', 'en service', '2022-02-08'],
            ['Tunis', 'OC-CA1100 Tunis 01', 'OC-CA1100', 'SERIAL #TU-1100-001', 'Zone montage', 'en service', '2020-07-24'],
            ['Tunis', 'OC-CA1100 Tunis 02', 'OC-CA1100', 'SERIAL #TU-1100-002', 'Zone expédition', 'en maintenance', '2021-12-01'],
            ['Sousse', 'HLS-CA1100 Sousse 01', 'HLS-CA1100', 'HLS-2024-0001', 'Atelier C', 'en service', '2024-01-10'],
            ['Béja', 'HLS-CA1100 Béja 01', 'HLS-CA1100', 'HLS-2024-0002', 'Ligne 3', 'en service', '2024-02-12'],
            ['Jendouba', 'HLS-CA1100 Jendouba 01', 'HLS-CA1100', 'HLS-2024-0003', 'Atelier C', 'en service', '2024-03-15'],
            ['Sousse', 'HLS-PXL-CA1100 Sousse 01', 'HLS-PXL-CA1100', 'HLS-PXL-2024-0001', 'Ligne 4', 'en service', '2024-01-20'],
            ['Sousse', 'HLS-PXL-CA1100 Sousse 02', 'HLS-PXL-CA1100', 'HLS-PXL-2024-0002', 'Ligne 5', 'en maintenance', '2024-02-20'],
            ['Tunis', 'HLS-PXL-CA1100 Tunis 01', 'HLS-PXL-CA1100', 'HLS-PXL-2024-0003', 'Zone nord', 'en service', '2024-03-20'],
            ['Béja', 'HLS-PXL-CA1100-01 Béja 01', 'HLS-PXL-CA1100-01', 'HLS-BOX-2024-0001', 'Ligne 6', 'en service', '2024-01-25'],
            ['Jendouba', 'HLS-PXL-CA1100-01 Jendouba 01', 'HLS-PXL-CA1100-01', 'HLS-BOX-2024-0002', 'Ligne 7', 'en service', '2024-02-25'],
            ['Tunis', 'HLS-PXL-CA1100-01 Tunis 01', 'HLS-PXL-CA1100-01', 'HLS-BOX-2024-0003', 'Zone sud', 'en service', '2024-03-25'],
            ['Sousse', 'PPXXL-CA1100 Sousse 01', 'PPXXL-CA1100', 'PPXXL-2024-0001', 'Peinture', 'en panne', '2024-01-30'],
            ['Jendouba', 'PPXXL-CA1100 Jendouba 01', 'PPXXL-CA1100', 'PPXXL-2024-0002', 'Peinture', 'en service', '2024-02-28'],
            ['Tunis', 'HP-2000X Tunis 01', 'HP-2000X', 'HP-2000X-2024-0001', 'Presse 1', 'en service', '2024-01-05'],
            ['Béja', 'HP-2000X Béja 01', 'HP-2000X', 'HP-2000X-2024-0002', 'Presse 2', 'en maintenance', '2024-02-05'],
            ['Sousse', 'AirMax-300 Sousse 01', 'AirMax-300', 'AIRMAX-2024-0001', 'Compresseur 1', 'en service', '2024-01-08'],
            ['Jendouba', 'AirMax-300 Jendouba 01', 'AirMax-300', 'AIRMAX-2024-0002', 'Compresseur 2', 'en service', '2024-02-08'],
        ];
        $machines = [];
        foreach ($data as [$site, $name, $modelCode, $serial, $location, $status, $installationDate]) {
            $machine = (new Machine())->setName($name)->setMachineModel($models[$modelCode])->setSerialNumber($serial)
                ->setInstallationDate(new \DateTime($installationDate))->setCategory($models[$modelCode]->getFamily())
                ->setLocation($location)->setStatus($status)->setSite($sites[$site]);
            $manager->persist($machine);
            $machines[$serial] = $machine;
        }

        return $machines;
    }

    private function user(ObjectManager $manager, string $email, string $name, array $roles, ?Site $site = null): User
    {
        $user = (new User())->setEmail($email)->setFullName($name)->setRoles($roles)->setSite($site);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'roltech1234'));
        $manager->persist($user);

        return $user;
    }
}
