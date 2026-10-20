<?php

declare(strict_types=1);

namespace SailConnect\Auth\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SailConnect\Auth\Validator;

class ValidatorTest extends TestCase
{
    #[DataProvider('passwords')]
    public function testPasswordRules(string $password, bool $valid): void
    {
        $this->assertSame($valid, Validator::passwordErrors($password) === []);
    }

    public static function passwords(): array
    {
        return [
            'good' => ['Passw0rdX', true],
            'too short' => ['Pw0rdX', false],
            'no uppercase' => ['passw0rdx', false],
            'no lowercase' => ['PASSW0RDX', false],
            'no digit' => ['PasswordXx', false],
            'over bcrypt limit' => [str_repeat('Aa1', 25), false],
        ];
    }

    #[DataProvider('membershipNumbers')]
    public function testMembershipNumbers(string $number, bool $valid): void
    {
        $this->assertSame($valid, Validator::membershipNumber($number));
    }

    public static function membershipNumbers(): array
    {
        return [
            'club format with hyphen' => ['7060-13489', true],
            'plain digits' => ['123456', true],
            'letters and digits' => ['U002', true],
            'free text' => ['Lost my card', false],
            'an email address' => ['a@b.com', false],
            'leading hyphen' => ['-123', false],
            'too long' => [str_repeat('1', 21), false],
            'empty' => ['', false],
        ];
    }

    #[DataProvider('displayNames')]
    public function testDisplayNameBase(string $first, string $last, string $expected): void
    {
        $this->assertSame($expected, Validator::displayNameBase($first, $last));
    }

    public static function displayNames(): array
    {
        return [
            ['Tim', 'Moses', 'TimM'],
            ['tim', 'moses', 'TimM'],
            ['Mary Ann', "O'Brien", 'MaryAnnO'],
            ['Émile', 'zola', 'ÉmileZ'],
        ];
    }

    public function testProfileRequiresMembershipNumberForMembers(): void
    {
        $errors = Validator::profile(['first_name' => 'A', 'last_name' => 'B', 'is_member' => true, 'membership_number' => '']);
        $this->assertArrayHasKey('membership_number', $errors);

        $this->assertSame([], Validator::profile(['first_name' => 'A', 'last_name' => 'B', 'is_member' => false]));
    }

    public function testEmailValidation(): void
    {
        $this->assertTrue(Validator::email('a@example.com'));
        $this->assertFalse(Validator::email('not-an-email'));
    }
}
