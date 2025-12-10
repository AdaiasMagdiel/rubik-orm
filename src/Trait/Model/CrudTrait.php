<?php

namespace AdaiasMagdiel\Rubik\Trait\Model;

use AdaiasMagdiel\Rubik\Enum\Driver;
use AdaiasMagdiel\Rubik\Rubik;
use RuntimeException;

trait CrudTrait
{
    /**
     * Saves the model instance: INSERT or UPDATE.
     */
    public function save(bool $ignore = false): bool
    {
        $pk = static::primaryKey();
        $isNew = !isset($this->_data[$pk]);

        if (!$isNew) {
            // Existing record → UPDATE
            $result = $this->update();
            $this->_dirty = [];
            return $result;
        }

        // INSERT
        $fields = static::fields();
        $columns = [];
        $placeholders = [];
        $values = [];

        foreach ($fields as $key => $_) {
            if (array_key_exists($key, $this->_data)) {
                $columns[] = $key;
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
        if ($isNew) {
            $this->_data[$pk] = (int)Rubik::getConn()->lastInsertId();

            // Reload defaults
            $fresh = static::find($this->_data[$pk]);
            if ($fresh) {
                $this->_data = $fresh->_data;
            }
        }

        $this->_dirty = [];
        return true;
    }

    /**
     * Bulk insert with column validation.
     */
    public static function insertMany(array $records): bool
    {
        if (empty($records)) {
            return false;
        }

        $validColumns = array_keys(static::fields());
        $conn = Rubik::getConn();
        $conn->beginTransaction();

        try {
            foreach ($records as $i => $record) {

                // Validate keys
                foreach (array_keys($record) as $col) {
                    if (!in_array($col, $validColumns, true)) {
                        throw new RuntimeException("Invalid column: {$col}");
                    }
                }

                $columns = array_keys($record);
                $placeholders = [];
                $values = [];

                foreach ($columns as $key) {
                    $ph = ":{$key}_{$i}";
                    $placeholders[] = $ph;
                    $values[$ph] = $record[$key];
                }

                $sql = sprintf(
                    'INSERT INTO %s (%s) VALUES (%s)',
                    static::getTableName(),
                    implode(', ', $columns),
                    implode(', ', $placeholders)
                );

                $stmt = $conn->prepare($sql);

                if ($stmt === false) {
                    $err = $conn->errorInfo();
                    throw new RuntimeException("Failed to prepare SQL: {$sql} - Error: {$err[2]}");
                }

                $stmt->execute($values);
            }

            $conn->commit();
            return true;
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /**
     * Updates only dirty fields.
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
        return $result;
    }

    /**
     * Deletes the model instance.
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

        return $stmt->execute([$pk => $this->_data[$pk]]);
    }
}
