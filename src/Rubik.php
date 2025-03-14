<?php

namespace AdaiasMagdiel\Rubik;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use PDOException;

class Rubik
{
	private const DEFAULT_PDO_OPTIONS = [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES => false
	];

	private static ?PDO $pdo = null;

	/**
	 * Establishes a database connection
	 * 
	 * @throws RuntimeException If connection fails
	 */
	public static function connect(string $sqlitePath): void
	{
		if (empty($sqlitePath)) {
			throw new InvalidArgumentException(
				"SQLite database path cannot be empty."
			);
		}

		try {
			self::$pdo = new PDO(
				"sqlite:{$sqlitePath}",
				null,
				null,
				self::DEFAULT_PDO_OPTIONS
			);

			self::$pdo->exec('PRAGMA foreign_keys = ON;');
		} catch (PDOException $e) {
			throw new RuntimeException(
				sprintf(
					"Failed to establish database connection to '%s': %s",
					$sqlitePath,
					$e->getMessage()
				),
				$e->getCode(),
				$e
			);
		}
	}

	/**
	 * Get the active database connection
	 * 
	 * @throws RuntimeException If no active connection
	 */
	public static function getConn(): PDO
	{
		if (!self::isConnected()) {
			throw new RuntimeException(
				"Database connection not established. Call Rubik::connect() first."
			);
		}

		return self::$pdo;
	}

	/**
	 * Close the database connection
	 */
	public static function disconnect(): void
	{
		self::$pdo = null;
	}

	/**
	 * Check if connection is active
	 */
	public static function isConnected(): bool
	{
		return self::$pdo instanceof PDO;
	}
}
