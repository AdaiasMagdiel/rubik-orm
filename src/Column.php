<?php

namespace AdaiasMagdiel\Rubik;

use InvalidArgumentException;
use BadMethodCallException;
use RuntimeException;

/**
 * Flexible, driver-aware column definition builder for Rubik ORM.
 *
 * - Uses TYPE_META to describe each SQL type and its validation rules.
 * - Adapts automatically to MySQL/MariaDB, SQLite and future drivers.
 * - Invoked dynamically: Column::Varchar(...) or Column::Decimal(...).
 * - Also includes a ForeignKey() helper for relational constraints.
 *
 * @final
 */
final class Column
{
    /**
     * Type metadata map defining default values, validators, and driver-specific native mappings.
     *
     * Each key represents a logical type name (e.g. "VARCHAR").
     * Each value may include:
     *  - "defaults"   — default attributes for that type.
     *  - "validators" — validator method names to execute.
     *  - "mysql", "sqlite", "postgres" — driver-specific overrides.
     *
     * @var array<string,array<string,mixed>>
     */
    private const TYPE_META = [
        // ─── NUMERIC TYPES ───────────────────────────────────────────────

        'INTEGER' => [
            'defaults'   => ['autoincrement' => false],
            'validators' => ['validateInteger'],
            'sqlite'     => ['autoincrement_behavior' => 'special'],
        ],

        'BIGINT' => [
            'defaults'   => ['autoincrement' => false],
            'validators' => ['validateInteger'],
            'sqlite'     => ['native' => 'INTEGER'],
        ],

        'SMALLINT' => [
            'defaults'   => ['autoincrement' => false],
            'validators' => ['validateInteger'],
            'sqlite'     => ['native' => 'INTEGER'],
        ],

        'TINYINT' => [
            'defaults'   => ['unsigned' => false],
            'validators' => ['validateTinyint'],
            'sqlite'     => ['native' => 'INTEGER'],
            'postgres'   => ['native' => 'SMALLINT'],
        ],

        'MEDIUMINT' => [
            'defaults'   => ['autoincrement' => false],
            'validators' => ['validateInteger'],
            'sqlite'     => ['native' => 'INTEGER'],
            'postgres'   => ['native' => 'INTEGER'],
        ],

        'NUMERIC' => [
            'defaults'   => ['precision' => 10, 'scale' => 0],
            'validators' => ['validateNumeric'],
        ],

        'DECIMAL' => [
            'defaults'   => ['precision' => 10, 'scale' => 2],
            'validators' => ['validateDecimal'],
            'sqlite'     => ['native' => 'NUMERIC'],
        ],

        'REAL' => [
            'validators' => ['validateFloat'],
            'mysql'      => ['native' => 'DOUBLE'],
        ],

        'FLOAT' => [
            'defaults'   => ['precision' => 10, 'scale' => 2],
            'validators' => ['validateFloat'],
            'mysql'      => ['native' => 'FLOAT'],
            'sqlite'     => ['native' => 'REAL'],
            'postgres'   => ['native' => 'DOUBLE PRECISION'],
        ],

        'DOUBLE' => [
            'defaults'   => ['precision' => 15, 'scale' => 6],
            'validators' => ['validateFloat'],
            'mysql'      => ['native' => 'DOUBLE'],
            'sqlite'     => ['native' => 'REAL'],
            'postgres'   => ['native' => 'DOUBLE PRECISION'],
        ],

        'BIT' => [
            'defaults'   => ['length' => 1],
            'validators' => ['validateBit'],
            'sqlite'     => ['native' => 'INTEGER'],
        ],

        // ─── SERIAL (AUTO-INCREMENT) TYPES ─────────────────────────────────

        'SERIAL' => [
            'mysql'    => ['native' => 'INTEGER AUTO_INCREMENT'],
            'sqlite'   => ['native' => 'INTEGER'],
            'postgres' => ['native' => 'SERIAL'],
        ],

        'BIGSERIAL' => [
            'mysql'    => ['native' => 'BIGINT AUTO_INCREMENT'],
            'sqlite'   => ['native' => 'INTEGER'],
            'postgres' => ['native' => 'BIGSERIAL'],
        ],

        'SMALLSERIAL' => [
            'mysql'    => ['native' => 'SMALLINT AUTO_INCREMENT'],
            'sqlite'   => ['native' => 'INTEGER'],
            'postgres' => ['native' => 'SMALLSERIAL'],
        ],

        // ─── TEXTUAL TYPES ───────────────────────────────────────────────

        'TEXT' => [
            'defaults'   => ['length' => null],
            'validators' => ['validateText'],
        ],

        'VARCHAR' => [
            'defaults'   => ['length' => 255],
            'validators' => ['validateStringLength'],
            'sqlite'     => ['native' => 'TEXT'],
        ],

        'CHAR' => [
            'defaults'   => ['length' => 1],
            'validators' => ['validateStringLength'],
            'sqlite'     => ['native' => 'TEXT'],
        ],

        'TINYTEXT' => [
            'sqlite'     => ['native' => 'TEXT'],
            'postgres'   => ['native' => 'TEXT'],
            'validators' => ['validateText'],
        ],

        'MEDIUMTEXT' => [
            'sqlite'     => ['native' => 'TEXT'],
            'postgres'   => ['native' => 'TEXT'],
            'validators' => ['validateText'],
        ],

        'LONGTEXT' => [
            'sqlite'     => ['native' => 'TEXT'],
            'postgres'   => ['native' => 'TEXT'],
            'validators' => ['validateText'],
        ],

        'JSON' => [
            'validators' => ['validateJson'],
            'sqlite'     => ['native' => 'TEXT'],
        ],

        'JSONB' => [
            'validators' => ['validateJson'],
            'sqlite'     => ['native' => 'TEXT'],
            'mysql'      => ['native' => 'JSON'],
        ],

        'UUID' => [
            'validators' => ['validateUuid'],
            'sqlite'     => ['native' => 'TEXT'],
            'mysql'      => ['native' => 'CHAR(36)'],
            'postgres'   => ['native' => 'UUID'],
        ],

        // ─── BOOLEAN / ENUM ───────────────────────────────────────────────

        'BOOLEAN' => [
            'defaults'   => ['default' => null],
            'validators' => ['validateBoolean'],
            'sqlite'     => ['native' => 'INTEGER'],
            'mysql'      => ['native' => 'TINYINT(1)'],
        ],

        'ENUM' => [
            'validators' => ['validateEnum'],
            'sqlite'     => ['native' => 'TEXT'],
            'postgres'   => ['native' => 'TEXT'],
        ],

        'SET' => [
            'validators' => ['validateSet'],
            'sqlite'     => ['native' => 'TEXT'],
            'postgres'   => ['native' => 'TEXT'],
        ],

        // ─── DATE / TIME TYPES ───────────────────────────────────────────────

        'DATE' => [
            'validators' => ['validateDate'],
            'sqlite'     => ['native' => 'TEXT'],
        ],

        'DATETIME' => [
            'defaults'   => ['precision' => 0, 'onUpdate' => null],
            'validators' => ['validateDateTime'],
            'sqlite'     => ['native' => 'TEXT'],
            'postgres'   => ['native' => 'TIMESTAMP'],
        ],

        'TIMESTAMP' => [
            'defaults'   => ['precision' => 0],
            'validators' => ['validateDateTime'],
            'sqlite'     => ['native' => 'TEXT'],
            'mysql'      => ['native' => 'TIMESTAMP'],
        ],

        'TIME' => [
            'defaults'   => ['precision' => 0],
            'validators' => ['validateTime'],
            'sqlite'     => ['native' => 'TEXT'],
        ],

        'YEAR' => [
            'validators' => ['validateYear'],
            'sqlite'     => ['native' => 'INTEGER'],
            'postgres'   => ['native' => 'INTEGER'],
        ],

        // ─── BINARY TYPES ───────────────────────────────────────────────

        'BLOB' => [
            'validators' => ['validateBlob'],
            'postgres'   => ['native' => 'BYTEA'],
        ],

        'TINYBLOB' => [
            'validators' => ['validateBlob'],
            'sqlite'     => ['native' => 'BLOB'],
            'postgres'   => ['native' => 'BYTEA'],
        ],

        'MEDIUMBLOB' => [
            'validators' => ['validateBlob'],
            'sqlite'     => ['native' => 'BLOB'],
            'postgres'   => ['native' => 'BYTEA'],
        ],

        'LONGBLOB' => [
            'validators' => ['validateBlob'],
            'sqlite'     => ['native' => 'BLOB'],
            'postgres'   => ['native' => 'BYTEA'],
        ],

        'BYTEA' => [
            'validators' => ['validateBlob'],
            'mysql'      => ['native' => 'LONGBLOB'],
            'sqlite'     => ['native' => 'BLOB'],
        ],

        'BINARY' => [
            'defaults'   => ['length' => 1],
            'validators' => ['validateBinary'],
            'sqlite'     => ['native' => 'BLOB'],
        ],

        'VARBINARY' => [
            'defaults'   => ['length' => 255],
            'validators' => ['validateBinary'],
            'sqlite'     => ['native' => 'BLOB'],
        ],

        // ─── SPATIAL / GEOMETRIC TYPES ──────────────────────────────────

        'GEOMETRY' => [
            'validators' => ['validateGeometry'],
            'sqlite'     => ['native' => 'BLOB'],
            'postgres'   => ['native' => 'GEOMETRY'],
        ],

        'POINT' => [
            'validators' => ['validateGeometry'],
            'sqlite'     => ['native' => 'BLOB'],
            'postgres'   => ['native' => 'POINT'],
        ],

        'LINESTRING' => [
            'validators' => ['validateGeometry'],
            'sqlite'     => ['native' => 'BLOB'],
            'postgres'   => ['native' => 'LINESTRING'],
        ],

        'POLYGON' => [
            'validators' => ['validateGeometry'],
            'sqlite'     => ['native' => 'BLOB'],
            'postgres'   => ['native' => 'POLYGON'],
        ],
    ];

