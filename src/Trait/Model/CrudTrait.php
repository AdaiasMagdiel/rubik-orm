<?php

namespace AdaiasMagdiel\Rubik\Trait\Model;

use AdaiasMagdiel\Rubik\Rubik;
use RuntimeException;

trait CrudTrait
{
    /**
     * Saves the model instance to the database.
     *
     * Performs an INSERT if the primary key is not set, or an UPDATE if it is.
     *
     * @param bool $ignore If true, uses INSERT OR IGNORE to skip duplicates (default: false).
     * @return bool True if the save was successful, false otherwise.
     */
    public function save(bool $ignore = false): bool
    {
        $fields = static::fields();
        $pk = static::primaryKey();

        if (isset($this->_data[$pk]) && !$ignore) {
            // Check if record exists
            $exists = static::find($this->_data[$pk]) !== null;
            if ($exists && $this->update()) {
                return true;
            }
        }

        $values = [];
        $columns = [];
        $placeholders = [];

        foreach ($fields as $key => $_) {
            if (array_key_exists($key, $this->_data)) {
                $columns[] = $key;
                $placeholders[] = ":$key";
                $values[":$key"] = $this->_data[$key];
            }
        }

        $sql = sprintf(
            '%s INTO %s (%s) VALUES (%s)',
            $ignore ? 'INSERT OR IGNORE' : 'INSERT',
            static::getTableName(),
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = Rubik::getConn()->prepare($sql);
        $result = $stmt->execute($values);

        if ($result && !isset($this->_data[$pk])) {
            $this->_data[$pk] = (int)Rubik::getConn()->lastInsertId();
            // Fetch the inserted record to populate defaults
            $fetched = static::find($this->_data[$pk]);
            if ($fetched) {
                $this->_data = array_merge($this->_data, $fetched->_data);
            }
        }

        $this->_dirty = [];
        return $result;
    }

    /**
     * Inserts multiple records into the model's table.
     *
     * @param array $records Array of associative arrays containing column names and values.
     * @return bool True if the insert was successful, false if no records were provided.
     */
    public static function insertMany(array $records): bool
    {
        if (empty($records)) {
            return false;
        }

        $conn = Rubik::getConn();
        $conn->beginTransaction();

        try {
            for ($i = 0; $i < count($records); $i++) {
                $record = $records[$i];
                $columns = array_keys($record);
                $placeholders = [];
                $values = [];

                foreach ($columns as $key) {
                    $placeholder = ":{$key}_{$i}";
                    $placeholders[] = $placeholder;
                    $values[$placeholder] = $record[$key];
                }

                $sql = sprintf(
                    'INSERT INTO %s (%s) VALUES (%s)',
                    static::getTableName(),
                    implode(', ', $columns),
                    implode(', ', $placeholders)
                );

                $stmt = $conn->prepare($sql);
                if (!$stmt->execute($values)) {
                    throw new RuntimeException('Insert failed');
                }
            }
            $conn->commit();
            return true;
        } catch (\Exception $e) {
            $conn->rollBack();
            return false;
        }
    }

    /**
     * Updates the model instance in the database.
     *
     * Only updates fields marked as dirty since the last save.
     *
     * @return bool True if the update was successful or no changes were needed, false otherwise.
     * @throws RuntimeException If the primary key is not set.
     */
    public function update(): bool
    {
        $pk = static::primaryKey();
        if (!isset($this->_data[$pk])) {
            throw new RuntimeException('Cannot update record without primary key.');
        }

        $values = [];
        $sets = [];

        foreach ($this->_dirty as $key => $_) {
            if ($key !== $pk && array_key_exists($key, $this->_data)) {
                $sets[] = "$key = :$key";
                $values[":$key"] = $this->_data[$key];
            }
        }

        if (empty($sets)) {
            return true;
        }

        $values[":$pk"] = $this->_data[$pk];
        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = :%s',
            static::getTableName(),
            implode(', ', $sets),
            $pk,
            $pk
        );

        $stmt = Rubik::getConn()->prepare($sql);
        $result = $stmt->execute($values);
        $this->_dirty = [];

        return $result;
    }

    /**
     * Deletes the model instance from the database.
     *
     * @return bool True if the deletion was successful, false otherwise.
     * @throws RuntimeException If the primary key is not set.
     */
    public function delete(): bool
    {
        $pk = static::primaryKey();
        if (!isset($this->_data[$pk])) {
            throw new RuntimeException('Cannot delete record without primary key.');
        }

        $sql = sprintf(
            'DELETE FROM %s WHERE %s = :%s',
            static::getTableName(),
            $pk,
            $pk
        );

        $stmt = Rubik::getConn()->prepare($sql);
        return $stmt->execute([":$pk" => $this->_data[$pk]]);
    }
}
