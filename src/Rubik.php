<?php

namespace AdaiasMagdiel\Rubik;

use Exception;
use PDO;

class Rubik
{
	protected static PDO $pdo;

	public static function connect(string $sqlitePath)
	{
		self::$pdo = new PDO("sqlite:$sqlitePath", null, null, [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
		]);
	}

	public static function getConn(): PDO
	{
		if (!isset(self::$pdo))
			throw new Exception("Connection not defined.");

		return self::$pdo;
	}
}
