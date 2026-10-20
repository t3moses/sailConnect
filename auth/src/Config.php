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

    public static function load(string $rootDir): self
    {
        if (file_exists($rootDir . '/.env')) {
            Dotenv::createImmutable($rootDir)->safeLoad();
        }
        $env = $_ENV + $_SERVER;
        $strings = array_filter($env, 'is_string');
        return new self($strings + ['_ROOT' => $rootDir]);
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
