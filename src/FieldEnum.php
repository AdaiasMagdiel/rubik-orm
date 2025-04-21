<?php

namespace AdaiasMagdiel\Rubik;

/**
 * Defines SQLite field data types as an enumeration for use in the Rubik ORM.
 *
 * This enum provides a set of constants representing the supported SQLite data types
 * used in the Model class for defining table schemas. Each case corresponds to a valid
 * SQLite data type and is used in methods like Model::getFieldString() to generate
 * SQL field definitions.
 *
 * @package AdaiasMagdiel\Rubik
 * @see Model::getFieldString() For usage in generating SQL field definitions.
 */
enum FieldEnum: string
{
/**
     * SQLite INTEGER data type for storing whole numbers.
     *
     * Commonly used for primary keys or numeric identifiers.
     *
     * @var string
     */
    case INTEGER = 'INTEGER';

/**
     * SQLite TEXT data type for storing character data.
     *
     * Suitable for strings, such as names or descriptions.
     *
     * @var string
     */
    case TEXT = 'TEXT';

/**
     * SQLite REAL data type for storing floating-point numbers.
     *
     * Used for decimal values, such as prices or measurements.
     *
     * @var string
     */
    case REAL = 'REAL';

/**
     * SQLite BLOB data type for storing binary data.
     *
     * Ideal for images, files, or other binary objects.
     *
     * @var string
     */
    case BLOB = 'BLOB';

/**
     * SQLite NUMERIC data type for storing numeric values.
     *
     * Supports both integers and decimals, depending on input.
     *
     * @var string
     */
    case NUMERIC = 'NUMERIC';

/**
     * SQLite BOOLEAN data type, stored as 0 or 1.
     *
     * Used for true/false values in the database.
     *
     * @var string
     */
    case BOOLEAN = 'BOOLEAN';

/**
     * SQLite DATETIME data type for storing date and time values.
     *
     * Typically used for timestamps or date fields.
     *
     * @var string
     */
    case DATETIME = 'DATETIME';
}