    /**
     * Dynamically handles static calls like Column::Varchar(), Column::Integer(), etc.
     *
     * @param string $type The logical column type name (e.g., "VARCHAR", "INTEGER").
     * @param array<string,mixed> $args Named arguments (length, default, precision, etc.).
     *
     * @return array<string,mixed> Fully normalized column definition.
     *
     * @throws BadMethodCallException If the requested type is not defined in TYPE_META.
     * @throws InvalidArgumentException If a validation rule fails.
     */
    public static function __callStatic(string $type, array $args)
    {
        $type = strtoupper($type);

        $meta = self::TYPE_META[$type] ?? null;
        if (!$meta) {
            throw new BadMethodCallException("Unsupported column type: $type");
        }

        // Normalize named arguments
        $p = $args;

        // Normalize camelCase keys → snake_case for internal consistency
        $p = array_combine(
            array_map(function ($k) {
                return match ($k) {
                    'notNull' => 'not_null',
                    'primaryKey' => 'primary_key',
                    'foreignKey' => 'foreign_key',
                    default => $k,
                };
            }, array_keys($p)),
            array_values($p)
        );


        // Merge defaults from TYPE_META
        $defaults = $meta['defaults'] ?? [];
        foreach ($defaults as $k => $v) {
            $p[$k] ??= $v;
        }

        // Run declared validators
        foreach ($meta['validators'] ?? [] as $validator) {
            if (method_exists(self::class, $validator)) {
                self::$validator($p);
            }
        }

        // Resolve native SQL type based on current driver
        $driver = Rubik::getDriver() ?? null;

        if ($driver === null) {
            throw new RuntimeException(
                'No active driver set. You must connect Rubik first before defining columns.'
            );
        }

        $native = $meta[strtolower($driver->value)]['native'] ?? $meta['native'] ?? $type;

        return self::buildField($native, $p);
    }


