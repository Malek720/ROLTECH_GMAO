<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Machine;
use App\Entity\Fault;
use App\Entity\Intervention;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // --- Users ---
        $admin = new User();
        $admin->setEmail('admin@roltechsystems.com');
        $admin->setFullName('Ahmed Ben Salah');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin1234'));
        $manager->persist($admin);

        $tech1 = new User();
        $tech1->setEmail('karim.technicien@roltechsystems.com');
        $tech1->setFullName('Karim Trabelsi');
        $tech1->setRoles(['ROLE_TECHNICIEN']);
        $tech1->setPassword($this->passwordHasher->hashPassword($tech1, 'tech1234'));
        $manager->persist($tech1);

        $tech2 = new User();
        $tech2->setEmail('sami.technicien@roltechsystems.com');
        $tech2->setFullName('Sami Gharbi');
        $tech2->setRoles(['ROLE_TECHNICIEN']);
        $tech2->setPassword($this->passwordHasher->hashPassword($tech2, 'tech1234'));
        $manager->persist($tech2);

        $resp = new User();
        $resp->setEmail('malek.responsable@roltechsystems.com');
        $resp->setFullName('Malek Ben Sassi');
        $resp->setRoles(['ROLE_RESPONSABLE']);
        $resp->setPassword($this->passwordHasher->hashPassword($resp, 'resp1234'));
        $manager->persist($resp);

        // --- Machines ---
        $machine1 = new Machine();
        $machine1->setName('Presse hydraulique P-100');
        $machine1->setModel('HP-2000X');
        $machine1->setSerialNumber('SN-2021-00451');
        $machine1->setInstallationDate(new \DateTime('2021-03-15'));
        $machine1->setCategory('Presse');
        $machine1->setLocation('Atelier A');
        $machine1->setStatus('en service');
        $manager->persist($machine1);

        $machine2 = new Machine();
        $machine2->setName('Tour CNC T-220');
        $machine2->setModel('CNC-T220');
        $machine2->setSerialNumber('SN-2019-00832');
        $machine2->setInstallationDate(new \DateTime('2019-11-02'));
        $machine2->setCategory('Usinage');
        $machine2->setLocation('Atelier B');
        $machine2->setStatus('en panne');
        $manager->persist($machine2);

        $machine3 = new Machine();
        $machine3->setName('Convoyeur C-05');
        $machine3->setModel('CV-500');
        $machine3->setSerialNumber('SN-2022-00120');
        $machine3->setInstallationDate(new \DateTime('2022-06-20'));
        $machine3->setCategory('Manutention');
        $machine3->setLocation('Atelier A');
        $machine3->setStatus('en maintenance');
        $manager->persist($machine3);

        $machine4 = new Machine();
        $machine4->setName('Compresseur CP-3');
        $machine4->setModel('AirMax-300');
        $machine4->setSerialNumber('SN-2020-00219');
        $machine4->setInstallationDate(new \DateTime('2020-01-10'));
        $machine4->setCategory('Utilitaire');
        $machine4->setLocation('Local technique');
        $machine4->setStatus('en service');
        $manager->persist($machine4);

        // --- Faults ---
        $fault1 = new Fault();
        $fault1->setMachine($machine2);
        $fault1->setReportedBy($tech1);
        $fault1->setReportedAt(new \DateTime('-5 days'));
        $fault1->setDescription('Arret brutal du moteur principal, bruit anormal au demarrage.');
        $fault1->setSeverity('critique');
        $fault1->setStatus('ouverte');
        $manager->persist($fault1);

        $fault2 = new Fault();
        $fault2->setMachine($machine3);
        $fault2->setReportedBy($tech2);
        $fault2->setReportedAt(new \DateTime('-2 days'));
        $fault2->setDescription('Convoyeur bloque par un desalignement de la bande.');
        $fault2->setSeverity('moyenne');
        $fault2->setStatus('en cours');
        $manager->persist($fault2);

        $fault3 = new Fault();
        $fault3->setMachine($machine1);
        $fault3->setReportedBy($tech1);
        $fault3->setReportedAt(new \DateTime('-30 days'));
        $fault3->setDescription('Fuite hydraulique mineure detectee lors de la ronde de controle.');
        $fault3->setSeverity('faible');
        $fault3->setStatus('resolue');
        $manager->persist($fault3);

        // --- Interventions ---
        $intervention1 = new Intervention();
        $intervention1->setFault($fault1);
        $intervention1->setTechnician($tech1);
        $intervention1->setType('curative');
        $intervention1->setStartDate(new \DateTime('-4 days'));
        $intervention1->setStatus('en cours');
        $intervention1->setComment('Diagnostic en cours, piece de rechange commandee.');
        $manager->persist($intervention1);

        $intervention2 = new Intervention();
        $intervention2->setFault($fault2);
        $intervention2->setTechnician($tech2);
        $intervention2->setType('curative');
        $intervention2->setStartDate(new \DateTime('-2 days'));
        $intervention2->setStatus('planifiee');
        $intervention2->setComment('Intervention prevue demain matin.');
        $manager->persist($intervention2);

        $intervention3 = new Intervention();
        $intervention3->setFault($fault3);
        $intervention3->setTechnician($tech1);
        $intervention3->setType('preventive');
        $intervention3->setStartDate(new \DateTime('-29 days'));
        $intervention3->setEndDate(new \DateTime('-29 days'));
        $intervention3->setStatus('terminee');
        $intervention3->setComment('Joint remplace, fuite corrigee.');
        $manager->persist($intervention3);

        $manager->flush();
    }
}
