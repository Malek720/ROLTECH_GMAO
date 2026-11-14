<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class RoleBasedAuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    use TargetPathTrait;

    private ?string $firewallName = null;

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Set by the security bundle so the handler can read the target path saved
     * by the entry point (the page the user wanted before being sent to login).
     */
    public function setFirewallName(string $firewallName): void
    {
        $this->firewallName = $firewallName;
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

        // If the user was sent to the login form from another page, send them
        // back there — but only when that page is a safe path of this site.
        $targetPath = $this->consumeSafeTargetPath($request);

        if ($targetPath !== null) {
            return new RedirectResponse($targetPath);
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

    /**
     * Returns the page the user wanted before logging in, only when it is a safe
     * relative path of this application. The value is read once and always
     * removed from the session, so it can never be replayed.
     */
    private function consumeSafeTargetPath(Request $request): ?string
    {
        if ($this->firewallName === null) {
            return null;
        }

        $session = $request->getSession();
        $targetPath = $this->getTargetPath($session, $this->firewallName);

        if ($targetPath === null) {
            return null;
        }

        $this->removeTargetPath($session, $this->firewallName);

        return $this->isSafeTargetPath($targetPath) ? $targetPath : null;
    }

    /**
     * Accepts a saved target path only when it is a page of this site. An
     * absolute URL or a protocol-relative URL ("//host") would turn the login
     * form into an open redirect.
     */
    private function isSafeTargetPath(string $targetPath): bool
    {
        if ($targetPath === '' || !str_starts_with($targetPath, '/') || str_starts_with($targetPath, '//')) {
            return false;
        }

        if (str_contains($targetPath, '://')) {
            return false;
        }

        // Never bounce back to the authentication endpoints.
        $path = rtrim((string) parse_url($targetPath, PHP_URL_PATH), '/');

        return $path !== '/login' && $path !== '/logout';
    }
}
