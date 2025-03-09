<?php

namespace AdaiasMagdiel\Rubik;

use Exception;

abstract class Model
{
	protected string $table;
	protected array $fields = [];
	protected array $data = [];

	public function __set(string $key, mixed $value)
	{
		$fields = array_keys(static::fields());

		if (in_array($key, $fields))
			$this->data[$key] = $value;
	}

	public function __get(string $key)
	{
		$fields = array_keys($this->data);

		if (in_array($key, $fields))
			return $this->data[$key];

		return NULL;
	}

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

		if ($res)
			$this->data = [];

		return $res;
	}

	public function update()
	{
		$fields = static::fields();
		$pkField = array_keys(array_filter($fields, function ($item) {
			return $item["primary_key"];
		}))[0];

		if (!isset($this->data[$pkField]) || is_null($this->data[$pkField])) {
			throw new Exception("Missing primary key.");
		}

		$sql = [];
		$sql[] = "UPDATE";
		$sql[] = self::getTableName();
		$sql[] = "SET";

		$values = [];

		$fieldsString = [];
		foreach ($fields as $key => $_) {
			$repKey = ":$key";

			if (isset($this->data[$key]) && $key !== $pkField) {
				$fieldsString[] = "$key = $repKey";
			}

			$values[$repKey] = $this->data[$key];
		}

		$sql[] = implode(", ", $fieldsString);
		$sql[] = "WHERE $pkField = :$pkField;";

		$pdo = Rubik::getConn();

		$sql = implode(" ", $sql);
		$sttm = $pdo->prepare($sql);
		$res = $sttm->execute($values);

		if ($res)
			$this->data = [];

		return $res;
	}

	public static function createTable(bool $ignore = false): bool
	{
		$fields = static::fields();

		if (empty($fields)) {
			throw new \Exception("Expected fields description.");
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

	public static function find(mixed $pk): ?static
	{
		$fields = static::fields();
		$pkField = array_keys(array_filter($fields, function ($item) {
			return $item["primary_key"];
		}))[0];

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

	protected static function fields(): array
	{
		throw new \Exception("Not Implemented 'fields()' in '" . static::class . "'.");
	}

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

	private static function escapeDefaultValue($value): string
	{
		if (is_string($value)) {
			return "'$value'";
		}
		return $value;
	}

	private static function getTableName(): string
	{
		if (isset(static::$table))
			return static::$table;

		$parts = explode("\\", static::class);
		$table = $parts[count($parts) - 1];

		return strtolower($table) . "s";
	}

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
