<?php

declare(strict_types=1);

namespace SailConnect\Auth;

final class Validator
{
    public const PASSWORD_RULES = 'At least 8 characters, with an uppercase letter, a lowercase letter and a number.';

    /** @return list<string> */
    public static function passwordErrors(string $password): array
    {
        $errors = [];
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if (strlen($password) > 72) {
            $errors[] = 'Password must be at most 72 characters.';
        }
        if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password)) {
            $errors[] = 'Password needs an uppercase letter, a lowercase letter and a number.';
        }
        return $errors;
    }

    public static function email(string $email): bool
    {
        return strlen($email) <= 255 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function name(string $name): bool
    {
        return $name !== '' && mb_strlen($name) <= 50 && preg_match('/\p{L}/u', $name) === 1;
    }

    public static function membershipNumber(string $n): bool
    {
        return preg_match('/^[A-Za-z0-9]{1,20}$/', $n) === 1;
    }

    /**
     * Validates the fields shared by registration and profile editing.
     *
     * @param array<string,mixed> $in
     * @return array<string,string> field => message
     */
    public static function profile(array $in): array
    {
        $errors = [];
        if (!self::name((string) ($in['first_name'] ?? ''))) {
            $errors['first_name'] = 'First name is required.';
        }
        if (!self::name((string) ($in['last_name'] ?? ''))) {
            $errors['last_name'] = 'Last name is required.';
        }
        if (!empty($in['is_member']) && !self::membershipNumber((string) ($in['membership_number'] ?? ''))) {
            $errors['membership_number'] = 'Membership number is required for members (letters and digits only).';
        }
        return $errors;
    }

    /** Uppercase initial + last-name initial, e.g. "Tim", "Moses" => "TimM". */
    public static function displayNameBase(string $first, string $last): string
    {
        $clean = static fn (string $s): string => preg_replace('/[^\p{L}\p{N}]/u', '', $s) ?? '';
        $first = $clean($first);
        $last = $clean($last);
        return mb_strtoupper(mb_substr($first, 0, 1)) . mb_substr($first, 1)
            . mb_strtoupper(mb_substr($last, 0, 1));
    }
}
