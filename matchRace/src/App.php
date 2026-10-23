<?php

declare(strict_types=1);

namespace MatchRace;

use Dotenv\Dotenv;
use SailConnect\Auth\Client\SessionVerifier;
use SailConnect\Auth\Config as AuthConfig;

/**
 * Request handling for Match Race. Sign-in, registration, sign-out and the account profile belong to the
 * auth service; this app only stores the user type (boat owner or crew) for a signed-in sailConnect user.
 */
final class App
{
    private SessionVerifier $verifier;
    private AuthConfig $authConfig;
    private ?UserRepository $users = null;
    /** @var array<string,mixed>|null */
    private ?array $identity = null;
    private bool $identityLoaded = false;

    public function __construct(private string $root)
    {
        Dotenv::createImmutable($root)->safeLoad();
        $authDir = $this->setting('AUTH_DIR') ?: dirname($root) . '/auth';
        $this->authConfig = AuthConfig::load($authDir);
        $this->verifier = SessionVerifier::fromAuthDir($authDir);
    }

    public function handle(string $method, string $path): void
    {
        match ([$method, $path]) {
            ['GET', '/'] => $this->home(),
            ['GET', '/join'] => $this->joinForm(),
            ['POST', '/join'] => $this->join(),
            ['GET', '/dashboard'] => $this->dashboard(),
            ['GET', '/signin'] => $this->redirect($this->verifier->signInUrl($this->url('/dashboard'))),
            ['GET', '/signout'] => $this->redirect($this->verifier->signOutUrl($this->url('/'))),
            ['GET', '/profile'] => $this->redirect($this->authConfig->appUrl() . '/profile'),
            default => $this->notFound(),
        };
    }

    private function home(): void
    {
        $this->render('home', ['title' => 'Match Race', 'active' => 'home']);
    }

    private function joinForm(string $error = ''): void
    {
        $user = $this->currentUser();
        if ($user !== null) {
            $this->redirect('/dashboard');
        }
        $type = (string) ($_GET['type'] ?? '');
        $this->render('join', [
            'title' => 'Join Now',
            'active' => 'join',
            'selected' => isset(UserRepository::TYPES[$type]) ? $type : '',
            'error' => $error,
        ]);
    }

    private function join(): void
    {
        $type = (string) ($_POST['user_type'] ?? '');
        if (!isset(UserRepository::TYPES[$type])) {
            http_response_code(422);
            $this->joinForm('Choose whether you are a boat owner or crew.');
            return;
        }

        $identity = $this->identity();
        if ($identity === null) {
            // Register (or sign in) with sailConnect first; auth brings the user back here with the choice kept.
            $this->redirect($this->authConfig->appUrl() . '/register?return=' . rawurlencode($this->url('/join?type=' . $type)));
        }

        $this->users()->create($identity['user_id'], $type);
        $this->redirect('/dashboard');
    }

    private function dashboard(): void
    {
        $identity = $this->identity();
        if ($identity === null) {
            $this->redirect($this->verifier->signInUrl($this->url('/dashboard')));
        }
        $user = $this->users()->find($identity['user_id']);
        if ($user === null) {
            $this->redirect('/join');
        }
        $this->render('dashboard', [
            'title' => 'Dashboard',
            'active' => 'dashboard',
            'me' => $user,
            'owners' => $this->users()->namesByType('boat_owner'),
            'crew' => $this->users()->namesByType('crew'),
        ]);
    }

    private function notFound(): void
    {
        http_response_code(404);
        $this->render('not_found', ['title' => 'Not found', 'active' => '']);
    }

    /** The signed-in sailConnect identity (enrolled or not), or null. @return array<string,mixed>|null */
    private function identity(): ?array
    {
        if (!$this->identityLoaded) {
            $this->identity = $this->verifier->currentUser();
            $this->identityLoaded = true;
        }

        return $this->identity;
    }

    /** The signed-in user's Match Race row, or null when signed out or not yet joined. @return array<string,mixed>|null */
    private function currentUser(): ?array
    {
        $identity = $this->identity();

        return $identity === null ? null : $this->users()->find($identity['user_id']);
    }

    private function users(): UserRepository
    {
        return $this->users ??= new UserRepository(Database::connect($this->dbPath(), $this->authDbPath()));
    }

    private function dbPath(): string
    {
        $p = $this->setting('DB_PATH') ?: './database/matchRace.db';

        return str_starts_with($p, '/') ? $p : $this->root . '/' . ltrim($p, './');
    }

    private function authDbPath(): string
    {
        return $this->setting('AUTH_DB_PATH') ?: $this->authConfig->dbPath();
    }

    private function setting(string $name): string
    {
        $v = getenv($name);

        return is_string($v) && $v !== '' ? $v : (string) ($_ENV[$name] ?? '');
    }

    /** Absolute URL of a path on this application. */
    private function url(string $path): string
    {
        $scheme = ($_SERVER['HTTPS'] ?? 'off') !== 'off' ? 'https' : 'http';

        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $path;
    }

    private function redirect(string $to): never
    {
        header('Location: ' . $to, true, 302);
        exit;
    }

    /** @param array<string,mixed> $vars */
    private function render(string $view, array $vars): void
    {
        $vars += ['signedIn' => $this->identity() !== null, 'identity' => $this->identity(), 'profileUrl' => $this->authConfig->appUrl() . '/profile'];
        extract($vars);
        $h = static fn(mixed $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $typeLabels = UserRepository::TYPES;
        ob_start();
        require $this->root . '/views/' . $view . '.php';
        $content = ob_get_clean();
        header('Content-Type: text/html; charset=UTF-8');
        require $this->root . '/views/layout.php';
    }
}