    /**
     * Builds the final normalized column array.
     *
     * @param string               $native The resolved SQL native type name.
     * @param array<string, mixed> $p      Normalized parameters for the column.
     *
     * @return array<string, mixed> The final column definition.
     */
    private static function buildField(string $native, array $p): array
    {
        return array_merge(['type' => $native], $p);
    }


    // ─── VALIDATORS ───────────────────────────────────────────────

    /**
     * Checks if a value is an instance of SQL::raw().
     *
     * @param mixed $value
     * @return bool
     */
    private static function isSqlRaw(mixed $value): bool
    {
        return $value instanceof SQL;
    }

    /**
     * Validates that string-based types (CHAR/VARCHAR) have valid lengths.
     *
     * @param array<string, mixed> $p
     * @throws InvalidArgumentException
     */
    private static function validateStringLength(array $p): void
    {
        $len = $p['length'] ?? 255;
        if ($len < 1 || $len > 65535) {
            throw new InvalidArgumentException('Length must be between 1 and 65535.');
        }

        $default = $p['default'] ?? null;
        if ($default !== null && !is_string($default) && !self::isSqlRaw($default)) {
            throw new InvalidArgumentException('VARCHAR/CHAR default must be string or SQL::raw().');
        }
    }

    /**
     * Validates DECIMAL precision, scale, and default value.
     *
     * @param array<string, mixed> $p
     * @throws InvalidArgumentException
     */
    private static function validateDecimal(array $p): void
    {
        $precision = $p['precision'] ?? 10;
        $scale     = $p['scale'] ?? 2;
        if ($precision < 1 || $precision > 65 || $scale < 0 || $scale > 30 || $scale > $precision) {
            throw new InvalidArgumentException('Invalid DECIMAL precision/scale.');
        }

        $default = $p['default'] ?? null;
        if ($default !== null && !is_numeric($default) && !self::isSqlRaw($default)) {
            throw new InvalidArgumentException('DECIMAL default must be numeric or SQL::raw().');
        }

        if ($default !== null && is_numeric($default) && !self::isSqlRaw($default)) {
            $maxValue = pow(10, $precision - $scale) - pow(10, -$scale);
            if (abs($default) > $maxValue) {
                throw new InvalidArgumentException(
                    "DECIMAL default exceeds precision($precision, $scale) limits."
                );
            }
        }
    }

