<?php

namespace AdaiasMagdiel\Rubik;

use RuntimeException;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Abstract base class for database models in the Rubik ORM.
 * Provides methods for CRUD operations, relationships, and table schema management.
 * Subclasses must implement the fields() method to define the table schema.
 */
abstract class Model implements JsonSerializable
{
	/** @var string The database table name for the model. */
	protected static string $table = '';

	/** @var array Associative array of column names and their values for the model instance. */
	protected array $data = [];

	/** @var array Associative array of columns that have been modified since the last save. */
	protected array $dirty = [];

	/** @var array Associative array of cached relationship results. */
	protected array $relationships = [];

	/**
	 * Defines how the model will be serialized into JSON.
	 *
	 * @return array The data to be serialized into JSON.
	 */
	public function jsonSerialize(): array
	{
		$result = $this->data; // Includes all model data

		// Optionally, include loaded relationships
		foreach ($this->relationships as $key => $value) {
			$result[$key] = $value;
		}

		return $result;
	}

	/**
	 * Sets a value for a model field, marking it as dirty if it exists in the schema.
	 *
	 * @param string $key The field name.
	 * @param mixed $value The value to set.
	 * @return void
	 */
	public function __set(string $key, mixed $value): void
	{
		if ($this->hasField($key)) {
			$this->data[$key] = $value;
			$this->dirty[$key] = true;
		}
	}

	/**
	 * Retrieves a field value or resolves a relationship.
	 *
	 * If the key is a field, returns its value. If it is a relationship method,
	 * executes and caches the relationship results.
	 *
	 * @param string $key The field name or relationship method name.
	 * @return mixed The field value, relationship results, or null if not found.
	 */
	public function __get(string $key): mixed
	{
		if (array_key_exists($key, $this->data)) {
			return $this->data[$key];
		}

		if (method_exists($this, $key)) {
			$relationship = $this->$key();
			return $this->relationships[$key] = $relationship->getResults();
		}

		return null;
	}

	/**
	 * Creates a new query builder instance for the model.
	 *
	 * @return Query A query builder instance configured for the model.
	 */
	public static function query(): Query
	{
		return (new Query())->setModel(static::class);
	}

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

		if (isset($this->data[$pk]) && !$ignore) {
			return $this->update();
		}

		$values = [];
		$columns = [];
		$placeholders = [];

		foreach ($fields as $key => $_) {
			if (array_key_exists($key, $this->data)) {
				$columns[] = $key;
				$placeholders[] = ":$key";
				$values[":$key"] = $this->data[$key];
			}
		}

		$sql = sprintf(
			'%s INTO %s (%s) VALUES (%s)',
			$ignore ? 'INSERT OR IGNORE' : 'INSERT',
			static::getTableName(),
			implode(', ', $columns),
			implode(', ', $placeholders)
		);

		$stmt = DatabaseConnection::getConnection()->prepare($sql);
		$result = $stmt->execute($values);

		if ($result && !isset($this->data[$pk])) {
			$this->data[$pk] = DatabaseConnection::getConnection()->lastInsertId();
		}

		$this->dirty = [];
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

		$fields = static::fields();
		$columns = array_keys($fields);
		$values = [];
		$placeholders = [];

		foreach ($records as $index => $record) {
			$rowPlaceholders = [];
			foreach ($columns as $key) {
				$placeholder = ":{$key}_{$index}";
				$rowPlaceholders[] = $placeholder;
				$values[$placeholder] = $record[$key] ?? null;
			}
			$placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
		}

		$sql = sprintf(
			'INSERT INTO %s (%s) VALUES %s',
			static::getTableName(),
			implode(', ', $columns),
			implode(', ', $placeholders)
		);

		$stmt = DatabaseConnection::getConnection()->prepare($sql);
		return $stmt->execute($values);
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
		if (!isset($this->data[$pk])) {
			throw new RuntimeException('Cannot update record without primary key.');
		}

		$values = [];
		$sets = [];

		foreach ($this->dirty as $key => $_) {
			if ($key !== $pk && array_key_exists($key, $this->data)) {
				$sets[] = "$key = :$key";
				$values[":$key"] = $this->data[$key];
			}
		}

		if (empty($sets)) {
			return true;
		}

		$values[":$pk"] = $this->data[$pk];
		$sql = sprintf(
			'UPDATE %s SET %s WHERE %s = :%s',
			static::getTableName(),
			implode(', ', $sets),
			$pk,
			$pk
		);

		$stmt = DatabaseConnection::getConnection()->prepare($sql);
		$result = $stmt->execute($values);
		$this->dirty = [];

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
		if (!isset($this->data[$pk])) {
			throw new RuntimeException('Cannot delete record without primary key.');
		}

		$sql = sprintf(
			'DELETE FROM %s WHERE %s = :%s',
			static::getTableName(),
			$pk,
			$pk
		);

