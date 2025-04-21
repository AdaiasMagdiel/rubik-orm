<?php

namespace AdaiasMagdiel\Rubik;

use PDO;
use PDOException;
use RuntimeException;
use InvalidArgumentException;

/**
 * Manages database connections for the Rubik ORM using PDO.
 *
 * This class provides a singleton-like interface for establishing, retrieving, and closing
 * PDO database connections. It supports SQLite and MySQL/MariaDB drivers, with configurable
 * connection options and validation for driver-specific settings.
 *
 * @package AdaiasMagdiel\Rubik
 */
class DatabaseConnection
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
     * The database driver in use (e.g., 'sqlite', 'mysql', 'mariadb').
     *
     * @var string
     */
    private static string $driver = '';

    /**
     * Establishes a new PDO database connection.
     *
     * Configures the connection based on the provided configuration array, which must include
     * the driver and driver-specific settings (e.g., path for SQLite, host and database for MySQL).
     * Enables foreign key support for SQLite connections.
     *
     * @param array<string, mixed> $config The database configuration array.
     *                                    Must include 'driver' and driver-specific settings:
     *                                    - For SQLite: 'path' (file path or ':memory:').
     *                                    - For MySQL/MariaDB: 'host', 'port', 'database', 'charset' (optional).
     *                                    Optionally includes 'username', 'password', and 'options'.
     * @return void
     * @throws InvalidArgumentException If the driver is missing, unsupported, or configuration is invalid.
     * @throws RuntimeException If the connection fails due to a PDOException.
     */
    public static function connect(array $config): void
    {
        if (empty($config['driver'])) {
            throw new InvalidArgumentException('Database driver must be specified.');
        }

        self::$driver = strtolower($config['driver']);

        // Validate SQLite path
        if (self::$driver === 'sqlite' && (empty($config['path']) || $config['path'] === ':memory:' && $config['path'] !== ':memory:')) {
            throw new InvalidArgumentException('SQLite database path cannot be empty.');
        }

        try {
            $dsn = self::buildDsn($config);
            $options = array_merge(self::DEFAULT_PDO_OPTIONS, $config['options'] ?? []);

            self::$pdo = new PDO(
                $dsn,
                $config['username'] ?? null,
                $config['password'] ?? null,
                $options
            );

            if (self::$driver === 'sqlite') {
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
    public static function getConnection(): PDO
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
        self::$driver = '';
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
     * @return string The driver name (e.g., 'sqlite', 'mysql', 'mariadb').
     */
    public static function getDriver(): string
    {
        return self::$driver;
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
        return match (self::$driver) {
            'sqlite' => sprintf('sqlite:%s', $config['path'] ?? ''),
            'mysql', 'mariadb' => sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'] ?? 'localhost',
                $config['port'] ?? 3306,
                $config['database'] ?? '',
                $config['charset'] ?? 'utf8mb4'
            ),
            default => throw new InvalidArgumentException(
                sprintf('Unsupported database driver: %s', self::$driver)
            ),
        };
    }
}