    /**
     * Validates integer-based types.
     */
    private static function validateInteger(array $p): void
    {
        $d = $p['default'] ?? null;
        if ($d !== null && !is_int($d) && !self::isSqlRaw($d)) {
            throw new InvalidArgumentException('INTEGER default must be integer or SQL::raw().');
        }
    }

    /**
     * Validates TINYINT range and default.
     */
    private static function validateTinyint(array $p): void
    {
        $default  = $p['default'] ?? null;
        $unsigned = $p['unsigned'] ?? false;
        if ($default !== null && !self::isSqlRaw($default)) {
            $min = $unsigned ? 0 : -128;
            $max = $unsigned ? 255 : 127;
            if (!is_int($default) || $default < $min || $default > $max) {
                throw new InvalidArgumentException("TINYINT default must be between $min and $max or SQL::raw().");
            }
        }
    }

    /**
     * Validates ENUM values and default.
     */
    private static function validateEnum(array $p): void
    {
        $values = $p['values'] ?? [];
        if (empty($values) || array_filter($values, fn($v) => !is_string($v))) {
            throw new InvalidArgumentException('ENUM values must be a non-empty array of strings.');
        }
        $default = $p['default'] ?? null;
        if ($default !== null && !in_array($default, $values, true)) {
            throw new InvalidArgumentException('ENUM default must be one of the allowed values.');
        }
    }

    /**
     * Validates NUMERIC type (similar to DECIMAL but with broader limits).
     */
    private static function validateNumeric(array $p): void
    {
        $precision = $p['precision'] ?? 10;
        $scale = $p['scale'] ?? 0;
        if ($precision < 1 || $precision > 1000 || $scale < 0 || $scale > $precision) {
            throw new InvalidArgumentException('Invalid NUMERIC precision/scale.');
        }
        $default = $p['default'] ?? null;
        if ($default !== null && !is_numeric($default) && !self::isSqlRaw($default)) {
            throw new InvalidArgumentException('NUMERIC default must be numeric or SQL::raw().');
        }
    }

