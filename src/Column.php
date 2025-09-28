<?php

namespace AdaiasMagdiel\Rubik;

use InvalidArgumentException;

class Column
{
    /**
     * Helper method to build a field definition array with common properties.
     *
     * @param FieldEnum $type The field type.
     * @param array $props Additional type-specific properties (e.g., length, values).
     * @param bool $unique Whether the field must be unique (default: false).
     * @param bool $notNull Whether the field cannot be null (default: false).
     * @param bool $primaryKey Whether the field is the primary key (default: false).
     * @param mixed $default The default value (default: null).
     * @param bool $autoincrement Whether the field auto-increments (default: false).
     * @return array The field definition array.
     */
    private static function buildField(
        FieldEnum $type,
        array $props = [],
        bool $unique = false,
        bool $notNull = false,
        bool $primaryKey = false,
        mixed $default = null,
        bool $autoincrement = false
    ): array {
        return array_merge(
            ['type' => $type, 'unique' => $unique, 'not_null' => $notNull, 'primary_key' => $primaryKey, 'default' => $default, 'autoincrement' => $autoincrement],
            $props
        );
    }

    /**
     * Defines an INTEGER field for the model's table.
     *
     * @param bool $autoincrement Whether the field auto-increments (default: false).
     * @param bool $primaryKey Whether the field is the primary key (default: false).
     * @param bool $unique Whether the field must be unique (default: false).
     * @param bool $notNull Whether the field cannot be null (default: false).
     * @param int|null $default The default value (default: null).
     * @return array The field definition array.
     */
    public static function Int(
        bool $autoincrement = false,
        bool $primaryKey = false,
        bool $unique = false,
        bool $notNull = false,
        ?int $default = null
    ): array {
        return self::buildField(FieldEnum::INTEGER, [], $unique, $notNull, $primaryKey, $default, $autoincrement);
    }

    /**
     * Defines a TEXT field for the model's table.
     *
     * @param bool $unique Whether the field must be unique (default: false).
     * @param bool $notNull Whether the field cannot be null (default: false).
     * @param bool $primaryKey Whether the field is the primary key (default: false).
     * @param string|null $default The default value (default: null).
     * @return array The field definition array.
     */
    public static function Text(
        bool $unique = false,
        bool $notNull = false,
        bool $primaryKey = false,
        ?string $default = null
    ): array {
        return self::buildField(FieldEnum::TEXT, [], $unique, $notNull, $primaryKey, $default);
    }

    /**
     * Defines a REAL field for the model's table.
     *
     * @param bool $unique Whether the field must be unique (default: false).
     * @param bool $notNull Whether the field cannot be null (default: false).
     * @param bool $primaryKey Whether the field is the primary key (default: false).
     * @param float|null $default The default value (default: null).
     * @return array The field definition array.
     */
    public static function Real(
        bool $unique = false,
        bool $notNull = false,
        bool $primaryKey = false,
        ?float $default = null
    ): array {
        return self::buildField(FieldEnum::REAL, [], $unique, $notNull, $primaryKey, $default);
    }

    /**
     * Defines a BLOB field for the model's table.
     *
     * @param bool $unique Whether the field must be unique (default: false).
     * @param bool $notNull Whether the field cannot be null (default: false).
     * @param mixed $default The default value (default: null).
     * @return array The field definition array.
     */
    public static function Blob(
        bool $unique = false,
        bool $notNull = false,
        mixed $default = null
    ): array {
        return self::buildField(FieldEnum::BLOB, [], $unique, $notNull, false, $default);
    }

    /**
     * Defines a NUMERIC field for the model's table.
     *
     * @param bool $unique Whether the field must be unique (default: false).
     * @param bool $notNull Whether the field cannot be null (default: false).
     * @param bool $primaryKey Whether the field is the primary key (default: false).
     * @param int|float|null $default The default value (default: null).
     * @return array The field definition array.
     */
    public static function Numeric(
        bool $unique = false,
        bool $notNull = false,
        bool $primaryKey = false,
        int|float|null $default = null
    ): array {
        return self::buildField(FieldEnum::NUMERIC, [], $unique, $notNull, $primaryKey, $default);
    }

    /**
     * Defines a BOOLEAN field for the model's table.
     *
     * @param bool $notNull Whether the field cannot be null (default: false).
     * @param bool|null $default The default value (default: null).
     * @return array The field definition array.
     */
    public static function Boolean(
        bool $notNull = false,
        ?bool $default = null
    ): array {
        return self::buildField(FieldEnum::BOOLEAN, [], false, $notNull, false, $default);
    }

    /**
     * Defines a DATETIME field for the model's table.
     *
     * @param bool $notNull Whether the field cannot be null (default: false).
     * @param string|null $default The default value (default: null).
     * @return array The field definition array.
     */
    public static function DateTime(
        bool $notNull = false,
        ?string $default = null
    ): array {
        return self::buildField(FieldEnum::DATETIME, [], false, $notNull, false, $default);
    }

