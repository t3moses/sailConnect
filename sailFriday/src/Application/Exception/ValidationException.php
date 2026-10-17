<?php

declare(strict_types=1);

namespace App\Application\Exception;

/**
 * Validation Exception
 *
 * Thrown when input validation fails.
 */
class ValidationException extends \RuntimeException
{
    private array $errors;

    /**
     * @param array<string, string> $errors Field => error message map
     */
    public function __construct(array $errors)
    {
        $this->errors = $errors;
        $message = 'Validation failed: ' . implode(', ', array_map(
            fn($field, $error) => "$field: $error",
            array_keys($errors),
            array_values($errors)
        ));
        parent::__construct($message);
    }

    /**
     * Get validation errors
     *
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
