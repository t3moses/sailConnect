<?php

declare(strict_types=1);

namespace App\Application\DTO\Util;

/**
 * Key Transformer Utility
 *
 * Recursively converts array keys between snake_case and camelCase.
 * Used for API boundary transformations to maintain clean internal structures
 * while providing JavaScript-friendly camelCase JSON APIs.
 */
final class KeyTransformer
{
    /**
     * Convert all keys in an array from snake_case to camelCase recursively
     *
     * @param array<string, mixed> $data Data with snake_case keys
     * @return array<string, mixed> Data with camelCase keys
     */
    public static function toCamelCase(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            // Keep numeric keys as-is (for indexed arrays)
            $camelKey = is_int($key) ? $key : static::snakeToCamel($key);
            $result[$camelKey] = is_array($value)
                ? static::toCamelCase($value)
                : $value;
        }
        return $result;
    }

    /**
     * Convert all keys in an array from camelCase to snake_case recursively
     *
     * @param array<string, mixed> $data Data with camelCase keys
     * @return array<string, mixed> Data with snake_case keys
     */
    public static function toSnakeCase(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            // Keep numeric keys as-is (for indexed arrays)
            $snakeKey = is_int($key) ? $key : static::camelToSnake($key);
            $result[$snakeKey] = is_array($value)
                ? static::toSnakeCase($value)
                : $value;
        }
        return $result;
    }

    /**
     * Convert a single string from snake_case to camelCase
     *
     * @param string $snake Snake_case string
     * @return string camelCase string
     */
    private static function snakeToCamel(string $snake): string
    {
        return lcfirst(str_replace('_', '', ucwords($snake, '_')));
    }

    /**
     * Convert a single string from camelCase to snake_case
     *
     * @param string $camel camelCase string
     * @return string snake_case string
     */
    private static function camelToSnake(string $camel): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $camel));
    }
}
