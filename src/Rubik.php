<?php

namespace AdaiasMagdiel\Rubik;

use AdaiasMagdiel\Rubik\Enum\Driver;
use PDO;
use PDOException;
use RuntimeException;
use InvalidArgumentException;
use Throwable;

/**
 * Manages database connections for the Rubik ORM using PDO.
 *
 * This class provides a singleton-like interface for establishing, retrieving, and closing
 * PDO database connections. It supports SQLite and MySQL/MariaDB drivers, with configurable
 * connection options and validation for driver-specific settings.
 *
 * @package AdaiasMagdiel\Rubik
 */
class Rubik
{
    /**
     * Default PDO options for consistent connection behavior.
     *
     * Configures error handling, fetch mode, prepared statement emulation, and stringification.
     *
     * @var array<string, mixed>
     */
    private const DEFAULT_PDO_OPTIONS = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ];

    /**
     * The active PDO connection instance.
     *
     * @var PDO|null
     */
    private static ?PDO $pdo = null;

    /**
     * The database driver in use (e.g., Driver::SQLITE, Driver::MYSQL).
     *
     * @var Driver|null
     */
    private static Driver|null $driver = null;

    /**
     * Establishes a new PDO database connection.
     *
     * Configures the connection based on the provided configuration array, which must include
     * the driver and driver-specific settings (e.g., path for SQLite, host and database for MySQL).
     * Enables foreign key support for SQLite connections.
     *
     * @param Driver $driver The database driver enum (SQLITE or MYSQL).
     * @param string $username Database username (optional).
     * @param string $password Database password (optional).
     * @param string $database Database name (for MySQL).
     * @param int $port Database port (default: 3306).
     * @param string $host Database host (default: localhost).
     * @param string $charset Database charset (default: utf8mb4).
     * @param string $path SQLite database path (default: :memory:).
     * @param array $options Additional PDO options.
     * 
     * @return void
     * @throws InvalidArgumentException If the driver is missing, unsupported, or configuration is invalid.
     * @throws RuntimeException If the connection fails due to a PDOException.
     */
    public static function connect(
        Driver $driver,
        string $username = '',
        string $password = '',
        string $database = '',
        int $port = 3306,
        string $host = 'localhost',
        string $charset = 'utf8mb4',
        string $path = ":memory:",
        array $options = []
    ): void {
        self::$driver = $driver;

        // Validate SQLite path
        if (self::$driver === Driver::SQLITE && empty($path)) {
            throw new InvalidArgumentException('SQLite database path cannot be empty.');
        }

        try {
            $dsn = self::buildDsn([
                "database" => $database,
                "port" => $port,
                "charset" => $charset,
                "host" => $host,
                "path" => $path
            ]);
            $options = array_merge(self::DEFAULT_PDO_OPTIONS, $options);

            self::$pdo = new PDO(
                $dsn,
                $username ?? null,
                $password ?? null,
                $options
            );

            if (self::$driver === Driver::SQLITE) {
                self::$pdo->exec('PRAGMA foreign_keys = ON;');
            }
        } catch (PDOException $e) {
            throw new RuntimeException(
                sprintf('Failed to connect to database: %s', $e->getMessage()),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Retrieves the active PDO connection.
     *
     * @return PDO The active PDO connection instance.
     * @throws RuntimeException If no connection is active (connect() has not been called).
     */
    public static function getConn(): PDO
    {
        if (!self::isConnected()) {
            throw new RuntimeException(
                'No active database connection. Call DatabaseConnection::connect() first.'
            );
        }

        return self::$pdo;
    }

    /**
     * Closes the active database connection.
     *
     * Resets the PDO instance and driver, effectively disconnecting from the database.
     *
     * @return void
     */
    public static function disconnect(): void
    {
        self::$pdo = null;
        self::$driver = null;
    }

    /**
     * Checks if a database connection is active.
     *
     * @return bool True if a PDO connection is active, false otherwise.
     */
    public static function isConnected(): bool
    {
        return self::$pdo instanceof PDO;
    }

    /**
     * Retrieves the database driver in use.
     *
     * @return Driver The current driver (e.g., Driver::SQLITE or DRIVER::MYSQL).
     */
    public static function getDriver(): Driver
    {
        return self::$driver;
    }

    /**
     * Forcefully sets the current database driver.
     *
     * ⚠️ Intended for testing or internal debugging only.
     * This method does not establish or validate a database connection;
     * it simply overrides the internal static driver reference used
     * for driver-specific logic (e.g., SQL syntax differences).
     *
     * Example:
     * ```php
     * Rubik::setDriver(Driver::MYSQL);
     * ```
     *
     * @param \AdaiasMagdiel\Rubik\Enum\Driver $driver
     *        The driver to set manually (e.g., Driver::MYSQL, Driver::SQLITE).
     *
     * @return void
     */
    public static function setDriver(\AdaiasMagdiel\Rubik\Enum\Driver $driver): void
    {
        self::$driver = $driver;
    }

    /**
     * Builds the Data Source Name (DSN) string for the PDO connection.
     *
     * Constructs the DSN based on the driver and configuration settings, supporting
     * SQLite and MySQL/MariaDB drivers.
     *
     * @param array<string, mixed> $config The database configuration array.
     * @return string The constructed DSN string.
     * @throws InvalidArgumentException If the driver is unsupported.
     */
    private static function buildDsn(array $config): string
    {
        if (!self::$driver instanceof Driver) {
            throw new InvalidArgumentException('No valid database driver is set.');
        }

        return match (self::$driver) {
            Driver::SQLITE => sprintf('sqlite:%s', $config['path'] ?? ''),
            Driver::MYSQL => sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'] ?? 'localhost',
                $config['port'] ?? 3306,
                $config['database'] ?? '',
                $config['charset'] ?? 'utf8mb4'
            ),
            default => throw new InvalidArgumentException(
                sprintf('Unsupported database driver: %s', self::$driver->value)
            ),
        };
    }

    /**
     * Quotes a string identifier (table or column name) based on the active driver.
     *
     * Handles simple names (e.g., "users") and qualified names (e.g., "users.id"),
     * wrapping them in backticks (MySQL) or double quotes (SQLite/PostgreSQL).
     *
     * @param string $identifier The identifier to quote.
     * @return string The quoted identifier string.
     */
    public static function quoteIdentifier(string $identifier): string
    {
        $char = self::getDriver() === Driver::MYSQL ? '`' : '"';

        $identifier = str_replace($char, $char . $char, $identifier);

        if (str_contains($identifier, '.')) {
            $parts = explode('.', $identifier);
            return implode('.', array_map(fn($p) => $char . $p . $char, $parts));
        }

        return $char . $identifier . $char;
    }

    /**
     * Initiates a transaction inside the database.
     * 
     * @return bool True on success, false on failure.
     */
    public static function beginTransaction(): bool
    {
        return self::getConn()->beginTransaction();
    }

    /**
     * Commits the active transaction.
     * 
     * @return bool True on success, false on failure.
     */
    public static function commit(): bool
    {
        return self::getConn()->commit();
    }

    /**
     * Rolls back the active transaction.
     * 
     * @return bool True on success, false on failure.
     */
    public static function rollBack(): bool
    {
        return self::getConn()->rollBack();
    }

    /**
     * Executes a set of operations within a database transaction.
     * 
     * If the callback throws an exception, the transaction is automatically rolled back.
     * If the callback executes successfully, the transaction is committed.
     *
     * @param callable $callback The function containing database logic.
     * @return mixed The return value of the callback.
     * @throws Throwable Re-throws the exception after rolling back.
     */
    public static function transaction(callable $callback): mixed
    {
        self::beginTransaction();

        try {
            $result = $callback();
            self::commit();
            return $result;
        } catch (Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }
}
