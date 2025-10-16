<?php

use AdaiasMagdiel\Rubik\Rubik;
use AdaiasMagdiel\Rubik\Enum\Driver;

beforeEach(fn() => Rubik::disconnect());

test('connects successfully to SQLite in-memory database', function () {
    Rubik::connect(driver: Driver::SQLITE, path: ':memory:');

    $conn = Rubik::getConn();

    expect($conn)->toBeInstanceOf(PDO::class)
        ->and(Rubik::isConnected())->toBeTrue()
        ->and(Rubik::getDriver())->toBe(Driver::SQLITE);
});

test('throws when getting connection without connecting first', function () {
    expect(fn() => Rubik::getConn())
        ->toThrow(RuntimeException::class, 'No active database connection');
});

test('disconnect clears connection and driver', function () {
    Rubik::connect(driver: Driver::SQLITE, path: ':memory:');
    Rubik::disconnect();

    expect(Rubik::isConnected())->toBeFalse();
});

test('throws when SQLite path is empty', function () {
    Rubik::connect(driver: Driver::SQLITE, path: '');
})->throws(InvalidArgumentException::class);

test('SQLite enables foreign key constraints automatically', function () {
    Rubik::connect(driver: Driver::SQLITE, path: ':memory:');
    $result = Rubik::getConn()->query('PRAGMA foreign_keys;')->fetchColumn();

    expect((int)$result)->toBe(1);
});

test('MySQL DSN is built correctly and fails gracefully if unreachable', function () {
    expect(fn() => Rubik::connect(
        driver: Driver::MYSQL,
        username: 'fake_user',
        password: 'fake_pass',
        database: 'fake_db',
        host: '127.0.0.1',
        port: 3306,
        charset: 'utf8mb4'
    ))->toThrow(RuntimeException::class, 'Failed to connect to database');
});

test('getDriver returns the current driver', function () {
    Rubik::connect(driver: Driver::SQLITE, path: ':memory:');
    expect(Rubik::getDriver())->toBe(Driver::SQLITE);
});

test('isConnected returns false before connection', function () {
    expect(Rubik::isConnected())->toBeFalse();
});

test('isConnected returns true after connection', function () {
    Rubik::connect(driver: Driver::SQLITE, path: ':memory:');
    expect(Rubik::isConnected())->toBeTrue();
});

test('disconnect resets internal static properties', function () {
    Rubik::connect(driver: Driver::SQLITE, path: ':memory:');
    Rubik::disconnect();

    $ref = new ReflectionClass(Rubik::class);
    $pdoProp = $ref->getProperty('pdo');
    $pdoProp->setAccessible(true);
    $driverProp = $ref->getProperty('driver');
    $driverProp->setAccessible(true);

    expect($pdoProp->getValue())->toBeNull()
        ->and($driverProp->getValue())->toBeNull();
});

test('throws if unsupported driver is set', function () {
    expect(fn() => Rubik::connect(driver: Driver::from('POSTGRES')))
        ->toThrow(ValueError::class);
});

test('buildDsn builds correct DSN for each driver', function () {
    $ref = new ReflectionClass(Rubik::class);
    $method = $ref->getMethod('buildDsn');
    $method->setAccessible(true);

    // SQLite
    $refDriver = $ref->getProperty('driver');
    $refDriver->setAccessible(true);
    $refDriver->setValue(null, Driver::SQLITE);
    $dsnSqlite = $method->invoke(null, ['path' => ':memory:']);
    expect($dsnSqlite)->toBe('sqlite::memory:');

    // MySQL
    $refDriver->setValue(null, Driver::MYSQL);
    $dsnMysql = $method->invoke(null, [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'db',
        'charset' => 'utf8mb4',
    ]);
    expect($dsnMysql)->toBe('mysql:host=127.0.0.1;port=3306;dbname=db;charset=utf8mb4');
});

test('buildDsn throws if driver is not set', function () {
    $ref = new ReflectionClass(Rubik::class);
    $method = $ref->getMethod('buildDsn');
    $method->setAccessible(true);

    $refDriver = $ref->getProperty('driver');
    $refDriver->setAccessible(true);
    $refDriver->setValue(null, null);

    expect(fn() => $method->invoke(null, ['path' => ':memory:']))
        ->toThrow(InvalidArgumentException::class);
});

test('connect to MySQL without database triggers runtime exception', function () {
    expect(fn() => Rubik::connect(
        driver: Driver::MYSQL,
        host: 'localhost',
        username: 'root',
        password: '',
        database: '',
    ))->toThrow(RuntimeException::class);
});

test('can reconnect after disconnect', function () {
    Rubik::connect(driver: Driver::SQLITE, path: ':memory:');
    Rubik::disconnect();
    Rubik::connect(driver: Driver::SQLITE, path: ':memory:');
    expect(Rubik::isConnected())->toBeTrue();
});

test('custom PDO options override defaults', function () {
    Rubik::connect(
        driver: Driver::SQLITE,
        path: ':memory:',
        options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]
    );

    $conn = Rubik::getConn();
    expect($conn)->toBeInstanceOf(PDO::class);
});
