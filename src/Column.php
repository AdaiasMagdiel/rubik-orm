<?php

namespace AdaiasMagdiel\Rubik;

use AdaiasMagdiel\Rubik\Enum\Field;
use InvalidArgumentException;

class Column
{
    /**
     * Helper method to build a field definition array with common properties.
     *
     * @param Field $type The field type.
     * @param array $props Additional type-specific properties (e.g., length, values).
     * @param bool $unique Whether the field must be unique (default: false).
     * @param bool $notNull Whether the field cannot be null (default: false).
     * @param bool $primaryKey Whether the field is the primary key (default: false).
     * @param mixed $default The default value (default: null).
     * @param bool $autoincrement Whether the field auto-increments (default: false).
     * @param array $foreignKey Foreign key constraint details (default: []).
     * @return array The field definition array.
     */
    private static function buildField(
        Field $type,
        array $props = [],
        bool $unique = false,
        bool $notNull = false,
        bool $primaryKey = false,
        mixed $default = null,
        bool $autoincrement = false,
        array $foreignKey = []
    ): array {
        return array_merge(
            [
                'type' => $type,
                'unique' => $unique,
                'not_null' => $notNull,
                'primary_key' => $primaryKey,
                'default' => $default,
                'autoincrement' => $autoincrement,
                'foreign_key' => $foreignKey
            ],
            $props
        );
    }

    /**
     * Defines a FOREIGN KEY constraint for the model's table.
     *
     * @param string $references The column name in the referenced table.
     * @param string $table The referenced table name.
     * @param string $onDelete The action on deletion (e.g., 'CASCADE', 'SET NULL', 'RESTRICT', 'NO ACTION') (default: 'NO ACTION').
     * @param string $onUpdate The action on update (e.g., 'CASCADE', 'SET NULL', 'RESTRICT', 'NO ACTION') (default: 'NO ACTION').
     * @return array The field definition array with foreign key constraint.
     * @throws InvalidArgumentException If parameters are invalid or unsupported.
     */
    public static function ForeignKey(
        string $references,
        string $table,
        string $onDelete = 'NO ACTION',
        string $onUpdate = 'NO ACTION'
    ): array {
        $validActions = ['CASCADE', 'SET NULL', 'RESTRICT', 'NO ACTION'];
        $onDelete = strtoupper($onDelete);
        $onUpdate = strtoupper($onUpdate);

        if (empty($references) || empty($table)) {
            throw new InvalidArgumentException('Foreign key references and table cannot be empty.');
        }
        if (!in_array($onDelete, $validActions)) {
            throw new InvalidArgumentException("Invalid ON DELETE action: {$onDelete}. Must be one of: " . implode(', ', $validActions));
        }
        if (!in_array($onUpdate, $validActions)) {
            throw new InvalidArgumentException("Invalid ON UPDATE action: {$onUpdate}. Must be one of: " . implode(', ', $validActions));
        }

        return [
            'foreign_key' => [
                'references' => $references,
                'table' => $table,
                'on_delete' => $onDelete,
                'on_update' => $onUpdate
            ]
        ];
    }

    /**
     * Defines an INTEGER field for the model's table.
     *
     * @param bool $autoincrement Whether the field auto-increments (default: false).
     * @param bool $primaryKey Whether the field is the primary key (default: false).
     * @param bool $unique Whether the field must be unique (default: false).
     * @param bool $notNull Whether the field cannot be null (default: false).
     * @param int|null $default The default value (default: null).
     * @param array $foreignKey Foreign key constraint details (default: []).
     * @return array The field definition array.
     */
    public static function Int(
        bool $autoincrement = false,
        bool $primaryKey = false,
        bool $unique = false,
        bool $notNull = false,
        ?int $default = null,
        array $foreignKey = []
    ): array {
        return self::buildField(Field::INTEGER, [], $unique, $notNull, $primaryKey, $default, $autoincrement, $foreignKey);
    }

    /**
     * Defines a TEXT field for the model's table.
     *
     * @param bool $unique Whether the field must be unique (default: false).
     * @param bool $notNull Whether the field cannot be null (default: false).
     * @param bool $primaryKey Whether the field is the primary key (default: false).
     * @param string|null $default The default value (default: null).
     * @param array $foreignKey Foreign key constraint details (default: []).
     * @return array The field definition array.
     */
    public static function Text(
        bool $unique = false,
        bool $notNull = false,
        bool $primaryKey = false,
        ?string $default = null,
        array $foreignKey = []
    ): array {
        return self::buildField(Field::TEXT, [], $unique, $notNull, $primaryKey, $default, false, $foreignKey);
    }

