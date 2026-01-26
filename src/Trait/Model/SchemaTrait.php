<?php

namespace AdaiasMagdiel\Rubik\Trait\Model;

use AdaiasMagdiel\Rubik\Enum\Driver;
use AdaiasMagdiel\Rubik\Rubik;
use AdaiasMagdiel\Rubik\Enum\Field;
use AdaiasMagdiel\Rubik\SQL;
use InvalidArgumentException;
use LogicException;

trait SchemaTrait
{
    /**
     * Defines the database schema for the model's table.
     *
     * This method must be implemented by subclasses to specify the table's
     * columns and their properties (type, length, constraints, etc.).
     *
     * @return array An associative array of field names and their configuration.
     * @throws LogicException If the method is not implemented in the subclass.
     */
    protected static function fields(): array
    {
        throw new LogicException(
            sprintf('Method fields() must be implemented in %s', static::class)
        );
    }

    /**
     * Retrieves the primary key column name for the model.
     *
     * Iterates through the defined fields to find the one marked as the primary key.
     *
     * @return string The name of the primary key column.
     * @throws LogicException If no primary key is defined in the schema.
     */
    public static function primaryKey(): string
    {
        $fields = static::fields();
        foreach ($fields as $key => $field) {
            if (($field['primary_key'] ?? false) || ($field['primaryKey'] ?? false)) {
                return $key;
            }
        }
        throw new LogicException('No primary key defined for model: ' . static::class);
    }

    /**
     * Retrieves the database table name associated with the model.
     *
     * If the `$table` property is explicitly defined, it is returned.
     * Otherwise, the table name is inferred from the class name (snake_case + pluralized).
     *
     * @return string The database table name.
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
     * Creates the database table based on the model's schema definition.
     *
     * Generates and executes the SQL CREATE TABLE statement, including columns
     * and foreign key constraints.
     *
     * @param bool $ifNotExists If true, adds the 'IF NOT EXISTS' clause to the statement.
     * @return bool True on success, false on failure.
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
     * Truncates the model's table, removing all records while preserving the structure.
     * 
     * For SQLite, this executes a 'DELETE FROM' statement as TRUNCATE is not supported.
     * For other drivers, it executes 'TRUNCATE TABLE'.
     * 
     * @return bool True on success, false on failure.
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
     * Drops the database table, removing both the structure and the data.
     * 
     * @param bool $ifExists If true, adds the 'IF EXISTS' clause to prevent errors if the table is missing.
     * @return bool True on success, false on failure.
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
     * Generates the SQL column definition string based on field configuration and active driver.
     *
     * Handles type mapping between generic Rubik types and driver-specific SQL types
     * (e.g., mapping VARCHAR to TEXT in SQLite).
     *
     * @param array $field The field configuration properties.
     * @return string The SQL column definition string.
     * @throws InvalidArgumentException If the field type is missing or invalid.
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
     * Escapes a default value for safe inclusion in DDL statements.
     *
     * @param mixed $value The default value to escape.
     * @return string The escaped default value string.
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
     * Checks if a specific field exists in the model's schema definition.
     *
     * @param string $key The field name to check.
     * @return bool True if the field exists, false otherwise.
     */
    private function hasField(string $key): bool
    {
        return array_key_exists($key, static::fields());
    }
}
