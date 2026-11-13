<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Entity\User;

final class RoleAccessTest extends WebTestCase
{
    public function testTechnicianDashboardRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/technicien');

        self::assertResponseRedirects('/login');
    }

    public function testResponsableDashboardRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/responsable');

        self::assertResponseRedirects('/login');
    }

    public function testFaultDeclarationRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/faults/new');

        self::assertResponseRedirects('/login');
    }

    public function testTechnicianCannotReachCatalogAdmin(): void
    {
        $client = static::createClient();
        $user = (new User())->setEmail('tech@test.local')->setFullName('Tech')->setRoles(['ROLE_TECHNICIEN'])->setPassword('x');
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, 1);
        $client->loginUser($user);
        $client->request('GET', '/admin');
        self::assertResponseStatusCodeSame(302);
    }

    public function testResponsableCannotReachCatalogAdmin(): void
    {
        $client = static::createClient();
        $user = (new User())->setEmail('resp@test.local')->setFullName('Resp')->setRoles(['ROLE_RESPONSABLE'])->setPassword('x');
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, 2);
        $client->loginUser($user);
        $client->request('GET', '/admin');
        self::assertResponseStatusCodeSame(302);
    }
}