    /**
     * Defines a REAL field for the model's table.
     *
     * @param bool $unique Whether the field must be unique (default: false).
     * @param bool $notNull Whether the field cannot be null (default: false).
     * @param bool $primaryKey Whether the field is the primary key (default: false).
     * @param float|null $default The default value (default: null).
     * @param array $foreignKey Foreign key constraint details (default: []).
     * @return array The field definition array.
     */
    public static function Real(
        bool $unique = false,
        bool $notNull = false,
        bool $primaryKey = false,
        ?float $default = null,
        array $foreignKey = []
    ): array {
        return self::buildField(Field::REAL, [], $unique, $notNull, $primaryKey, $default, false, $foreignKey);
    }

    /**
     * Defines a BLOB field for the model's table.
     *
     * @param bool $unique Whether the field must be unique (default: false).
     * @param bool $notNull Whether the field cannot be null (default: false).
     * @param mixed $default The default value (default: null).
     * @param array $foreignKey Foreign key constraint details (default: []).
     * @return array The field definition array.
     */
    public static function Blob(
        bool $unique = false,
        bool $notNull = false,
        mixed $default = null,
        array $foreignKey = []
    ): array {
        return self::buildField(Field::BLOB, [], $unique, $notNull, false, $default, false, $foreignKey);
    }

    /**
     * Defines a NUMERIC field for the model's table.
     *
     * @param bool $unique Whether the field must be unique (default: false).
     * @param bool $notNull Whether the field cannot be null (default: false).
     * @param bool $primaryKey Whether the field is the primary key (default: false).
     * @param int|float|null $default The default value (default: null).
     * @param array $foreignKey Foreign key constraint details (default: []).
     * @return array The field definition array.
     */
    public static function Numeric(
        bool $unique = false,
        bool $notNull = false,
        bool $primaryKey = false,
        int|float|null $default = null,
        array $foreignKey = []
    ): array {
        return self::buildField(Field::NUMERIC, [], $unique, $notNull, $primaryKey, $default, false, $foreignKey);
    }

    /**
     * Defines a BOOLEAN field for the model's table.
     *
     * @param bool $notNull Whether the field cannot be null (default: false).
     * @param bool|null $default The default value (default: null).
     * @param array $foreignKey Foreign key constraint details (default: []).
     * @return array The field definition array.
     */
    public static function Boolean(
        bool $notNull = false,
        ?bool $default = null,
        array $foreignKey = []
    ): array {
        return self::buildField(Field::BOOLEAN, [], false, $notNull, false, $default, false, $foreignKey);
    }

    /**
     * Defines a DATETIME field for the model's table.
     *
     * @param bool $notNull Whether the field cannot be null (default: false).
     * @param string|null $default The default value (default: null).
     * @param array $foreignKey Foreign key constraint details (default: []).
     * @return array The field definition array.
     */
    public static function DateTime(
        bool $notNull = false,
        ?string $default = null,
        array $foreignKey = []
    ): array {
        return self::buildField(Field::DATETIME, [], false, $notNull, false, $default, false, $foreignKey);
    }

    /**
     * Defines a VARCHAR or CHAR field for the model's table.
     *
     * @param Field $type VARCHAR or CHAR.
     * @param int $length The maximum (VARCHAR) or fixed (CHAR) length (default: 255 for VARCHAR, 1 for CHAR).
     * @param bool $unique Whether the field must be unique (default: false).
     * @param bool $notNull Whether the field cannot be null (default: false).
     * @param bool $primaryKey Whether the field is the primary key (default: false).
     * @param string|null $default The default value (default: null).
     * @param array $foreignKey Foreign key constraint details (default: []).
     * @return array The field definition array.
     * @throws InvalidArgumentException If length is invalid.
     */
    private static function StringField(
        Field $type,
        int $length,
        bool $unique = false,
        bool $notNull = false,
        bool $primaryKey = false,
        ?string $default = null,
        array $foreignKey = []
    ): array {
        $maxLength = $type === Field::VARCHAR ? 65535 : 255;
        if ($length < 1 || $length > $maxLength) {
            throw new InvalidArgumentException("{$type->value} length must be between 1 and $maxLength.");
        }
        return self::buildField($type, ['length' => $length], $unique, $notNull, $primaryKey, $default, false, $foreignKey);
    }

    /**
     * Defines a VARCHAR field for the model's table.
     *
     * @param int $length The maximum length (default: 255).
     * @param bool $unique Whether the field must be unique (default: false).
     * @param bool $notNull Whether the field cannot be null (default: false).
     * @param bool $primaryKey Whether the field is the primary key (default: false).
     * @param string|null $default The default value (default: null).
     * @param array $foreignKey Foreign key constraint details (default: []).
     * @return array The field definition array.
     */
    public static function Varchar(
        int $length = 255,
        bool $unique = false,
        bool $notNull = false,
        bool $primaryKey = false,
        ?string $default = null,
        array $foreignKey = []
    ): array {
        return self::StringField(Field::VARCHAR, $length, $unique, $notNull, $primaryKey, $default, $foreignKey);
    }

