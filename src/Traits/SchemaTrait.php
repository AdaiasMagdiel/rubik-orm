<?php

namespace AdaiasMagdiel\Rubik\Traits;

use AdaiasMagdiel\Rubik\Rubik;
use AdaiasMagdiel\Rubik\FieldEnum;
use AdaiasMagdiel\Rubik\SQL;
use InvalidArgumentException;
use RuntimeException;

trait SchemaTrait
{
    /**
     * Defines the field schema for the model's table.
     *
     * Must be implemented by subclasses to specify the table's columns and their properties.
     *
     * @return array Associative array of field names and their properties.
     * @throws RuntimeException If not implemented in the subclass.
     */
    protected static function fields(): array
    {
        throw new RuntimeException(
            sprintf('Method fields() must be implemented in %s', static::class)
        );
    }

    /**
     * Returns the primary key column name for the model.
     *
     * @return string The primary key column name.
     * @throws RuntimeException If no primary key is defined.
     */
    public static function primaryKey(): string
    {
        $fields = static::fields();
        foreach ($fields as $key => $field) {
            if ($field['primary_key'] ?? false) {
                return $key;
            }
        }
        throw new RuntimeException('No primary key defined for model.');
    }

    /**
     * Returns the table name for the model.
     *
     * Uses the explicitly defined $table property, or derives it from the class name.
     *
     * @return string The table name.
     */
    public static function getTableName(): string
    {
        if (!empty(static::$table)) {
            return static::$table;
        }

        $class = str_replace('\\', '/', static::class);
        return strtolower(basename($class)) . 's';
    }

    /**
     * Creates the model's table in the database based on the defined fields.
     *
     * @param bool $ifNotExists If true, adds IF NOT EXISTS to prevent errors if the table already exists (default: false).
     * @return bool True if the table was created successfully, false otherwise.
     */
    public static function createTable(bool $ifNotExists = false): bool
    {
        $fields = static::fields();
        $columns = [];

        foreach ($fields as $key => $field) {
            $columns[] = sprintf('%s %s', $key, self::getFieldString($field));
        }

        $sql = sprintf(
            'CREATE TABLE %s %s (%s)',
            $ifNotExists ? 'IF NOT EXISTS' : '',
            static::getTableName(),
            implode(', ', $columns)
        );

        return Rubik::getConn()->exec($sql) !== false;
    }

    /**
     * Truncates the model's table (removes all records but keeps the table structure).
     * 
     * @return bool True if successful, false otherwise.
     */
    public static function truncateTable(): bool
    {
        $sql = sprintf(
            'TRUNCATE TABLE %s',
            static::getTableName()
        );

        return Rubik::getConn()->exec($sql) !== false;
    }

    /**
     * Drops the model's table (completely removes the table and its data).
     * 
     * @param bool $ifExists If true, adds IF EXISTS to prevent errors (default: false).
     * @return bool True if successful, false otherwise.
     */
    public static function dropTable(bool $ifExists = false): bool
    {
        $sql = sprintf(
            'DROP TABLE %s %s',
            $ifExists ? 'IF EXISTS' : '',
            static::getTableName()
        );

        return Rubik::getConn()->exec($sql) !== false;
    }

    /**
     * Generates the SQL field definition string for a field.
     *
     * @param array $field The field properties (type, primary_key, autoincrement, etc.).
     * @return string The SQL field definition.
     * @throws InvalidArgumentException If the field configuration is invalid.
     */
    protected static function getFieldString(array $field): string
    {
        if (!isset($field['type']) || !$field['type'] instanceof FieldEnum) {
            throw new InvalidArgumentException('Field type must be a valid FieldEnum value.');
        }

        $driver = Rubik::getDriver();
        $typeStr = strtoupper($field['type']->value);

        // Driver-specific type mapping
        if ($driver === 'sqlite') {
            // SQLite uses affinities; map unsupported types
            $typeStr = match ($field['type']) {
                FieldEnum::VARCHAR, FieldEnum::CHAR, FieldEnum::ENUM => 'TEXT',
                FieldEnum::DECIMAL, FieldEnum::TINYINT => 'NUMERIC',
                FieldEnum::FLOAT => 'REAL',
                default => $typeStr, // INTEGER, TEXT, REAL, BLOB, NUMERIC, BOOLEAN, DATETIME
            };
        } elseif (in_array($driver, ['mysql', 'mariadb'])) {
            // MySQL-specific formatting
            if (in_array($field['type'], [FieldEnum::VARCHAR, FieldEnum::CHAR]) && isset($field['length'])) {
                $typeStr .= "({$field['length']})";
            } elseif ($field['type'] === FieldEnum::DECIMAL && isset($field['precision'], $field['scale'])) {
                $typeStr .= "({$field['precision']},{$field['scale']})";
            } elseif ($field['type'] === FieldEnum::ENUM && isset($field['values'])) {
                if (empty($field['values'])) {
                    throw new InvalidArgumentException('ENUM values cannot be empty in MySQL.');
                }
                $escapedValues = array_map(fn($v) => "'" . addslashes($v) . "'", $field['values']);
                $typeStr .= '(' . implode(', ', $escapedValues) . ')';
            } elseif ($field['type'] === FieldEnum::TINYINT && isset($field['unsigned']) && $field['unsigned']) {
                $typeStr .= ' UNSIGNED';
            }
        }

        $parts = [
            $typeStr,
            $field['primary_key'] ?? false ? 'PRIMARY KEY' : '',
            $field['autoincrement'] ?? false ? ($driver === 'mysql' ? 'AUTO_INCREMENT' : 'AUTOINCREMENT') : '',
            $field['unique'] ?? false ? 'UNIQUE' : '',
            $field['not_null'] ?? false ? 'NOT NULL' : '',
            isset($field['default']) ? sprintf('DEFAULT %s', self::escapeDefaultValue($field['default'])) : '',
        ];

        return implode(' ', array_filter($parts));
    }

    /**
     * Escapes a default value for use in SQL field definitions.
     *
     * @param mixed $value The default value to escape.
     * @return string The escaped default value.
     */
    protected static function escapeDefaultValue(mixed $value): string
    {
        if ($value instanceof SQL) {
            return (string) $value;
        }
        if (is_null($value)) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_string($value)) {
            return sprintf("'%s'", addslashes($value));
        }
        return (string)$value;
    }

    /**
     * Checks if a field exists in the model's schema.
     *
     * @param string $key The field name to check.
     * @return bool True if the field exists, false otherwise.
     */
    private function hasField(string $key): bool
    {
        return array_key_exists($key, static::fields());
    }
}
