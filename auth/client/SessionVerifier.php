<?php

declare(strict_types=1);

namespace SailConnect\Auth\Client;

use SailConnect\Auth\Config;
use SailConnect\Auth\Database;
use SailConnect\Auth\SessionService;

/**
 * Used by applications (sailFriday, matchRace, ...) running on the same server to find out who is
 * signed in. It validates the shared cookie against the auth database and slides the inactivity
 * window. The only write it performs is updating the session's last_touch.
 *
 *   $auth = SessionVerifier::fromAuthDir('/var/www/sailConnect/auth');
 *   $user = $auth->currentUser();            // null when not signed in
 *   $auth->requireUser();                    // redirects to the sign-in page when not signed in
 */
final class SessionVerifier
{
    public function __construct(private SessionService $sessions, private string $cookieName, private string $authUrl)
    {
    }

    public static function fromAuthDir(string $authDir): self
    {
        $config = Config::load($authDir);
        $db = Database::connect($config->dbPath());
        return new self(
            new SessionService($db, $config->inactivitySeconds(), $config->sessionMaxSeconds()),
            $config->cookieName(),
            $config->appUrl(),
        );
    }

    /**
     * @return array<string,mixed>|null user_id, email_address, first_name, last_name, display_name,
     *                                  is_member, membership_number, is_platform_admin, ...
     */
    public function currentUser(): ?array
    {
        $token = $_COOKIE[$this->cookieName] ?? null;
        return is_string($token) && $token !== '' ? $this->sessions->validate($token) : null;
    }

    /** @return array<string,mixed> */
    public function requireUser(?string $returnTo = null): array
    {
        $user = $this->currentUser();
        if ($user === null) {
            header('Location: ' . $this->signInUrl($returnTo), true, 302);
            exit;
        }
        return $user;
    }

    public function signInUrl(?string $returnTo = null): string
    {
        $returnTo ??= (($_SERVER['HTTPS'] ?? 'off') !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/');
        return $this->authUrl . '/signin?return=' . rawurlencode($returnTo);
    }

    public function signOutUrl(?string $returnTo = null): string
    {
        return $this->authUrl . '/signout' . ($returnTo !== null ? '?return=' . rawurlencode($returnTo) : '');
    }
}