    /**
     * Validates FLOAT/REAL/DOUBLE types.
     */
    private static function validateFloat(array $p): void
    {
        $default = $p['default'] ?? null;
        if ($default !== null && !is_float($default) && !is_int($default) && !self::isSqlRaw($default)) {
            throw new InvalidArgumentException('FLOAT/REAL/DOUBLE default must be numeric or SQL::raw().');
        }

        $precision = $p['precision'] ?? null;
        $scale = $p['scale'] ?? null;

        if ($precision !== null && ($precision < 1 || $precision > 53)) {
            throw new InvalidArgumentException('FLOAT precision must be between 1 and 53.');
        }
    }

    /**
     * Validates BIT fields and length constraints.
     */
    private static function validateBit(array $p): void
    {
        $length = $p['length'] ?? 1;
        if ($length < 1 || $length > 64) {
            throw new InvalidArgumentException('BIT length must be between 1 and 64.');
        }
        $default = $p['default'] ?? null;
        if ($default !== null && !is_int($default) && !self::isSqlRaw($default)) {
            throw new InvalidArgumentException('BIT default must be integer or SQL::raw().');
        }
    }

    /**
     * Validates TEXT-based columns.
     */
    private static function validateText(array $p): void
    {
        $default = $p['default'] ?? null;
        if ($default !== null && !is_string($default) && !self::isSqlRaw($default)) {
            throw new InvalidArgumentException('TEXT default must be string or SQL::raw().');
        }
    }

    /**
     * Validates JSON type values.
     */
    private static function validateJson(array $p): void
    {
        $default = $p['default'] ?? null;

        if ($default === null) {
            return;
        }

        if (self::isSqlRaw($default)) {
            return;
        }

        if (is_string($default)) {
            json_decode($default);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException('Invalid JSON string: ' . json_last_error_msg());
            }
            return;
        }

        if (is_array($default) || is_object($default)) {
            if (json_encode($default) === false) {
                throw new InvalidArgumentException('Invalid JSON structure (cannot encode).');
            }
            return;
        }