		$stmt = DatabaseConnection::getConnection()->prepare($sql);
		return $stmt->execute([":$pk" => $this->data[$pk]]);
	}

	/**
	 * Retrieves all records from the model's table.
	 *
	 * @param array|string $fields The fields to select (default: '*').
	 * @return array Array of model instances or associative arrays.
	 */
	public static function all(array|string $fields = '*'): array
	{
		return static::query()->select($fields)->all();
	}

	/**
	 * Finds a record by its primary key.
	 *
	 * @param mixed $pk The primary key value.
	 * @return static|null The model instance, or null if not found.
	 */
	public static function find(mixed $pk): ?static
	{
		return static::query()
			->where(static::primaryKey(), $pk)
			->first();
	}

	/**
	 * Finds the first record matching a field value.
	 *
	 * @param string $key The field name to filter on.
	 * @param mixed $value The value to match.
	 * @param string $op The comparison operator (default: '=').
	 * @return static|null The model instance, or null if not found.
	 */
	public static function findOneBy(string $key, mixed $value, string $op = '='): ?static
	{
		return static::query()
			->where($key, $value, $op)
			->first();
	}

	/**
	 * Finds all records matching a field value.
	 *
	 * @param string $key The field name to filter on.
	 * @param mixed $value The value to match.
	 * @param string $op The comparison operator (default: '=').
	 * @return array Array of model instances.
	 */
	public static function findAllBy(string $key, mixed $value, string $op = '='): array
	{
		return static::query()
			->where($key, $value, $op)
			->all();
	}

	/**
	 * Paginates the model's query results.
	 *
	 * @param int $page The page number (1-based).
	 * @param int $perPage The number of items per page.
	 * @param array|string $fields The fields to select (default: '*').
	 * @return \stdClass An object containing data, current_page, per_page, total, and last_page.
	 * @throws InvalidArgumentException If page or perPage is less than 1.
	 */
	public static function paginate(int $page, int $perPage, array|string $fields = '*'): \stdClass
	{
		$result = static::query()->select($fields)->paginate($page, $perPage);
		$pagination = new \stdClass();
		$pagination->data = $result['data'];
		$pagination->current_page = $result['current_page'];
		$pagination->per_page = $result['per_page'];
		$pagination->total = $result['total'];
		$pagination->last_page = $result['last_page'];
		return $pagination;
	}

	/**
	 * Creates the database table for the model based on its field definitions.
	 *
	 * @param bool $ifNotExists If true, adds IF NOT EXISTS to the CREATE TABLE statement (default: false).
	 * @return bool True if the table was created successfully, false otherwise.
	 * @throws RuntimeException If no fields are defined.
	 */
	public static function createTable(bool $ifNotExists = false): bool
	{
		$fields = static::fields();
		if (empty($fields)) {
			throw new RuntimeException('No fields defined for table creation.');
		}

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

		return DatabaseConnection::getConnection()->exec($sql) !== false;
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

		return DatabaseConnection::getConnection()->exec($sql) !== false;
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

		return DatabaseConnection::getConnection()->exec($sql) !== false;
	}

	/**
	 * Defines a belongsTo relationship with another model.
	 *
	 * @param string $related The fully qualified class name of the related model.
	 * @param string $foreignKey The foreign key column in the current model's table.
	 * @return Relationship The relationship instance.
	 */
	public function belongsTo(string $related, string $foreignKey): Relationship
	{
		return new Relationship('belongsTo', static::class, $related, $foreignKey, $this);
	}

	/**
	 * Defines a hasMany relationship with another model.
	 *
	 * @param string $related The fully qualified class name of the related model.
	 * @param string $foreignKey The foreign key column in the related model's table.
	 * @return Relationship The relationship instance.
	 */
	public function hasMany(string $related, string $foreignKey): Relationship
	{
		return new Relationship('hasMany', static::class, $related, $foreignKey, $this);
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
	 * Generates the SQL field definition string for a field.
	 *
	 * @param array $field The field properties (type, primary_key, autoincrement, etc.).
	 * @return string The SQL field definition.
	 */
	protected static function getFieldString(array $field): string
	{
		$parts = [
			strtoupper($field['type']->value),
			$field['primary_key'] ?? false ? 'PRIMARY KEY' : '',
			$field['autoincrement'] ?? false ? (
				DatabaseConnection::getDriver() === 'mysql' ? 'AUTO_INCREMENT' : 'AUTOINCREMENT'
			) : '',
			$field['unique'] ?? false ? 'UNIQUE' : '',
			$field['not_null'] ?? false ? 'NOT NULL' : '',
			isset($field['default']) ? sprintf(
				'DEFAULT %s',
				self::escapeDefaultValue($field['default'])
			) : '',
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
		if ($value instanceof RawSQL) {
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
		return [
			'type' => FieldEnum::INTEGER,
			'autoincrement' => $autoincrement,
			'primary_key' => $primaryKey,
			'unique' => $unique,
			'not_null' => $notNull,
			'default' => $default,
		];
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
		return [
			'type' => FieldEnum::TEXT,
			'unique' => $unique,
			'not_null' => $notNull,
			'primary_key' => $primaryKey,
			'default' => $default,
		];
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
		return [
			'type' => FieldEnum::REAL,
			'unique' => $unique,
			'not_null' => $notNull,
			'primary_key' => $primaryKey,
			'default' => $default,
		];
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
		return [
			'type' => FieldEnum::BLOB,
			'unique' => $unique,
			'not_null' => $notNull,
			'default' => $default,
		];
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
		return [
			'type' => FieldEnum::NUMERIC,
			'unique' => $unique,
			'not_null' => $notNull,
			'primary_key' => $primaryKey,
			'default' => $default,
		];
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
		return [
			'type' => FieldEnum::BOOLEAN,
			'not_null' => $notNull,
			'default' => $default,
		];
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
		return [
			'type' => FieldEnum::DATETIME,
			'not_null' => $notNull,
			'default' => $default,
		];
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
