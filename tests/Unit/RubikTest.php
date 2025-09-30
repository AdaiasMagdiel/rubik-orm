<?php

use AdaiasMagdiel\Rubik\Enum\Driver;
use AdaiasMagdiel\Rubik\Rubik;

test('rubik can connect to sqlite successfully', function () {
    Rubik::connect(Driver::SQLITE, path: ':memory:');

    expect(Rubik::isConnected())->toBe(true);
    expect(Rubik::getConn())->toBeInstanceOf(PDO::class);
});

test('rubik can disconnect successfully', function () {
    Rubik::connect(Driver::SQLITE, path: ':memory:');

    expect(Rubik::isConnected())->toBe(true);
    Rubik::disconnect();
    expect(Rubik::isConnected())->toBe(false);
});
