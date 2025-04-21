<?php

uses()->beforeEach(function () {
    // Reset database connection before each test
    if (AdaiasMagdiel\Rubik\DatabaseConnection::isConnected()) {
        AdaiasMagdiel\Rubik\DatabaseConnection::disconnect();
    }
});

uses()->afterAll(function () {
    // Clean up Mockery
    if (class_exists('Mockery')) {
        Mockery::close();
    }
});
