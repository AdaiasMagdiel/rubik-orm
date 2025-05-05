<?php

use AdaiasMagdiel\Rubik\Model;

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

class User extends Model
{
    protected static function fields(): array
    {
        return [
            'id' => self::Int(autoincrement: true, primaryKey: true),
            'name' => self::Text(notNull: true),
            'email' => self::Text(unique: true, notNull: true),
            'active' => self::Boolean(default: true),
            'created_at' => self::DateTime(default: 'CURRENT_TIMESTAMP'),
        ];
    }
}

class Post extends Model
{
    protected static function fields(): array
    {
        return [
            'id' => self::Int(autoincrement: true, primaryKey: true),
            'user_id' => self::Int(notNull: true),
            'title' => self::Text(notNull: true),
            'content' => self::Text(),
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
