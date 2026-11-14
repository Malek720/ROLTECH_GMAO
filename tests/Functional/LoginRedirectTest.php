<?php

namespace App\Tests\Functional;

use App\Entity\Site;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Covers the "Login redirect" section of the phase 8 spec: the success handler
 * returns the user to the page they wanted before logging in, but only when the
 * saved target path is a safe relative path of this site.
 */
final class LoginRedirectTest extends WebTestCase
{
    private const EMAIL = 'login-redirect@test.local';
    private const PASSWORD = 'login-redirect-test';

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->seedResponsable();
    }

    protected function tearDown(): void
    {
        $connection = $this->entityManager->getConnection();
        foreach (['notification', 'maintenance_postponement', 'intervention', 'fault', 'maintenance_schedule', 'maintenance_task', 'part', 'machine_module', 'machine', 'user', 'site', 'machine_model'] as $table) {
            $connection->executeStatement('DELETE FROM '.$table);
        }
        parent::tearDown();
    }

    public function testValidSavedTargetPathIsUsedOnce(): void
    {
        $this->loginWithSavedTargetPath('/indicateurs');

        self::assertResponseRedirects('/indicateurs');
        // The saved path is consumed: it can never be replayed by a later login.
        self::assertFalse($this->client->getRequest()->getSession()->has('_security.main.target_path'));
    }

    public function testProtocolRelativeTargetPathIsIgnored(): void
    {
        $this->loginWithSavedTargetPath('//evil.example');

        self::assertResponseRedirects('/responsable');
    }

    public function testAbsoluteTargetPathIsIgnored(): void
    {
        $this->loginWithSavedTargetPath('https://evil.example');

        self::assertResponseRedirects('/responsable');
    }

    public function testLogoutTargetPathIsIgnored(): void
    {
        $this->loginWithSavedTargetPath('/logout');

        self::assertResponseRedirects('/responsable');
    }

    public function testNoSavedTargetPathKeepsRoleDashboard(): void
    {
        $this->loginWithSavedTargetPath(null);

        self::assertResponseRedirects('/responsable');
    }

    /**
     * Drives the real login form: stores a target path in a session the client
     * will reuse (through its session cookie), renders /login to get the
     * session-bound CSRF token, then submits the form.
     */
    private function loginWithSavedTargetPath(?string $targetPath): void
    {
        if ($targetPath !== null) {
            $this->seedSessionTargetPath($targetPath);
        }

        $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        if (preg_match('/window\.rtnCsrfToken = "([^"]+)"/', $content, $matches) !== 1) {
            self::fail('The login page did not expose a CSRF token.');
        }

        $this->client->request('POST', '/login_check', [
            '_username' => self::EMAIL,
            '_password' => self::PASSWORD,
            '_csrf_token' => $matches[1],
        ]);
    }

    /**
     * The session created by the previous response is already closed, so the
     * target path is seeded through the session factory and handed to the client
     * as a cookie: the next request starts that exact session.
     */
    private function seedSessionTargetPath(string $targetPath): void
    {
        /** @var SessionFactoryInterface $factory */
        $factory = static::getContainer()->get('session.factory');
        $session = $factory->createSession();
        $session->start();
        $session->set('_security.main.target_path', $targetPath);
        $session->save();

        $this->client->getCookieJar()->set(new Cookie(
            name: $session->getName(),
            value: $session->getId(),
            domain: 'localhost',
        ));
    }

    private function seedResponsable(): void
    {
        $site = (new Site())->setName('Site connexion')->setCity('Sousse');
        $user = (new User())->setEmail(self::EMAIL)->setFullName('Responsable connexion')->setRoles(['ROLE_RESPONSABLE'])->setSite($site);
        $user->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, self::PASSWORD));

        $this->entityManager->persist($site);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
