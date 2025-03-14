<?php

use AdaiasMagdiel\Rubik\Rubik;

describe('Database Connection', function () {
    afterEach(function () {
        Rubik::disconnect();
    });

    test('should establish valid SQLite connection', function () {
        Rubik::connect(':memory:');
        expect(Rubik::getConn())->toBeInstanceOf(PDO::class);
    });

    test('should throw exception for empty connection path', function () {
        expect(fn() => Rubik::connect(''))
            ->toThrow(InvalidArgumentException::class);
    });

    test('should throw exception for invalid database path', function () {
        expect(fn() => Rubik::connect('/invalid/path/db.sqlite'))
            ->toThrow(RuntimeException::class);
    });

    test('should handle multiple connect/disconnect cycles', function () {
        Rubik::connect(':memory:');
        expect(Rubik::isConnected())->toBeTrue();

        Rubik::disconnect();
        expect(Rubik::isConnected())->toBeFalse();

        Rubik::connect(':memory:');
        expect(Rubik::isConnected())->toBeTrue();
    });

    test('should enforce foreign key constraints', function () {
        Rubik::connect(':memory:');
        $foreignKeys = Rubik::getConn()
            ->query('PRAGMA foreign_keys')
            ->fetchColumn();

        expect($foreignKeys)->toBe(1);
    });
});
