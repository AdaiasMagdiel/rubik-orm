<?php

namespace AdaiasMagdiel\Rubik\Enum;

/**
 * Defines SQLite field data types as an enumeration for use in the Rubik ORM.
 *
 * This enum provides a set of constants representing the supported SQLite data types
 * used in the Model class for defining table schemas. Each case corresponds to a valid
 * SQLite data type and is used in methods like Model::getFieldString() to generate
 * SQL field definitions.
 *
 * @package AdaiasMagdiel\Rubik\Enum
 * @see Model::getFieldString() For usage in generating SQL field definitions.
 */
enum Field: string
{
     case INTEGER = 'INTEGER';
     case TEXT = 'TEXT';
     case REAL = 'REAL';
     case BLOB = 'BLOB';
     case NUMERIC = 'NUMERIC';
     case BOOLEAN = 'BOOLEAN';
     case DATETIME = 'DATETIME';
     case VARCHAR = 'VARCHAR';
     case CHAR = 'CHAR';
     case DECIMAL = 'DECIMAL';
     case FLOAT = 'FLOAT';
     case ENUM = 'ENUM';
     case TINYINT = 'TINYINT';
}
