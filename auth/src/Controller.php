<?php

declare(strict_types=1);

namespace SailConnect\Auth;

use PDO;

/**
 * All HTTP actions of the auth service. Each action returns void after emitting a response.
 */
final class Controller
{
    private const CSRF_COOKIE = 'sc_csrf';
    private const RESET_TTL = 3600;
    private const EMAIL_CHANGE_TTL = 86400;
    private const MAX_FAILED_LOGINS = 5;
    private const LOGIN_WINDOW = 900;

    /** @var array<string,mixed>|null */
    private ?array $user = null;
    private ?string $sessionToken = null;
    private string $csrf = '';

    public function __construct(
        private Config $config,
        private PDO $db,
        private UserRepository $users,
        private SessionService $sessions,
        private TokenService $tokens,
        private Turnstile $turnstile,
        private Mailer $mailer,
        private View $view,
    ) {
    }

    public function dispatch(string $method, string $path): void
    {
        $this->bootSession();
        $this->bootCsrf();
        if ($method === 'POST' && !$this->csrfOk()) {
            $this->render('message', ['title' => 'Request expired', 'heading' => 'Request expired', 'message' => 'Your form expired. Go back, reload the page and try again.'], 400);
            return;
        }

        $routes = [
            'GET /' => 'home',
            'GET /register' => 'registerForm',
            'POST /register' => 'register',
            'GET /signin' => 'signinForm',
            'POST /signin' => 'signin',
            'GET /signout' => 'signout',
            'POST /signout' => 'signout',
            'GET /forgot-password' => 'forgotForm',
            'POST /forgot-password' => 'forgot',
            'GET /reset-password' => 'resetForm',
            'POST /reset-password' => 'reset',
            'GET /profile' => 'profile',
            'POST /profile' => 'profileUpdate',
            'POST /profile/email' => 'emailChange',
            'POST /profile/password' => 'passwordChange',
            'GET /confirm-email' => 'emailConfirm',
            'GET /admin/users' => 'adminUsers',
            'POST /admin/users/active' => 'adminSetActive',
            'POST /admin/users/admin' => 'adminSetAdmin',
        ];
        $action = $routes["$method $path"] ?? null;
        if ($action === null) {
            $this->render('message', ['title' => 'Not found', 'heading' => 'Page not found', 'message' => 'That page does not exist.'], 404);
            return;
        }
        $this->$action();
    }

    // ------------------------------------------------------------------ home / session

    private function home(): void
    {
        $this->redirect($this->user ? '/profile' : '/signin');
    }

    private function signinForm(): void
    {
        if ($this->user) {
            $this->redirect($this->returnUrl() ?? '/profile');
        }
        $this->render('signin', ['title' => 'Sign in', 'errors' => [], 'email' => '', 'return' => $this->returnParam(), 'notice' => $_GET['notice'] ?? '']);
    }

