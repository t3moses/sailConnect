<?php

declare(strict_types=1);

namespace SailConnect\Auth;

/** Server-side verification of a Cloudflare Turnstile response. */
final class Turnstile
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(private string $secret)
    {
    }

    public function verify(string $response, ?string $ip): bool
    {
        if ($response === '' || $this->secret === '') {
            return false;
        }
        $ch = curl_init(self::VERIFY_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['secret' => $this->secret, 'response' => $response, 'remoteip' => $ip]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $body = curl_exec($ch);
        if (!is_string($body)) {
            return false;
        }
        $json = json_decode($body, true);
        return is_array($json) && ($json['success'] ?? false) === true;
    }
}
