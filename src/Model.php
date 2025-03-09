<?php

namespace AdaiasMagdiel\Rubik;

abstract class Model
{
	protected string $table;
	protected array $fields = [];

	public static function createTable(bool $ignore = false)
	{
		$fields = static::fields();

		if (empty($fields)) {
			throw new \Exception("Expected fields description.");
		}

		$sql = ["CREATE TABLE"];
		if ($ignore)
			$sql[] = "IF NOT EXISTS";

		$sql[] = isset(static::$table) ? static::$table : self::getTableName();

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

	protected static function fields(): array
	{
		throw new \Exception("Not Implemented.");
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