    private function signin(): void
    {
        $email = strtolower(trim((string) ($_POST['email_address'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $fail = fn (string $msg) => $this->render('signin', [
            'title' => 'Sign in', 'errors' => ['form' => $msg], 'email' => $email,
            'return' => $this->returnParam(), 'notice' => '',
        ], 401);

        if ($this->tooManyFailures($email)) {
            $this->render('signin', ['title' => 'Sign in', 'errors' => ['form' => 'Too many failed attempts. Try again in 15 minutes.'], 'email' => $email, 'return' => $this->returnParam(), 'notice' => ''], 429);
            return;
        }

        $user = $this->users->findByEmail($email);
        // Verify against a dummy hash when the user is unknown so timing does not reveal accounts.
        $hash = $user['password_hash'] ?? '$2y$12$/f8huKRVC8CDROI7Ozf9c.KBCq9VOJ.ldSBib1HPTDdGCjl4WqgTG';
        $ok = password_verify($password, $hash) && $user !== null;

        if (!$ok) {
            $this->db->prepare('INSERT INTO login_attempts (email_address, ip_address, attempted_at) VALUES (?,?,?)')
                ->execute([$email, $ip, Database::now()]);
            $fail('Incorrect email address or password.');
            return;
        }
        if (!(int) $user['is_platform_active']) {
            $fail('This account has been deactivated. Contact the club administrator.');
            return;
        }

        $this->db->prepare('DELETE FROM login_attempts WHERE email_address = ?')->execute([$email]);
        $this->startSession($user['user_id']);
        $this->redirect($this->returnUrl() ?? '/profile');
    }

    private function signout(): void
    {
        if ($this->sessionToken !== null) {
            $this->sessions->destroy($this->sessionToken);
        }
        $this->clearCookie($this->config->cookieName());
        $this->clearCookie(Config::HINT_COOKIE);
        $this->redirect($this->returnUrl() ?? '/signin?notice=signed-out');
    }

    // ------------------------------------------------------------------ registration

    private function registerForm(): void
    {
        if ($this->user) {
            $this->redirect('/profile');
        }
        $this->render('register', $this->registerVars([], []));
    }

    private function register(): void
    {
        $in = [
            'email_address' => strtolower(trim((string) ($_POST['email_address'] ?? ''))),
            'email_confirm' => strtolower(trim((string) ($_POST['email_confirm'] ?? ''))),
            'first_name' => trim((string) ($_POST['first_name'] ?? '')),
            'last_name' => trim((string) ($_POST['last_name'] ?? '')),
            'is_member' => !empty($_POST['is_member']),
            'membership_number' => trim((string) ($_POST['membership_number'] ?? '')),
        ];
        $password = (string) ($_POST['password'] ?? '');
        $errors = Validator::profile($in);

        if (!Validator::email($in['email_address'])) {
            $errors['email_address'] = 'Enter a valid email address.';
        } elseif ($in['email_address'] !== $in['email_confirm']) {
            $errors['email_confirm'] = 'The email addresses do not match.';
        } elseif ($this->users->emailExists($in['email_address'])) {
            $errors['email_address'] = 'An account with this email address already exists.';
        }
        $pwErrors = Validator::passwordErrors($password);
        if ($pwErrors) {
            $errors['password'] = implode(' ', $pwErrors);
        } elseif ($password !== (string) ($_POST['password_confirm'] ?? '')) {
            $errors['password_confirm'] = 'The passwords do not match.';
        }
        if (empty($_POST['agree_terms'])) {
            $errors['agree_terms'] = 'You must accept the terms of use.';
        }
        if (!$this->turnstile->verify((string) ($_POST['cf-turnstile-response'] ?? ''), $_SERVER['REMOTE_ADDR'] ?? null)) {
            $errors['captcha'] = 'Captcha verification failed. Please try again.';
        }
        if ($errors) {
            $this->render('register', $this->registerVars($in, $errors), 422);
            return;
        }

        try {
            $user = $this->users->create($in + [
                'password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
                'terms_accepted_at' => Database::now(),
                'terms_url' => $this->config->get('TERMS_OF_USE_URL'),
            ]);
        } catch (\PDOException $e) {
            // Lost a race on the unique email index.
            $errors['email_address'] = 'An account with this email address already exists.';
            $this->render('register', $this->registerVars($in, $errors), 422);
            return;
        }

        $this->sendRegistrationEmails($user);
        $this->startSession($user['user_id']);
        $this->redirect($this->returnUrl() ?? '/profile?notice=welcome');
    }

    /** @param array<string,mixed> $values @param array<string,string> $errors @return array<string,mixed> */
    private function registerVars(array $values, array $errors): array
    {
        return [
            'title' => 'Create account', 'v' => $values, 'errors' => $errors,
            'siteKey' => $this->config->get('TURNSTILE_SITE_KEY'),
            'termsUrl' => $this->config->get('TERMS_OF_USE_URL'),
            'return' => $this->returnParam(), 'rules' => Validator::PASSWORD_RULES,
        ];
    }

    /** @param array<string,mixed> $user */
    private function sendRegistrationEmails(array $user): void
    {
        $recipients = $this->users->activeAdminEmails();
        $club = $this->config->get('CLUB_ADMIN_EMAIL');
        if ($club !== '') {
            $recipients[] = $club;
        }
        $notice = $this->view->partial('mail/registration_admin', ['u' => $user]);
        $seen = [];
        foreach ($recipients as $to) {
            if (isset($seen[strtolower($to)]) || strtolower($to) === $user['email_address']) {
                continue;
            }
            $seen[strtolower($to)] = true;
            $this->mailer->send($to, 'New registration: ' . $user['display_name'], $notice);
        }
        $this->mailer->send($user['email_address'], 'Welcome to sailConnect', $this->view->partial('mail/registration_confirm', ['u' => $user, 'url' => $this->config->appUrl()]));
    }

    // ------------------------------------------------------------------ password reset

    private function forgotForm(): void
    {
        $this->render('forgot', ['title' => 'Reset password', 'sent' => false, 'email' => '']);
    }

    private function forgot(): void
    {
        $email = strtolower(trim((string) ($_POST['email_address'] ?? '')));
        $user = Validator::email($email) ? $this->users->findByEmail($email) : null;
        if ($user && (int) $user['is_platform_active']) {
            $token = $this->tokens->issue($user['user_id'], 'password_reset', self::RESET_TTL);
            $url = $this->config->appUrl() . '/reset-password?token=' . $token;
            $this->mailer->send($user['email_address'], 'Reset your sailConnect password', $this->view->partial('mail/reset', ['u' => $user, 'url' => $url]));
        }
        // Same response whether or not the account exists.
        $this->render('forgot', ['title' => 'Reset password', 'sent' => true, 'email' => $email]);
    }

    private function resetForm(): void
    {
        $token = (string) ($_GET['token'] ?? '');
        if (!$this->tokens->isValid($token, 'password_reset')) {
            $this->render('message', ['title' => 'Link expired', 'heading' => 'Link expired', 'message' => 'This password reset link is invalid or has expired. Request a new one.', 'link' => ['/forgot-password', 'Reset password']], 400);
            return;
        }
        $this->render('reset', ['title' => 'Choose a new password', 'token' => $token, 'errors' => [], 'rules' => Validator::PASSWORD_RULES]);
    }

    private function reset(): void
    {
        $token = (string) ($_POST['token'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $errors = [];
        $pw = Validator::passwordErrors($password);
        if ($pw) {
            $errors['password'] = implode(' ', $pw);
        } elseif ($password !== (string) ($_POST['password_confirm'] ?? '')) {
            $errors['password_confirm'] = 'The passwords do not match.';
        }
        if ($errors) {
            $this->render('reset', ['title' => 'Choose a new password', 'token' => $token, 'errors' => $errors, 'rules' => Validator::PASSWORD_RULES], 422);
            return;
        }
        $row = $this->tokens->consume($token, 'password_reset');
        if (!$row) {
            $this->render('message', ['title' => 'Link expired', 'heading' => 'Link expired', 'message' => 'This password reset link is invalid or has expired. Request a new one.', 'link' => ['/forgot-password', 'Reset password']], 400);
            return;
        }
        $this->users->setPasswordHash($row['user_id'], password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]));
        $this->sessions->destroyAllForUser($row['user_id']);
        $this->redirect('/signin?notice=password-reset');
    }

    // ------------------------------------------------------------------ profile

    private function profile(string $notice = '', array $errors = [], array $values = [], int $status = 200): void
    {
        $this->requireUser();
        $this->render('profile', [
            'title' => 'Your profile', 'u' => $this->user, 'errors' => $errors, 'v' => $values,
            'notice' => $notice !== '' ? $notice : (string) ($_GET['notice'] ?? ''),
            'rules' => Validator::PASSWORD_RULES,
        ], $status);
    }

    private function profileUpdate(): void
    {
        $this->requireUser();
        $in = [
            'first_name' => trim((string) ($_POST['first_name'] ?? '')),
            'last_name' => trim((string) ($_POST['last_name'] ?? '')),
            'is_member' => !empty($_POST['is_member']),
            'membership_number' => trim((string) ($_POST['membership_number'] ?? '')),
        ];
        $errors = Validator::profile($in);
        if ($errors) {
            $this->profile('', $errors, $in, 422);
            return;
        }
        $this->users->updateProfile($this->user['user_id'], $in);
        $this->redirect('/profile?notice=saved');
    }

    private function emailChange(): void
    {
        $this->requireUser();
        $new = strtolower(trim((string) ($_POST['new_email'] ?? '')));
        $errors = [];
        if (!$this->currentPasswordOk()) {
            $errors['email_password'] = 'Current password is incorrect.';
        } elseif (!Validator::email($new)) {
            $errors['new_email'] = 'Enter a valid email address.';
        } elseif ($new !== strtolower(trim((string) ($_POST['new_email_confirm'] ?? '')))) {
            $errors['new_email'] = 'The email addresses do not match.';
        } elseif ($this->users->emailExists($new, $this->user['user_id'])) {
            $errors['new_email'] = 'That email address is already in use.';
        }
        if ($errors) {
            $this->profile('', $errors, [], 422);
            return;
        }
        $token = $this->tokens->issue($this->user['user_id'], 'email_change', self::EMAIL_CHANGE_TTL, $new);
        $url = $this->config->appUrl() . '/confirm-email?token=' . $token;
        $this->mailer->send($new, 'Confirm your new sailConnect email address', $this->view->partial('mail/email_change', ['u' => $this->user, 'url' => $url]));
        $this->redirect('/profile?notice=email-sent');
    }

    private function emailConfirm(): void
    {
        $row = $this->tokens->consume((string) ($_GET['token'] ?? ''), 'email_change');
        $new = $row['payload'] ?? null;
        if (!$row || !$new || $this->users->emailExists($new, $row['user_id'])) {
            $this->render('message', ['title' => 'Link expired', 'heading' => 'Link expired', 'message' => 'This confirmation link is invalid or has expired.', 'link' => ['/profile', 'Back to profile']], 400);
            return;
        }
        $old = $this->users->find($row['user_id']);
        $this->users->setEmail($row['user_id'], $new);
        if ($old) {
            $this->mailer->send($old['email_address'], 'Your sailConnect email address was changed', $this->view->partial('mail/email_changed_notice', ['u' => $old, 'new' => $new]));
        }
        $this->render('message', ['title' => 'Email updated', 'heading' => 'Email address updated', 'message' => 'Your sign-in email address is now ' . $new . '.', 'link' => ['/profile', 'Back to profile']]);
    }

    private function passwordChange(): void
    {
        $this->requireUser();
        $new = (string) ($_POST['new_password'] ?? '');
        $errors = [];
        if (!$this->currentPasswordOk()) {
            $errors['pw_current'] = 'Current password is incorrect.';
        } elseif ($pw = Validator::passwordErrors($new)) {
            $errors['new_password'] = implode(' ', $pw);
        } elseif ($new !== (string) ($_POST['new_password_confirm'] ?? '')) {
            $errors['new_password'] = 'The passwords do not match.';
        }
        if ($errors) {
            $this->profile('', $errors, [], 422);
            return;
        }
        $this->users->setPasswordHash($this->user['user_id'], password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]));
        $this->sessions->destroyAllForUser($this->user['user_id'], $this->sessionToken);
        $this->redirect('/profile?notice=password-saved');
    }

    private function currentPasswordOk(): bool
    {
        $full = $this->users->find($this->user['user_id']);
        return $full !== null && password_verify((string) ($_POST['current_password'] ?? ''), $full['password_hash']);
    }

    // ------------------------------------------------------------------ platform admin

    private function adminUsers(): void
    {
        $this->requireAdmin();
        $this->render('admin_users', ['title' => 'Users', 'wide' => true, 'users' => $this->users->all(), 'meId' => $this->user['user_id'], 'notice' => (string) ($_GET['notice'] ?? '')]);
    }

    private function adminSetActive(): void
    {
        $this->requireAdmin();
        $target = $this->users->find((string) ($_POST['user_id'] ?? ''));
        $active = !empty($_POST['active']);
        if (!$target) {
            $this->redirect('/admin/users?notice=not-found');
        }
        if (!$active && $target['user_id'] === $this->user['user_id']) {
            $this->redirect('/admin/users?notice=cannot-deactivate-self');
        }
        if (!$active && (int) $target['is_platform_admin'] && $this->users->activeAdminCount() <= 1) {
            $this->redirect('/admin/users?notice=last-admin');
        }
        $this->users->setActive($target['user_id'], $active);
        if (!$active) {
            $this->sessions->destroyAllForUser($target['user_id']);
        }
        $this->redirect('/admin/users?notice=updated');
    }

    private function adminSetAdmin(): void
    {
        $this->requireAdmin();
        $target = $this->users->find((string) ($_POST['user_id'] ?? ''));
        $admin = !empty($_POST['admin']);
        if (!$target) {
            $this->redirect('/admin/users?notice=not-found');
        }
        if (!$admin && (int) $target['is_platform_admin'] && (int) $target['is_platform_active'] && $this->users->activeAdminCount() <= 1) {
            $this->redirect('/admin/users?notice=last-admin');
        }
        $this->users->setAdmin($target['user_id'], $admin);
        $this->redirect('/admin/users?notice=updated');
    }

    // ------------------------------------------------------------------ plumbing

    private function bootSession(): void
    {
        $token = $_COOKIE[$this->config->cookieName()] ?? null;
        if (is_string($token) && $token !== '') {
            $user = $this->sessions->validate($token);
            if ($user) {
                $this->user = $user;
                $this->sessionToken = $token;
            } else {
                $this->clearCookie($this->config->cookieName());
                $this->clearCookie(Config::HINT_COOKIE);
            }
        }
    }

    private function startSession(string $userId): void
    {
        $token = $this->sessions->create($userId, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null);
        $this->setCookie($this->config->cookieName(), $token, $this->config->cookieDomain());
        $this->setCookie(Config::HINT_COOKIE, '1', $this->config->cookieDomain(), false);
    }

    private function bootCsrf(): void
    {
        $c = $_COOKIE[self::CSRF_COOKIE] ?? '';
        if (!is_string($c) || !preg_match('/^[a-f0-9]{32}$/', $c)) {
            $c = bin2hex(random_bytes(16));
            $this->setCookie(self::CSRF_COOKIE, $c, '');
        }
        $this->csrf = $c;
    }

    private function csrfOk(): bool
    {
        return hash_equals($this->csrf, (string) ($_POST['csrf'] ?? ''));
    }

    private function setCookie(string $name, string $value, string $domain, bool $httpOnly = true): void
    {
        setcookie($name, $value, [
            'expires' => 0, 'path' => '/', 'domain' => $domain,
            'secure' => $this->config->isSecure(), 'httponly' => $httpOnly, 'samesite' => 'Lax',
        ]);
    }

    private function clearCookie(string $name): void
    {
        setcookie($name, '', [
            'expires' => 1, 'path' => '/', 'domain' => $this->config->cookieDomain(),
            'secure' => $this->config->isSecure(), 'httponly' => $name !== Config::HINT_COOKIE, 'samesite' => 'Lax',
        ]);
    }

    private function tooManyFailures(string $email): bool
    {
        $st = $this->db->prepare('SELECT COUNT(*) FROM login_attempts WHERE email_address = ? AND attempted_at > ?');
        $st->execute([$email, gmdate('Y-m-d H:i:s', time() - self::LOGIN_WINDOW)]);
        return (int) $st->fetchColumn() >= self::MAX_FAILED_LOGINS;
    }

    private function requireUser(): void
    {
        if (!$this->user) {
            $this->redirect('/signin');
        }
    }

    private function requireAdmin(): void
    {
        $this->requireUser();
        if (!(int) $this->user['is_platform_admin']) {
            $this->render('message', ['title' => 'Forbidden', 'heading' => 'Not allowed', 'message' => 'Platform administrators only.'], 403);
            exit;
        }
    }

    private function returnParam(): string
    {
        return (string) ($_POST['return'] ?? $_GET['return'] ?? '');
    }

    /** A safe post-login destination: a local path or an https URL on an allowed host. */
    private function returnUrl(): ?string
    {
        $r = $this->returnParam();
        if ($r === '') {
            return null;
        }
        if (str_starts_with($r, '/') && !str_starts_with($r, '//') && !str_contains($r, '\\')) {
            return $r;
        }
        $p = parse_url($r);
        if (!$p || empty($p['host']) || !in_array($p['scheme'] ?? '', ['https', 'http'], true)) {
            return null;
        }
        if (($p['scheme'] === 'http') && $this->config->isSecure()) {
            return null;
        }
        foreach (array_filter(array_map('trim', explode(',', strtolower($this->config->get('ALLOWED_RETURN_HOSTS'))))) as $allowed) {
            $host = strtolower($p['host']);
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                return $r;
            }
        }
        return null;
    }

    /** @param array<string,mixed> $vars */
    private function render(string $name, array $vars, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        header('Cache-Control: no-store');
        echo $this->view->page($name, $vars + ['csrf' => $this->csrf, 'me' => $this->user, 'appUrl' => $this->config->appUrl()]);
    }

    private function redirect(string $to): never
    {
        header('Location: ' . $to, true, 303);
        exit;
    }
}
