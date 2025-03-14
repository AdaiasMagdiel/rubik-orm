<?php

namespace AdaiasMagdiel\Rubik;

use Exception;

/**
 * Abstract base class for ActiveRecord-style database entities
 * 
 * Provides CRUD operations, schema management and query building capabilities
 */
abstract class Model
{
	protected static string $table;
	protected array $fields = [];
	protected array $data = [];

	/**
	 * Magic setter for model properties
	 * 
	 * @param string $key Property name
	 * @param mixed $value Property value
	 * @throws RuntimeException If trying to set undefined field
	 */
	public function __set(string $key, mixed $value)
	{
		$fields = static::fields();

		if (isset($fields[$key])) {
			$this->data[$key] = $value;
		}
	}

	/**
	 * Magic getter for model properties
	 * 
	 * @param string $key Property name
	 * @return mixed|null Property value or null if not set
	 */
	public function __get(string $key)
	{
		return $this->data[$key] ?? null;
	}

	/**
	 * Persist the model to the database
	 * 
	 * @param bool $ignore Use INSERT OR IGNORE clause
	 * @return bool True on successful save
	 * @throws RuntimeException If database operation fails
	 */
	public function save(bool $ignore = false): bool
	{
		$fields = static::fields();

		$sql = [];

		if ($ignore)
			$sql[] = "INSERT OR IGNORE INTO";
		else
			$sql[] = "INSERT INTO";

		$sql[] = self::getTableName();

		$values = [];

		$keysString = [];
		$valuesString = [];
		foreach ($fields as $key => $_) {
			$repKey = ":$key";

			$keysString[] = $key;
			$valuesString[] = $repKey;

			$values[$repKey] = $this->data[$key] ?? null;
		}
		$sql[] = "(" . implode(", ", $keysString) . ")";
		$sql[] = "VALUES";
		$sql[] = "(" . implode(", ", $valuesString) . ");";

		$pdo = Rubik::getConn();

		$sql = implode(" ", $sql);
		$sttm = $pdo->prepare($sql);
		$res = $sttm->execute($values);

		if ($res) {
			$pk = $this::primaryKey();
			$this->__set($pk, Rubik::getConn()->lastInsertId($pk));
		}

		return $res;
	}

	/**
	 * Update existing record in the database
	 * 
	 * @return bool True on successful update
	 * @throws RuntimeException If primary key is missing or update fails
	 */
	public function update(): bool
	{
		$fields = static::fields();
		$pkField = self::primaryKey();

		if (!isset($this->data[$pkField])) {
			throw new Exception("Primary key value is missing. Ensure the primary key is set before updating.");
		}

		$sql = ["UPDATE", self::getTableName(), "SET"];
		$values = [];
		$fieldsString = [];
		$pkValue = $this->data[$pkField];

		foreach ($fields as $key => $_) {
			if ($key === $pkField) continue;

			if (isset($this->data[$key])) {
				$repKey = ":$key";
				$fieldsString[] = "$key = $repKey";
				$values[$repKey] = $this->data[$key];
			}
		}

		$sql[] = implode(", ", $fieldsString);
		$sql[] = "WHERE $pkField = :$pkField";
		$values[":$pkField"] = $pkValue;

		$sttm = Rubik::getConn()->prepare(implode(" ", $sql));
		$res = $sttm->execute($values);

		return $res;
	}

	/**
	 * Delete record from the database
	 * 
	 * @return bool True on successful deletion
	 * @throws RuntimeException If primary key is missing or deletion fails
	 */
	public function delete(): bool
	{
		$pkField = self::primaryKey();

		if (!isset($this->data[$pkField])) {
			throw new Exception("Primary key value is missing. Ensure the primary key is set before deleting.");
		}

		$sql = [
			"DELETE FROM",
			self::getTableName(),
			"WHERE",
			$pkField,
			"=",
			":$pkField"
		];

		$sttm = Rubik::getConn()->prepare(implode(" ", $sql));
		$res = $sttm->execute([":$pkField" => $this->data[$pkField]]);

		return $res;
	}

	/**
	 * Get a query builder instance for this model
	 * 
	 * @return Query Configured query builder
	 */
	public static function query(): Query
	{

		$query = new Query();
		$query->setModel(static::class);

		return $query;
	}


	/**
	 * Get all records from the database
	 * 
	 * @param array|string $fields Fields to select (default: all fields)
	 * @return array<static> Array of model instances
	 * @throws RuntimeException If query fails
	 */
	public static function all(array|string $fields = "*"): array
	{
		if (is_string($fields)) {
			$fields = [$fields];
		}

		$pkField = self::primaryKey();
		$fields = array_unique(array_merge([$pkField], $fields));

		$sql = [
			"SELECT",
			implode(", ", $fields),
			"FROM",
			self::getTableName()
		];

		$sttm = Rubik::getConn()->prepare(implode(" ", $sql));
		$sttm->execute();
		$res = $sttm->fetchAll();

		if ($res === false) return [];

		return array_map(function ($item) {
			$model = new static();
			foreach ($item as $key => $value) {
				$model->__set($key, $value);
			}
			return $model;
		}, $res ?: []);
	}

