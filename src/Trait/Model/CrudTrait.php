<?php

namespace AdaiasMagdiel\Rubik\Trait\Model;

use AdaiasMagdiel\Rubik\Enum\Driver;
use AdaiasMagdiel\Rubik\Rubik;
use RuntimeException;
use Throwable;

trait CrudTrait
{
    /**
     * Persists the model instance to the database.
     * 
     * Performs either an INSERT or an UPDATE based on the `exists` property.
     * Handles lifecycle hooks (beforeSave, beforeCreate/Update, etc.), manages
     * dirty states, and populates the primary key after insertion if applicable.
     *
     * @param bool $ignore If true, uses 'INSERT OR IGNORE' (SQLite) or 'INSERT IGNORE' (MySQL)
     *                     to silently bypass duplicate key errors during insertion.
     * @return bool True if the operation was successful, false otherwise.
     */
    public function save(bool $ignore = false): bool
    {
        $this->beforeSave();

        if ($this->exists) {
            $this->beforeUpdate();

            $result = $this->update();

            if ($result) {
                $this->_dirty = [];
                $this->afterUpdate();
                $this->afterSave();
            }

            return $result;
        }

        // INSERT
        $this->beforeCreate();

        $pk = static::primaryKey();
        $fields = static::fields();
        $columns = [];
        $placeholders = [];
        $values = [];

        foreach ($fields as $key => $_) {
            if (array_key_exists($key, $this->_data)) {
                $columns[] = Rubik::quoteIdentifier($key);
                $placeholders[] = ":$key";
                $values[":$key"] = $this->_data[$key];
            }
        }

        // Driver-specific IGNORE
        $insertKeyword = match (Rubik::getDriver()) {
            Driver::SQLITE  => $ignore ? 'INSERT OR IGNORE' : 'INSERT',
            Driver::MYSQL   => $ignore ? 'INSERT IGNORE'    : 'INSERT',
            // Driver::PGSQL   => 'INSERT', // soon
            default   => 'INSERT'
        };

        $sql = sprintf(
            '%s INTO %s (%s) VALUES (%s)',
            $insertKeyword,
            static::getTableName(),
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        // PostgreSQL conflict handling - soon
        // if ($ignore && Rubik::getDriver() === Driver::PGSQL) {
        //     $sql .= ' ON CONFLICT DO NOTHING';
        // }

        $stmt = Rubik::getConn()->prepare($sql);
        $result = $stmt->execute($values);

        if (!$result) {
            return false;
        }

        // Retrieve new PK
        if (!isset($this->_data[$pk])) {
            $lastId = Rubik::getConn()->lastInsertId();

            if ($lastId) {
                $this->_data[$pk] = (int)$lastId;
            }

            // Reload defaults
            $fresh = static::find($this->_data[$pk]);
            if ($fresh) {
                $this->_data = $fresh->_data;
            }
        }

        $this->exists = true;
        $this->_dirty = [];

        $this->afterCreate();
        $this->afterSave();

        return true;
    }

    /**
     * Inserts multiple records into the database in a single batch query.
     * 
     * Validates that all keys in the records match the model's defined fields
     * and delegates the execution to the Query Builder for performance.
     *
     * @param array<int, array<string, mixed>> $records An array of associative arrays representing records.
     * @return bool True if records were inserted successfully.
     * 
     * @throws RuntimeException If an invalid column name is found.
     */
    public static function insertMany(array $records): bool
    {
        if (empty($records)) {
            return false;
        }

        $validColumns = array_keys(static::fields());

        foreach ($records as $record) {
            foreach (array_keys($record) as $col) {
                if (!in_array($col, $validColumns, true)) {
                    throw new RuntimeException("Invalid column: {$col}");
                }
            }
        }

        static::query()->insert($records);

        return true;
    }

    /**
     * Updates the existing model in the database.
     * 
     * Only updates fields that have been modified (dirty fields).
     * Requires the primary key to be set on the model.
     *
     * @return bool True if the update was successful (or no fields were dirty), false otherwise.
     * @throws RuntimeException If the model does not have a primary key set.
     */
    public function update(): bool
    {
        $pk = static::primaryKey();
        if (!isset($this->_data[$pk])) {
            throw new RuntimeException('Cannot update record without primary key.');
        }

        if (empty($this->_dirty)) {
            return true;
        }

        $this->beforeUpdate();

        $sets = [];
        $values = [];

        foreach ($this->_dirty as $key => $_) {
            if ($key !== $pk) {
                $sets[] = "$key = :$key";
                $values[":$key"] = $this->_data[$key];
            }
        }

        $values[":pk"] = $this->_data[$pk];

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = :pk',
            static::getTableName(),
            implode(', ', $sets),
            $pk
        );

        $stmt = Rubik::getConn()->prepare($sql);
        $result = $stmt->execute($values);

        $this->_dirty = [];
        $this->afterUpdate();

        return $result;
    }

    /**
     * Removes the model instance from the database.
     * 
     * Requires the primary key to be set. Upon success, the model's
     * `exists` property is set to false.
     *
     * @return bool True if the record was successfully deleted.
     * @throws RuntimeException If the model does not have a primary key set.
     */
    public function delete(): bool
    {
        $pk = static::primaryKey();
        if (!isset($this->_data[$pk])) {
            throw new RuntimeException('Cannot delete record without primary key.');
        }

        $this->beforeDelete();

        $sql = sprintf(
            'DELETE FROM %s WHERE %s = :%s',
            static::getTableName(),
            $pk,
            $pk
        );

        $stmt = Rubik::getConn()->prepare($sql);

        $result = $stmt->execute([$pk => $this->_data[$pk]]);

        if ($result) {
            $this->afterDelete();
            $this->exists = false;
        }

        return $result;
    }
}
