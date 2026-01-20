<?php

namespace AdaiasMagdiel\Rubik\Trait\Model;

use AdaiasMagdiel\Rubik\Enum\Driver;
use AdaiasMagdiel\Rubik\Rubik;
use AdaiasMagdiel\Rubik\Enum\Field;
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
        $foreignKeys = [];

        foreach ($fields as $key => $field) {
            $columnDef = sprintf('%s %s', $key, self::getFieldString($field));
            $columns[] = $columnDef;

            if (!empty($field['foreign_key'])) {
                $fk = $field['foreign_key'];
                $foreignKeys[] = sprintf(
                    'FOREIGN KEY (%s) REFERENCES %s(%s)%s%s',
                    $key,
                    $fk['table'],
                    $fk['references'],
                    !empty($fk['on_delete']) && $fk['on_delete'] !== 'NO ACTION' ? " ON DELETE {$fk['on_delete']}" : '',
                    !empty($fk['on_update']) && $fk['on_update'] !== 'NO ACTION' ? " ON UPDATE {$fk['on_update']}" : ''
                );
            }
        }

        $sql = sprintf(
            'CREATE TABLE %s %s (%s)',
            $ifNotExists ? 'IF NOT EXISTS' : '',
            static::getTableName(),
            implode(', ', array_merge($columns, $foreignKeys))
        );

        $conn = Rubik::getConn();
        if (Rubik::getDriver() === Driver::SQLITE) {
            $conn->exec('PRAGMA foreign_keys = ON;');
        }

        return $conn->exec($sql) !== false;
    }

    /**
     * Truncates the model's table (removes all records but keeps the table structure).
     * 
     * @return bool True if successful, false otherwise.
     */
    public static function truncateTable(): bool
    {
        $sql = sprintf(
            '%s %s',
            Rubik::getDriver() === Driver::SQLITE ? 'DELETE FROM' : 'TRUNCATE TABLE',
            static::getTableName()
        );

        $conn = Rubik::getConn();
        if (Rubik::getDriver() === Driver::SQLITE) {
            $conn->exec('PRAGMA foreign_keys = ON;');
        }

        return $conn->exec($sql) !== false;
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

        $conn = Rubik::getConn();
        if (Rubik::getDriver() === Driver::SQLITE) {
            $conn->exec('PRAGMA foreign_keys = ON;');
        }

        return $conn->exec($sql) !== false;
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
        if (!isset($field['type'])) {
            throw new InvalidArgumentException('Field type is required.');
        }

        if ($field['type'] instanceof Field) {
            $typeValue = strtoupper($field['type']->value);
        } elseif (is_string($field['type'])) {
            $typeValue = strtoupper(trim($field['type']));
        } else {
            throw new InvalidArgumentException(
                'Field type must be either a string or an instance of AdaiasMagdiel\\Rubik\\Enum\\Field.'
            );
        }

        $driver = Rubik::getDriver();
        $typeStr = $typeValue;

        // === DRIVER-SPECIFIC ADJUSTMENTS ======================================

        if ($driver === Driver::SQLITE) {
            // SQLite uses affinities; map unsupported types
            $typeStr = match ($typeValue) {
                'VARCHAR', 'CHAR', 'ENUM', 'SET' => 'TEXT',
                'DECIMAL', 'NUMERIC', 'TINYINT' => 'NUMERIC',
                'FLOAT', 'DOUBLE', 'REAL' => 'REAL',
                default => $typeStr, // INTEGER, TEXT, REAL, BLOB, NUMERIC, BOOLEAN, DATETIME
            };
        } elseif ($driver === Driver::MYSQL) {
            // MySQL-specific formatting
            $typeUpper = strtoupper($typeValue);

            if (in_array($typeUpper, ['VARCHAR', 'CHAR', 'VARBINARY', 'BINARY']) && isset($field['length'])) {
                $typeStr .= "({$field['length']})";
            } elseif (
                in_array($typeUpper, ['DECIMAL', 'NUMERIC', 'FLOAT', 'DOUBLE'])
                && isset($field['precision'], $field['scale'])
            ) {
                $typeStr .= "({$field['precision']},{$field['scale']})";
            } elseif ($typeUpper === 'ENUM' && isset($field['values'])) {
                if (empty($field['values'])) {
                    throw new InvalidArgumentException('ENUM values cannot be empty in MySQL.');
                }
                $escapedValues = array_map(fn($v) => "'" . addslashes($v) . "'", $field['values']);
                $typeStr .= '(' . implode(', ', $escapedValues) . ')';
            } elseif ($typeUpper === 'TINYINT' && ($field['unsigned'] ?? false)) {
                $typeStr .= ' UNSIGNED';
            }
        }

        // === GENERIC FIELD ATTRIBUTES =========================================

        $parts = [
            $typeStr,
            ($field['primary_key'] ?? false) ? 'PRIMARY KEY' : '',
            ($field['auto_increment'] ?? $field['autoincrement'] ?? false)
                ? ($driver === Driver::MYSQL ? 'AUTO_INCREMENT' : 'AUTOINCREMENT')
                : '',
            ($field['unique'] ?? false) ? 'UNIQUE' : '',
            ($field['not_null'] ?? false) ? 'NOT NULL' : '',
            isset($field['default']) ? sprintf('DEFAULT %s', self::escapeDefaultValue($field['default'])) : '',
            isset($field['on_update']) ? sprintf('ON UPDATE %s', self::escapeDefaultValue($field['on_update'])) : '',
        ];

        return implode(' ', array_filter($parts, fn($p) => $p !== ''));
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

        return Rubik::getConn()->quote($value);
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
