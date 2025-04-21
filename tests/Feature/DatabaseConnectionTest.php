<?php

use AdaiasMagdiel\Rubik\DatabaseConnection;
use AdaiasMagdiel\Rubik\Rubik;

it('connects to SQLite database successfully', function () {
    Rubik::connect([
        'driver' => 'sqlite',
        'path' => ':memory:'
    ]);

    expect(DatabaseConnection::isConnected())->toBeTrue();
    expect(DatabaseConnection::getDriver())->toBe('sqlite');
});

it('throws exception for invalid SQLite path', function () {
    Rubik::connect([
        'driver' => 'sqlite',
        'path' => ''
    ]);
})->throws(InvalidArgumentException::class);

it('throws exception for invalid driver', function () {
    Rubik::connect([
        'driver' => 'postgres',
        'path' => ':memory:'
    ]);
})->throws(InvalidArgumentException::class);

it('connects to MySQL database successfully', function () {
    // Mock MySQL connection (since we can't connect to real MySQL in tests)
    $pdoMock = Mockery::mock(PDO::class);
    $pdoMock->shouldReceive('exec')->andReturn(true);

    DatabaseConnection::connect([
        'driver' => 'mysql',
        'host' => 'localhost',
        'database' => 'test',
        'username' => 'user',
        'password' => 'pass'
    ]);

    // We can't actually connect, so we verify driver is set
    expect(DatabaseConnection::getDriver())->toBe('mysql');
})->skip('Requires MySQL server for full testing');

it('disconnects properly', function () {
    Rubik::connect([
        'driver' => 'sqlite',
        'path' => ':memory:'
    ]);

    Rubik::disconnect();
    expect(DatabaseConnection::isConnected())->toBeFalse();
});

it('throws exception when accessing connection without connecting', function () {
    Rubik::disconnect();
    DatabaseConnection::getConnection();
})->throws(RuntimeException::class, 'No active database connection.');