    /**
     * Defines a CHAR field for the model's table.
     *
     * @param int $length The fixed length (default: 1).
     * @param bool $unique Whether the field must be unique (default: false).
     * @param bool $notNull Whether the field cannot be null (default: false).
     * @param bool $primaryKey Whether the field is the primary key (default: false).
     * @param string|null $default The default value (default: null).
     * @param array $foreignKey Foreign key constraint details (default: []).
     * @return array The field definition array.
     */
    public static function Char(
        int $length = 1,
        bool $unique = false,
        bool $notNull = false,
        bool $primaryKey = false,
        ?string $default = null,
        array $foreignKey = []
    ): array {
        return self::StringField(Field::CHAR, $length, $unique, $notNull, $primaryKey, $default, $foreignKey);
    }

    /**
     * Defines a DECIMAL field for the model's table.
     *
     * @param int $precision The total number of digits (default: 10).
     * @param int $scale The number of decimal places (default: 2).
     * @param bool $unique Whether the field must be unique (default: false).
     * @param bool $notNull Whether the field cannot be null (default: false).
     * @param bool $primaryKey Whether the field is the primary key (default: false).
     * @param string|null $default The default value (default: null).
     * @param array $foreignKey Foreign key constraint details (default: []).
     * @return array The field definition array.
     * @throws InvalidArgumentException If precision or scale is invalid.
     */
    public static function Decimal(
        int $precision = 10,
        int $scale = 2,
        bool $unique = false,
        bool $notNull = false,
        bool $primaryKey = false,
        ?string $default = null,
        array $foreignKey = []
    ): array {
        if ($precision < 1 || $precision > 65) {
            throw new InvalidArgumentException('DECIMAL precision must be between 1 and 65.');
        }
        if ($scale < 0 || $scale > 30 || $scale > $precision) {
            throw new InvalidArgumentException('DECIMAL scale must be between 0 and 30 and not exceed precision.');
        }
        return self::buildField(Field::DECIMAL, ['precision' => $precision, 'scale' => $scale], $unique, $notNull, $primaryKey, $default, false, $foreignKey);
    }

    /**
     * Defines a FLOAT field for the model's table.
     *
     * @param bool $unique Whether the field must be unique (default: false).
     * @param bool $notNull Whether the field cannot be null (default: false).
     * @param bool $primaryKey Whether the field is the primary key (default: false).
     * @param float|null $default The default value (default: null).
     * @param array $foreignKey Foreign key constraint details (default: []).
     * @return array The field definition array.
     */
    public static function Float(
        bool $unique = false,
        bool $notNull = false,
        bool $primaryKey = false,
        ?float $default = null,
        array $foreignKey = []
    ): array {
        return self::buildField(Field::FLOAT, [], $unique, $notNull, $primaryKey, $default, false, $foreignKey);
    }

    /**
     * Defines an ENUM field for the model's table.
     *
     * @param array $values The allowed values for the ENUM.
     * @param bool $notNull Whether the field cannot be null (default: false).
     * @param string|null $default The default value (default: null).
     * @param array $foreignKey Foreign key constraint details (default: []).
     * @return array The field definition array.
     * @throws InvalidArgumentException If values array is empty or contains non-string values.
     */
    public static function Enum(
        array $values,
        bool $notNull = false,
        ?string $default = null,
        array $foreignKey = []
    ): array {
        if (empty($values)) {
            throw new InvalidArgumentException('ENUM values array cannot be empty.');
        }
        foreach ($values as $value) {
            if (!is_string($value)) {
                throw new InvalidArgumentException('ENUM values must be strings.');
            }
        }
        if ($default !== null && !in_array($default, $values, true)) {
            throw new InvalidArgumentException('Default value must be one of the ENUM values.');
        }
        return self::buildField(Field::ENUM, ['values' => $values], false, $notNull, false, $default, false, $foreignKey);
    }

    /**
     * Defines a TINYINT field for the model's table.
     *
     * @param bool $unsigned Whether the field is unsigned (default: false, MySQL only).
     * @param bool $unique Whether the field must be unique (default: false).
     * @param bool $notNull Whether the field cannot be null (default: false).
     * @param bool $primaryKey Whether the field is the primary key (default: false).
     * @param int|null $default The default value (default: null).
     * @param array $foreignKey Foreign key constraint details (default: []).
     * @return array The field definition array.
     * @throws InvalidArgumentException If default value is out of range.
     */
    public static function Tinyint(
        bool $unsigned = false,
        bool $unique = false,
        bool $notNull = false,
        bool $primaryKey = false,
        ?int $default = null,
        array $foreignKey = []
    ): array {
        if ($default !== null) {
            $min = $unsigned ? 0 : -128;
            $max = $unsigned ? 255 : 127;
            if ($default < $min || $default > $max) {
                throw new InvalidArgumentException("TINYINT default must be between $min and $max.");
            }
        }
        return self::buildField(Field::TINYINT, ['unsigned' => $unsigned], $unique, $notNull, $primaryKey, $default, false, $foreignKey);
    }
}
