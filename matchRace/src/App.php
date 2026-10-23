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
            ['GET', '/register'] => $this->redirect($this->registerUrl()),
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
        $this->requireEnrolled(false);
        $this->render('home', ['title' => 'Match Race', 'active' => 'home']);
    }

    /** Step two of joining: the user has a sailConnect account and now chooses a user type. */
    private function joinForm(string $error = ''): void
    {
        $identity = $this->identity();
        if ($identity === null) {
            $this->redirect($this->registerUrl());
        }
        if ($this->users()->find($identity['user_id']) !== null) {
            $this->redirect('/');
        }
        $this->render('join', ['title' => 'Join Now', 'active' => 'join', 'error' => $error]);
    }

    private function join(): void
    {
        $identity = $this->identity();
        if ($identity === null) {
            $this->redirect($this->registerUrl());
        }

        $type = (string) ($_POST['user_type'] ?? '');
        if (!isset(UserRepository::TYPES[$type])) {
            http_response_code(422);
            $this->joinForm('Choose whether you are a boat owner or crew.');
            return;
        }

        $this->users()->create($identity['user_id'], $type);
        $this->redirect('/');
    }

    private function dashboard(): void
    {
        $user = $this->requireEnrolled(true);
        $this->render('dashboard', [
            'title' => 'Dashboard',
            'active' => 'dashboard',
            'me' => $user,
            'owners' => $this->users()->namesByType('boat_owner'),
            'crew' => $this->users()->namesByType('crew'),
        ]);
    }

    /**
     * A signed-in user without a Match Race record is sent to /join. A signed-out visitor is sent to sign in
     * when $signInRequired, else allowed through (the landing page).
     *
     * @return array<string,mixed>|null The user's Match Race row
     */
    private function requireEnrolled(bool $signInRequired): ?array
    {
        $identity = $this->identity();
        if ($identity === null) {
            if ($signInRequired) {
                $this->redirect($this->verifier->signInUrl($this->url('/dashboard')));
            }
            return null;
        }
        $user = $this->users()->find($identity['user_id']);
        if ($user === null) {
            $this->redirect('/join');
        }

        return $user;
    }

    /** The auth "join or sign in" page, which returns to the user-type step. */
    private function registerUrl(): string
    {
        return $this->authConfig->appUrl() . '/start?return=' . rawurlencode($this->url('/join'));
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
        $identity = $this->identity();
        $vars += [
            'signedIn' => $identity !== null,
            'enrolled' => $identity !== null && $this->users()->find($identity['user_id']) !== null,
            'identity' => $identity,
            'profileUrl' => $this->authConfig->appUrl() . '/profile',
        ];
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
