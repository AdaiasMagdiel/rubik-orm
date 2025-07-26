<?php

namespace AdaiasMagdiel\Rubik;

use PDO;

/**
 * Rubik is the main entry point for the Rubik ORM, providing static methods to manage
 * database connections and access the underlying PDO connection.
 */
class Rubik
{
    /**
     * Establishes a database connection using the provided configuration.
     *
     * @param array $config Configuration array containing database connection details.
     *                      Required keys depend on the driver:
     *                      - 'driver': 'sqlite' or 'mysql'
     *                      - For SQLite: 'path' (e.g., ':memory:' or file path)
     *                      - For MySQL: 'host', 'database', 'username', 'password'
     *                      Optional: 'options' (PDO options array)
     * @return void
     * @throws InvalidArgumentException If the configuration is invalid.
     * @throws RuntimeException If the connection fails.
     */
    public static function connect(array $config): void
    {
        DatabaseConnection::connect($config);
    }

    /**
     * Retrieves the active PDO database connection.
     *
     * @return PDO The active PDO connection instance, or null if not connected.
     * @throws RuntimeException If no active connection exists.
     */
    public static function getConn(): PDO
    {
        return DatabaseConnection::getConnection();
    }

    /**
     * Closes the active database connection.
     *
     * @return void
     */
    public static function disconnect(): void
    {
        DatabaseConnection::disconnect();
    }

    /**
     * Checks if a database connection is currently active.
     *
     * @return bool True if a connection is active, false otherwise.
     */
    public static function isConnected(): bool
    {
        return DatabaseConnection::isConnected();
    }
}
