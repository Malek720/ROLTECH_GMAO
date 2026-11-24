<?php

namespace App\Tests\Functional;

use App\Entity\Site;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for Login Asset Isolation and Shell Asset Loading.
 * Ensures rtn-ui.css and ui.js are NOT loaded on /login, while preserving all form hooks.
 * Ensures authenticated shell pages (e.g. /carte) properly load rtn-ui.css and ui.js.
 */
final class LoginAssetsTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        $connection = $this->entityManager->getConnection();
        foreach (['notification', 'maintenance_postponement', 'intervention', 'fault', 'maintenance_schedule', 'maintenance_task', 'part', 'machine_module', 'machine', 'user', 'site', 'machine_model'] as $table) {
            $connection->executeStatement('DELETE FROM '.$table);
        }
        parent::tearDown();
    }

    public function testLoginPageDoesNotLoadRtnUiCssOrUiJs(): void
    {
        $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();

        // Must NOT load rtn-ui.css nor ui.js on /login
        self::assertStringNotContainsString('rtn-ui', $content, '/login must not load rtn-ui.css');
        self::assertStringNotContainsString('/ui', $content, '/login must not load ui.js');

        // Must load login.css (AssetMapper may append digest hash)
        self::assertMatchesRegularExpression('#/assets/styles/login[^"]*\.css#', $content, '/login must load login.css');

        // Preserved form and behavior hooks
        self::assertStringContainsString('data-turbo="false"', $content, '/login must preserve data-turbo="false"');
        self::assertStringContainsString('window.rtnCsrfToken', $content, '/login must expose window.rtnCsrfToken');
        self::assertStringContainsString('window.rtnLastUsername', $content, '/login must expose window.rtnLastUsername');
        self::assertStringContainsString('id="rtn-card"', $content, '/login must preserve #rtn-card');
        self::assertStringContainsString('id="rtn-main-slot"', $content, '/login must preserve #rtn-main-slot');
        self::assertStringContainsString('data-role="technicien"', $content, '/login must preserve role picker buttons');
        self::assertStringContainsString('data-role="responsable"', $content);
        self::assertStringContainsString('data-role="admin"', $content);
    }

    public function testAuthenticatedShellPageLoadsRtnUiCssAndUiJs(): void
    {
        $site = new Site();
        $site->setName('Site Test Sousse')
            ->setCity('Sousse')
            ->setLatitude(35.8256)
            ->setLongitude(10.6084);
        $this->entityManager->persist($site);

        $user = new User();
        $user->setEmail('tech-assets@test.local')
            ->setFullName('Tech Asset Test')
            ->setRoles(['ROLE_TECHNICIEN'])
            ->setPassword('dummy')
            ->setSite($site);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/carte');
        self::assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();

        // Must load rtn-ui.css and ui.js on authenticated shell pages (AssetMapper hashed)
        self::assertMatchesRegularExpression('#/assets/styles/rtn-ui[^"]*\.css#', $content, 'Shell page /carte must load rtn-ui.css');
        self::assertMatchesRegularExpression('#/assets/ui[^"]*\.js#', $content, 'Shell page /carte must load ui.js');
    }
}