        throw new InvalidArgumentException('JSON default must be a string, array, object, or SQL::raw().');
    }

    /**
     * Validates UUIDs against RFC 4122 format.
     */
    private static function validateUuid(array $p): void
    {
        $default = $p['default'] ?? null;

        if ($default === null) {
            return;
        }

        if (self::isSqlRaw($default)) {
            return;
        }

        if (!is_string($default)) {
            throw new InvalidArgumentException('UUID default must be a string or SQL::raw().');
        }

        // regex padrão RFC 4122
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
        if (!preg_match($pattern, $default)) {
            throw new InvalidArgumentException('Invalid UUID format.');
        }
    }

    /**
     * Validates BOOLEAN values and ensures only valid defaults are used.
     */
    private static function validateBoolean(array $p): void
    {
        $default = $p['default'] ?? null;
        if (
            $default !== null &&
            !is_bool($default) &&
            !in_array($default, [0, 1, '0', '1'], true) &&
            !self::isSqlRaw($default)
        ) {
            throw new InvalidArgumentException('BOOLEAN default must be boolean, 0/1, or SQL::raw().');
        }
    }

    /**
     * Validates SET type and its default value.
     */
    private static function validateSet(array $p): void
    {
        $values = $p['values'] ?? [];
        if (empty($values) || array_filter($values, fn($v) => !is_string($v))) {
            throw new InvalidArgumentException('SET values must be a non-empty array of strings.');
        }
        $default = $p['default'] ?? null;
        if ($default !== null) {
            if (!is_string($default)) {
                throw new InvalidArgumentException('SET default must be string.');
            }
            $defaultValues = array_map('trim', explode(',', $default));
            foreach ($defaultValues as $value) {
                if (!in_array($value, $values, true)) {
                    throw new InvalidArgumentException('SET default contains invalid value: ' . $value);
                }
            }
        }
    }

    /**
     * Validates DATE column default values.
     */
    private static function validateDate(array $p): void
    {
        $default = $p['default'] ?? null;
        if ($default !== null && !is_string($default) && !self::isSqlRaw($default)) {
            throw new InvalidArgumentException('DATE default must be string or SQL::raw().');
        }
    }

    /**
     * Validates DATETIME and TIMESTAMP defaults and precision.
     */
    private static function validateDateTime(array $p): void
    {
        $precision = $p['precision'] ?? 0;
        if ($precision < 0 || $precision > 6) {
            throw new InvalidArgumentException('DATETIME/TIMESTAMP precision must be between 0 and 6.');
        }

        $default = $p['default'] ?? null;
        if ($default !== null && !is_string($default) && !self::isSqlRaw($default)) {
            throw new InvalidArgumentException('DATETIME/TIMESTAMP default must be string or SQL::raw().');
        }
    }

    /**
     * Validates TIME fields.
     */
    private static function validateTime(array $p): void
    {
        $default = $p['default'] ?? null;
        if ($default === null || self::isSqlRaw($default)) {
            return;
        }

        if (!is_string($default) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $default)) {
            throw new InvalidArgumentException('TIME default must be a valid HH:MM:SS string or SQL::raw().');
        }
    }

    /**
     * Validates YEAR type ranges (MySQL-compatible).
     */
    private static function validateYear(array $p): void
    {
        $default = $p['default'] ?? null;
        if ($default !== null && !self::isSqlRaw($default)) {
            if (!is_int($default) || $default < 1901 || $default > 2155) {
                throw new InvalidArgumentException('YEAR must be between 1901 and 2155 or SQL::raw().');
            }
        }
    }

    /**
     * Validates BLOB and similar binary data types.
     */
    private static function validateBlob(array $p): void
    {
        $default = $p['default'] ?? null;
        if ($default !== null && !is_string($default) && !self::isSqlRaw($default)) {
            throw new InvalidArgumentException('BLOB default must be string.');
        }
    }

    /**
     * Validates fixed-length binary columns (BINARY, VARBINARY).
     */
    private static function validateBinary(array $p): void
    {
        $length = $p['length'] ?? 1;
        if ($length < 1 || $length > 65535) {
            throw new InvalidArgumentException('BINARY length must be between 1 and 65535.');
        }

        $default = $p['default'] ?? null;
        if ($default !== null && !is_string($default) && !self::isSqlRaw($default)) {
            throw new InvalidArgumentException('BINARY default must be string.');
        }
    }

    /**
     * Validates spatial/geometric data types.
     */
    private static function validateGeometry(array $p): void
    {
        // Geometry types typically don't have defaults
        // but we can validate if someone tries to set one
        $default = $p['default'] ?? null;
        if ($default !== null && !is_string($default) && !self::isSqlRaw($default)) {
            throw new InvalidArgumentException('GEOMETRY default must be WKT string or SQL::raw().');
        }
    }

    // ─── FOREIGN KEY HELPER ───────────────────────────────────────────────

    /**
     * Builds a normalized foreign key definition array.
     *
     * Example:
     * ```php
     * Column::ForeignKey('user_id', 'users', 'CASCADE', 'SET NULL');
     * ```
     *
     * @param string $references Column name being referenced.
     * @param string $table      Target table name.
     * @param string $onDelete   ON DELETE action (CASCADE, SET NULL, RESTRICT, NO ACTION, SET DEFAULT).
     * @param string $onUpdate   ON UPDATE action (CASCADE, SET NULL, RESTRICT, NO ACTION, SET DEFAULT).
     *
     * @return array<string, array<string,string>>
     *
     * @throws InvalidArgumentException If arguments are invalid or empty.
     */
    public static function ForeignKey(
        string $references,
        string $table,
        string $onDelete = 'NO ACTION',
        string $onUpdate = 'NO ACTION'
    ): array {
        $valid = ['CASCADE', 'SET NULL', 'RESTRICT', 'NO ACTION', 'SET DEFAULT'];
        $onDelete = strtoupper(str_replace('_', ' ', $onDelete));
        $onUpdate = strtoupper(str_replace('_', ' ', $onUpdate));


        if (empty($references) || empty($table)) {
            throw new InvalidArgumentException('Foreign key references and table cannot be empty.');
        }
        if (!in_array($onDelete, $valid, true) || !in_array($onUpdate, $valid, true)) {
            throw new InvalidArgumentException('Invalid ON DELETE/ON UPDATE action.');
        }

        return [
            'table'      => $table,
            'references' => $references,
            'on_delete'  => $onDelete,
            'on_update'  => $onUpdate,
        ];
    }
}
