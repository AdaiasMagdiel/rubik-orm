<?php

use AdaiasMagdiel\Rubik\Rubik;
use PDO;

test('rubik can connect to sqlite successfully', function () {
    Rubik::connect([
        "driver" => "sqlite",
        "path" => ":memory:"
    ]);

    expect(Rubik::isConnected())->toBe(true);
    expect(Rubik::getConn())->toBeInstanceOf(PDO::class);
});

test('rubik can disconnect successfully', function () {
    Rubik::connect([
        "driver" => "sqlite",
        "path" => ":memory:"
    ]);

    expect(Rubik::isConnected())->toBe(true);
    Rubik::disconnect();
    expect(Rubik::isConnected())->toBe(false);
});