	/**
	 * Get the primary key field name
	 * 
	 * @return string Primary key field name
	 * @throws RuntimeException If no primary key defined
	 */
	public static function primaryKey(): string
	{
		$fields = static::fields();
		$pkField = array_keys(array_filter($fields, function ($item) {
			return $item["primary_key"];
		}));

		if (count($pkField) === 0)
			throw new Exception("No primary key field was found in the fields definition. Please ensure at least one field is marked as a primary key.");

		return $pkField[0];
	}

	/**
	 * Get table name for the model
	 * 
	 * Auto-generated if not explicitly defined:
	 * - Uses class basename
	 * - Converts to lowercase
	 * - Appends 's' (e.g., 'User' → 'users')
	 * 
	 * @return string Database table name
	 */
	public static function getTableName(): string
	{
		if (isset(static::$table))
			return static::$table;

		$parts = explode("\\", static::class);
		$table = $parts[count($parts) - 1];

		return strtolower($table) . "s";
	}

	/**
	 * Create database table for the model
	 * 
	 * @param bool $ignore Use CREATE TABLE IF NOT EXISTS
	 * @return bool True if table was created successfully
	 * @throws RuntimeException If field definitions are invalid
	 */
	public static function createTable(bool $ignore = false): bool
	{
		$fields = static::fields();

		if (empty($fields)) {
			throw new Exception("The fields description is empty or not defined. Expected a non-empty array of field definitions.");
		}

		$sql = ["CREATE TABLE"];
		if ($ignore)
			$sql[] = "IF NOT EXISTS";

		$sql[] = self::getTableName();

		$fieldsString = [];
		foreach ($fields as $key => $field) {
			$fieldsString[] = $key . " " . self::getFieldString($field);
		}
		$sql[] =  "(" . implode(", ", $fieldsString) . ");";

		$sttm = implode(" ", $sql);

		$pdo = Rubik::getConn();
		$res = $pdo->exec($sttm);

		return is_int($res);
	}

	/**
	 * Find record by primary key
	 * 
	 * @param mixed $pk Primary key value
	 * @return static|null Model instance or null if not found
	 * @throws RuntimeException If query fails
	 */
	public static function find(mixed $pk): ?static
	{
		$pkField = static::primaryKey();

		$sql = [];

		$sql[] = "SELECT * FROM";
		$sql[] = self::getTableName();
		$sql[] = "WHERE $pkField = :$pkField;";
		$sql = implode(" ", $sql);

		$pdo = Rubik::getConn();
		$sttm = $pdo->prepare($sql);
		$sttm->execute([":$pkField" => $pk]);

		$res = $sttm->fetch();

		if ($res === false) return NULL;

		$obj = new static();
		foreach ($res as $key => $value) {
			$obj->__set($key, $value);
		}

		return $obj;
	}

	/**
	 * Find single record by field value
	 * 
	 * @param string $key Field name
	 * @param mixed $value Search value
	 * @param string $op Comparison operator (=, <>, LIKE, etc)
	 * @return static|null Model instance or null if not found
	 * @throws RuntimeException If field doesn't exist or query fails
	 */
	public static function findOneBy(
		string $key,
		mixed $value,
		string $op = "="
	): ?static {
		$sql = sprintf(
			"SELECT * FROM %s WHERE %s %s :%s;",
			self::getTableName(),
			$key,
			$op,
			$key
		);

		$sttm = Rubik::getConn()->prepare($sql);
		$sttm->execute([":$key" => $value]);

		$result = $sttm->fetch();
		if (!$result) return NULL;

		$model = new static();
		foreach ($result as $key => $value) {
			$model->__set($key, $value);
		}
		return $model;
	}

	/**
	 * Find multiple records by field value
	 * 
	 * @param string $key Field name
	 * @param mixed $value Search value
	 * @param string $op Comparison operator (=, <>, LIKE, etc)
	 * @return array<static> Array of model instances
	 * @throws RuntimeException If field doesn't exist or query fails
	 */
	public static function findAllBy(
		string $key,
		mixed $value,
		string $op = "="
	): array {
		$sql = sprintf(
			"SELECT * FROM %s WHERE %s %s :%s;",
			self::getTableName(),
			$key,
			$op,
			$key
		);

		$sttm = Rubik::getConn()->prepare($sql);
		$sttm->execute([":$key" => $value]);

		$results = $sttm->fetchAll();
		return array_map(function ($result) {
			$model = new static();
			foreach ($result as $key => $value) {
				$model->__set($key, $value);
			}
			return $model;
		}, $results ?: []);
	}

