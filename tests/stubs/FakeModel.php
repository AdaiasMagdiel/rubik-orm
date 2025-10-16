<?php

namespace Tests\Stubs;

#[\AllowDynamicProperties]
class FakeModel
{
    public static function getTableName(): string
    {
        return 'users';
    }

    public static function primaryKey(): string
    {
        return 'id';
    }

    public function __set($key, $value)
    {
        $this->$key = $value;
    }
}