    /**
     * Defines a VARCHAR or CHAR field for the model's table.
     *
     * @param FieldEnum $type VARCHAR or CHAR.
     * @param int $length The maximum (VARCHAR) or fixed (CHAR) length (default: 255 for VARCHAR, 1 for CHAR).
     * @param bool $unique Whether the field must be unique (default: false).
     * @param bool $notNull Whether the field cannot be null (default: false).
     * @param bool $primaryKey Whether the field is the primary key (default: false).
     * @param string|null $default The default value (default: null).
     * @return array The field definition array.
     * @throws InvalidArgumentException If length is invalid.
     */
    private static function StringField(
        FieldEnum $type,
        int $length,
        bool $unique = false,
        bool $notNull = false,
        bool $primaryKey = false,
        ?string $default = null
    ): array {
        $maxLength = $type === FieldEnum::VARCHAR ? 65535 : 255;
        if ($length < 1 || $length > $maxLength) {
            throw new InvalidArgumentException("{$type->value} length must be between 1 and $maxLength.");
        }
        return self::buildField($type, ['length' => $length], $unique, $notNull, $primaryKey, $default);
    }

    /**
     * Defines a VARCHAR field for the model's table.
     *
     * @param int $length The maximum length (default: 255).
     * @param bool $unique Whether the field must be unique (default: false).
     * @param bool $notNull Whether the field cannot be null (default: false).
     * @param bool $primaryKey Whether the field is the primary key (default: false).
     * @param string|null $default The default value (default: null).
     * @return array The field definition array.
     */
    public static function Varchar(
        int $length = 255,
        bool $unique = false,
        bool $notNull = false,
        bool $primaryKey = false,
        ?string $default = null
    ): array {
        return self::StringField(FieldEnum::VARCHAR, $length, $unique, $notNull, $primaryKey, $default);
    }

    /**
     * Defines a CHAR field for the model's table.
     *
     * @param int $length The fixed length (default: 1).
     * @param bool $unique Whether the field must be unique (default: false).
     * @param bool $notNull Whether the field cannot be null (default: false).
     * @param bool $primaryKey Whether the field is the primary key (default: false).
     * @param string|null $default The default value (default: null).
     * @return array The field definition array.
     */
    public static function Char(
        int $length = 1,
        bool $unique = false,
        bool $notNull = false,
        bool $primaryKey = false,
        ?string $default = null
    ): array {
        return self::StringField(FieldEnum::CHAR, $length, $unique, $notNull, $primaryKey, $default);
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
     * @return array The field definition array.
     * @throws InvalidArgumentException If precision or scale is invalid.
     */
    public static function Decimal(
        int $precision = 10,
        int $scale = 2,
        bool $unique = false,
        bool $notNull = false,
        bool $primaryKey = false,
        ?string $default = null
    ): array {
        if ($precision < 1 || $precision > 65) {
            throw new InvalidArgumentException('DECIMAL precision must be between 1 and 65.');
        }
        if ($scale < 0 || $scale > 30 || $scale > $precision) {
            throw new InvalidArgumentException('DECIMAL scale must be between 0 and 30 and not exceed precision.');
        }
        return self::buildField(FieldEnum::DECIMAL, ['precision' => $precision, 'scale' => $scale], $unique, $notNull, $primaryKey, $default);
    }

    /**
     * Defines a FLOAT field for the model's table.
     *
     * @param bool $unique Whether the field must be unique (default: false).
     * @param bool $notNull Whether the field cannot be null (default: false).
     * @param bool $primaryKey Whether the field is the primary key (default: false).
     * @param float|null $default The default value (default: null).
     * @return array The field definition array.
     */
    public static function Float(
        bool $unique = false,
        bool $notNull = false,
        bool $primaryKey = false,
        ?float $default = null
    ): array {
        return self::buildField(FieldEnum::FLOAT, [], $unique, $notNull, $primaryKey, $default);
    }

    /**
     * Defines an ENUM field for the model's table.
     *
     * @param array $values The allowed values for the ENUM.
     * @param bool $notNull Whether the field cannot be null (default: false).
     * @param string|null $default The default value (default: null).
     * @return array The field definition array.
     * @throws InvalidArgumentException If values array is empty or contains non-string values.
     */
    public static function Enum(
        array $values,
        bool $notNull = false,
        ?string $default = null
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
        return self::buildField(FieldEnum::ENUM, ['values' => $values], false, $notNull, false, $default);
    }

    /**
     * Defines a TINYINT field for the model's table.
     *
     * @param bool $unsigned Whether the field is unsigned (default: false, MySQL only).
     * @param bool $unique Whether the field must be unique (default: false).
     * @param bool $notNull Whether the field cannot be null (default: false).
     * @param bool $primaryKey Whether the field is the primary key (default: false).
     * @param int|null $default The default value (default: null).
     * @return array The field definition array.
     * @throws InvalidArgumentException If default value is out of range.
     */
    public static function Tinyint(
        bool $unsigned = false,
        bool $unique = false,
        bool $notNull = false,
        bool $primaryKey = false,
        ?int $default = null
    ): array {
        if ($default !== null) {
            $min = $unsigned ? 0 : -128;
            $max = $unsigned ? 255 : 127;
            if ($default < $min || $default > $max) {
                throw new InvalidArgumentException("TINYINT default must be between $min and $max.");
            }
        }
        return self::buildField(FieldEnum::TINYINT, ['unsigned' => $unsigned], $unique, $notNull, $primaryKey, $default);
    }
}
