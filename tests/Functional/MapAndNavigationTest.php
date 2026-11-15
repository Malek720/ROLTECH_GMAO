<?php

namespace App\Tests\Functional;

use App\Entity\Machine;
use App\Entity\MachineModel;
use App\Entity\Site;
use App\Entity\User;
use App\Service\SiteMapDataProvider;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for Phase 6: Map, Navigation, Site Page, Machine List and Breadcrumbs.
 * Tests operate on roltech_gmao_copy_test and are self-contained and repeatable.
 */
final class MapAndNavigationTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private Site $siteSousse;
    private Site $siteBeja;
    private User $admin;
    private User $responsable;
    private User $techSousse;
    private User $techNoSite;
    private Machine $machineSousse;
    private Machine $machineBeja;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->seedData();
        $this->entityManager->clear();
        $this->siteSousse = $this->reload($this->siteSousse);
        $this->siteBeja = $this->reload($this->siteBeja);
        $this->admin = $this->reload($this->admin);
        $this->responsable = $this->reload($this->responsable);
        $this->techSousse = $this->reload($this->techSousse);
        $this->techNoSite = $this->reload($this->techNoSite);
        $this->machineSousse = $this->reload($this->machineSousse);
        $this->machineBeja = $this->reload($this->machineBeja);
    }

    protected function tearDown(): void
    {
        $connection = $this->entityManager->getConnection();
        foreach (['notification', 'maintenance_postponement', 'intervention', 'fault', 'maintenance_schedule', 'maintenance_task', 'part', 'machine_module', 'machine', 'user', 'site', 'machine_model'] as $table) {
            $connection->executeStatement('DELETE FROM '.$table);
        }
        parent::tearDown();
    }

    public function testSiteMapDataProviderScopingWithDatabase(): void
    {
        /** @var SiteMapDataProvider $provider */
        $provider = static::getContainer()->get(SiteMapDataProvider::class);

        // Admin and Responsable see all sites (2 sites)
        $adminData = $provider->forUser($this->admin);
        self::assertCount(2, $adminData);

        $respData = $provider->forUser($this->responsable);
        self::assertCount(2, $respData);

        // Technicien with site sees only their own site
        $techData = $provider->forUser($this->techSousse);
        self::assertCount(1, $techData);
        self::assertSame('Sousse', $techData[0]['name']);

        // Technicien without site sees 0 sites
        $noSiteData = $provider->forUser($this->techNoSite);
        self::assertCount(0, $noSiteData);
    }

    public function testTechnicienCanOpenOwnSitePageButNotOtherSite(): void
    {
        // Technicien opens their own site -> 200
        $this->client->loginUser($this->techSousse);
        $this->client->request('GET', '/sites/'.$this->siteSousse->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Sousse');
        self::assertSelectorExists('.rtn-breadcrumb');

        // Technicien attempts to open another site -> 403
        $this->client->request('GET', '/sites/'.$this->siteBeja->getId());
        self::assertResponseStatusCodeSame(403);
    }

    public function testTechnicienGets403OnMachineFicheWhileResponsableGets200(): void
    {
        // Technicien gets 403 on fiche (rule not weakened)
        $this->client->loginUser($this->techSousse);
        $this->client->request('GET', '/machines/'.$this->machineSousse->getId().'/fiche');
        self::assertResponseStatusCodeSame(403);

        // Responsable gets 200 on fiche
        $this->client->loginUser($this->responsable);
        $this->client->request('GET', '/machines/'.$this->machineSousse->getId().'/fiche');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.rtn-breadcrumb');
    }

    public function testMapPageRendersForTechnicienAndShowsSites(): void
    {
        $this->client->loginUser($this->techSousse);
        $this->client->request('GET', '/carte');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#rtn-map');
        self::assertSelectorExists('.rtn-breadcrumb');
    }

    public function testMachineListScopedByRoleAndFilterable(): void
    {
        // Technicien only sees Sousse machines
        $this->client->loginUser($this->techSousse);
        $this->client->request('GET', '/parc');
        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Convoyeur Sousse', $content);
        self::assertStringNotContainsString('Convoyeur Béja', $content);

        // Responsable sees all machines
        $this->client->loginUser($this->responsable);
        $this->client->request('GET', '/parc');
        self::assertResponseIsSuccessful();
        $respContent = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Convoyeur Sousse', $respContent);
        self::assertStringContainsString('Convoyeur Béja', $respContent);

        // Text search filter
        $this->client->request('GET', '/parc?q=Béja');
        self::assertResponseIsSuccessful();
        $searchContent = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Convoyeur Béja', $searchContent);
        self::assertStringNotContainsString('Convoyeur Sousse', $searchContent);

        // Status filter
        $this->client->request('GET', '/parc?status=en+panne');
        self::assertResponseIsSuccessful();
        $statusContent = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Convoyeur Béja', $statusContent);
        self::assertStringNotContainsString('Convoyeur Sousse', $statusContent);
    }

    public function testUnauthenticatedUsersAreRedirectedToLogin(): void
    {
        $this->client->request('GET', '/carte');
        self::assertResponseRedirects('/login');

        $this->client->request('GET', '/parc');
        self::assertResponseRedirects('/login');

        $this->client->request('GET', '/sites/'.$this->siteSousse->getId());
        self::assertResponseRedirects('/login');
    }

    private function reload(object $entity): object
    {
        return $this->entityManager->find($entity::class, $entity->getId());
    }

    private function seedData(): void
    {
        $this->siteSousse = (new Site())->setName('Sousse')->setCity('Sousse')->setLatitude(35.8256)->setLongitude(10.6084);
        $this->siteBeja = (new Site())->setName('Béja')->setCity('Béja')->setLatitude(36.7256)->setLongitude(9.1817);

        $model1 = (new MachineModel())->setCode('MOD-1')->setName('Modèle 1')->setFamily('Convoyeur')->setDataStatus('confirmed');
        $model2 = (new MachineModel())->setCode('MOD-2')->setName('Modèle 2')->setFamily('Presse')->setDataStatus('demo');

        $this->admin = (new User())->setEmail('admin@map-test.local')->setFullName('Admin')->setRoles(['ROLE_ADMIN'])->setPassword('test');
        $this->responsable = (new User())->setEmail('resp@map-test.local')->setFullName('Resp')->setRoles(['ROLE_RESPONSABLE'])->setSite($this->siteSousse)->setPassword('test');
        $this->techSousse = (new User())->setEmail('tech-so@map-test.local')->setFullName('Tech SO')->setRoles(['ROLE_TECHNICIEN'])->setSite($this->siteSousse)->setPassword('test');
        $this->techNoSite = (new User())->setEmail('tech-none@map-test.local')->setFullName('Tech None')->setRoles(['ROLE_TECHNICIEN'])->setPassword('test');

        $this->machineSousse = (new Machine())
            ->setName('Convoyeur Sousse')
            ->setSerialNumber('SN-SO-01')
            ->setMachineModel($model1)
            ->setSite($this->siteSousse)
            ->setLocation('Atelier 1')
            ->setStatus('en service')
            ->setCategory('Convoyeur')
            ->setInstallationDate(new DateTime('-1 year'));

        $this->machineBeja = (new Machine())
            ->setName('Convoyeur Béja')
            ->setSerialNumber('SN-BJ-01')
            ->setMachineModel($model2)
            ->setSite($this->siteBeja)
            ->setLocation('Atelier 2')
            ->setStatus('en panne')
            ->setCategory('Presse')
            ->setInstallationDate(new DateTime('-6 months'));

        foreach ([
            $this->siteSousse, $this->siteBeja,
            $model1, $model2,
            $this->admin, $this->responsable, $this->techSousse, $this->techNoSite,
            $this->machineSousse, $this->machineBeja,
        ] as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();
    }
}
