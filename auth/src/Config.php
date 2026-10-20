<?php

declare(strict_types=1);

namespace SailConnect\Auth;

use Dotenv\Dotenv;

/**
 * Typed access to .env settings. Values are read once at construction.
 */
final class Config
{
    /** @param array<string,string> $env */
    public function __construct(private array $env)
    {
    }

    /**
     * Reads <rootDir>/.env only. It never touches the process environment, so applications that embed
     * the session verifier (and have their own DB_PATH, APP_URL, SMTP_* ...) cannot leak into or
     * override auth's settings.
     */
    public static function load(string $rootDir): self
    {
        $file = $rootDir . '/.env';
        $env = is_file($file) ? Dotenv::parse((string) file_get_contents($file)) : [];
        return new self($env + ['_ROOT' => $rootDir]);
    }

    public function get(string $key, string $default = ''): string
    {
        $v = $this->env[$key] ?? '';
        return $v === '' ? $default : $v;
    }

    public function int(string $key, int $default): int
    {
        $v = $this->get($key);
        return ctype_digit($v) && (int) $v > 0 ? (int) $v : $default;
    }

    public function bool(string $key): bool
    {
        return in_array(strtolower($this->get($key)), ['1', 'true', 'yes'], true);
    }

    public function rootDir(): string
    {
        return $this->env['_ROOT'];
    }

    public function dbPath(): string
    {
        $p = $this->get('DB_PATH', './database/auth.db');
        return str_starts_with($p, '/') ? $p : $this->rootDir() . '/' . ltrim($p, './');
    }

    public function inactivitySeconds(): int
    {
        return $this->int('INACTIVITY_EXPIRY_MINUTES', 30) * 60;
    }

    public function sessionMaxSeconds(): int
    {
        return $this->int('SESSION_MAX_HOURS', 168) * 3600;
    }

    public function cookieName(): string
    {
        return $this->get('COOKIE_NAME', 'sc_session');
    }

    /** Readable (non-HttpOnly) marker so front ends can pick signed-in/out navigation synchronously. */
    public const HINT_COOKIE = 'sc_signed_in';

    public function cookieDomain(): string
    {
        return $this->get('COOKIE_DOMAIN');
    }

    public function appUrl(): string
    {
        return rtrim($this->get('APP_URL', 'http://localhost:8001'), '/');
    }

    public function isSecure(): bool
    {
        return str_starts_with($this->appUrl(), 'https://');
    }
}