	/**
	 * Define the model's database schema
	 * 
	 * @return array Field definitions using field helper methods
	 * @throws RuntimeException Must be implemented in child classes
	 */
	protected static function fields(): array
	{
		throw new Exception("The 'fields()' method is not implemented in class '" . static::class . "'. Please provide an implementation to define the fields.");
	}

	/**
	 * Generate SQL column definition from field attributes
	 * 
	 * @param array $field Field definition array
	 * @return string SQL column definition
	 */
	private static function getFieldString(array $field): string
	{
		$sttm = [];

		$sqlCommands = [
			'type' => fn($value) => strtoupper($value->name),
			'primary_key' => fn($value) => $value ? 'PRIMARY KEY' : '',
			'autoincrement' => fn($value) => $value ? 'AUTOINCREMENT' : '',
			'unique' => fn($value) => $value ? 'UNIQUE' : '',
			'not_null' => fn($value) => $value ? 'NOT NULL' : '',
			'default' => fn($value) => !is_null($value) ? 'DEFAULT ' . self::escapeDefaultValue($value) : '',
		];

		foreach ($sqlCommands as $key => $callback) {
			if (isset($field[$key])) {
				$result = $callback($field[$key]);

				if (!empty($result)) {
					$sttm[] = $result;
				}
			}
		}

		return implode(" ", $sttm);
	}

	/**
	 * Sanitize default values for SQL statements
	 * 
	 * @param mixed $value Default value
	 * @return string Sanitized SQL value
	 */
	private static function escapeDefaultValue($value): string
	{
		if (is_string($value)) {
			return "'$value'";
		}
		return $value;
	}

	/**
	 * Define an integer field
	 * 
	 * @param bool $autoincrement Auto-incrementing field
	 * @param bool $pk Primary key
	 * @param bool $unique Unique constraint
	 * @param bool $notNull Not null constraint
	 * @param int|null $default Default value
	 * @return array Field definition array
	 */
	final public static function Int(
		bool $autoincrement = false,
		bool $pk = false,
		bool $unique = false,
		bool $notNull = false,
		int $default = null
	): array {
		return [
			'type' => FieldEnum::INTEGER,
			'autoincrement' => $autoincrement,
			'primary_key' => $pk,
			'unique' => $unique,
			'not_null' => $notNull,
			'default' => $default,
		];
	}

	/**
	 * Define a text field
	 * 
	 * @param bool $pk Primary key
	 * @param bool $unique Unique constraint
	 * @param bool $notNull Not null constraint
	 * @param string $default Default value
	 * @return array Field definition array
	 */
	final public static function Text(
		bool $unique = false,
		bool $notNull = false,
		bool $pk = false,
		string $default = null
	): array {
		return [
			'type' => FieldEnum::TEXT,
			'unique' => $unique,
			'not_null' => $notNull,
			'primary_key' => $pk,
			'default' => $default
		];
	}

	/**
	 * Define a real number field for floating-point values
	 * 
	 * @param bool $pk Primary key
	 * @param bool $unique Unique constraint
	 * @param bool $notNull Not null constraint
	 * @param float|null $default Default numeric value
	 * @return array Field definition array
	 */
	final public static function Real(
		bool $unique = false,
		bool $notNull = false,
		bool $pk = false,
		float $default = null
	): array {
		return [
			'type' => FieldEnum::REAL,
			'unique' => $unique,
			'not_null' => $notNull,
			'primary_key' => $pk,
			'default' => $default
		];
	}

	/**
	 * Define a BLOB field for binary data storage
	 * 
	 * @param bool $unique Enforce unique constraint
	 * @param bool $notNull Disallow NULL values
	 * @param mixed $default Default binary value
	 * @return array<string,mixed> Structured field definition
	 */
	final public static function Blob(
		bool $unique = false,
		bool $notNull = false,
		$default = null
	): array {
		return [
			'type' => FieldEnum::BLOB,
			'unique' => $unique,
			'not_null' => $notNull,
			'default' => $default
		];
	}

	/**
	 * Define a numeric field for exact precision numbers
	 * 
	 * @param bool $unique Enforce unique constraint
	 * @param bool $notNull Disallow NULL values
	 * @param bool $pk Designate as primary key
	 * @param int|float|null $default Default numeric value
	 * @return array<string,mixed> Structured field definition
	 */
	final public static function Numeric(
		bool $unique = false,
		bool $notNull = false,
		bool $pk = false,
		int|float $default = null
	): array {
		return [
			'type' => FieldEnum::NUMERIC,
			'unique' => $unique,
			'not_null' => $notNull,
			'primary_key' => $pk,
			'default' => $default
		];
	}
}
