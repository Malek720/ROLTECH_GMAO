<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class RoleBasedAuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();

        // The redirect is always derived from the authenticated user's real
        // roles in the database — never from a role the client selected on
        // the login screen. The role picker is UI only.
        if (!$user instanceof User) {
            return new RedirectResponse($this->urlGenerator->generate('app_login'));
        }

        $roles = $user->getRoles();

        $route = match (true) {
            in_array('ROLE_ADMIN', $roles, true) => 'admin',
            in_array('ROLE_RESPONSABLE', $roles, true) => 'responsable_dashboard',
            in_array('ROLE_TECHNICIEN', $roles, true) => 'technicien_dashboard',
            default => 'app_login',
        };

        return new RedirectResponse($this->urlGenerator->generate($route));
    }
}
