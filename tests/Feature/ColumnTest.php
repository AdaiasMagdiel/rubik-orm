<?php

use AdaiasMagdiel\Rubik\Column;
use AdaiasMagdiel\Rubik\Enum\Driver;
use AdaiasMagdiel\Rubik\Rubik;
use AdaiasMagdiel\Rubik\SQL;

// ───────────────────────────────────────────────
//  Default driver reset before each test
// ───────────────────────────────────────────────

beforeEach(function () {
    $ref = new ReflectionClass(Rubik::class);
    $prop = $ref->getProperty('driver');
    $prop->setAccessible(true);
    $prop->setValue(null, Driver::SQLITE);
});

// ───────────────────────────────────────────────
//  __callStatic basic behavior
// ───────────────────────────────────────────────

test('creates a valid column definition for VARCHAR', function () {
    $col = Column::Varchar(length: 100, notNull: true, default: 'abc');
    expect($col)
        ->toBeArray()
        ->and($col['type'])->toBe('TEXT') // sqlite default
        ->and($col['not_null'])->toBeTrue()
        ->and($col['length'])->toBe(100)
        ->and($col['default'])->toBe('abc');
});

test('throws when using unsupported type', function () {
    Column::NonexistentType();
})->throws(BadMethodCallException::class);


// ───────────────────────────────────────────────
//  Validators: general numeric types
// ───────────────────────────────────────────────

test('validates integer default correctly', function () {
    $col = Column::Integer(default: 5);
    expect($col['default'])->toBe(5);
});

test('throws for invalid integer default', function () {
    Column::Integer(default: 'str');
})->throws(InvalidArgumentException::class, 'INTEGER default must be integer');

test('validates tinyint unsigned range', function () {
    $col = Column::Tinyint(default: 200, unsigned: true);
    expect($col['default'])->toBe(200);
});

test('throws for tinyint below signed min', function () {
    Column::Tinyint(default: -200);
})->throws(InvalidArgumentException::class);

test('validates decimal and allows SQL::raw', function () {
    $sql = SQL::raw('CURRENT_TIMESTAMP');
    $col = Column::Decimal(default: $sql);
    expect($col['default'])->toBe($sql);
});

test('throws for invalid decimal precision', function () {
    Column::Decimal(precision: 0);
})->throws(InvalidArgumentException::class);

// ───────────────────────────────────────────────
//  Validators: string and text types
// ───────────────────────────────────────────────

test('throws when varchar length is too small', function () {
    Column::Varchar(length: 0);
})->throws(InvalidArgumentException::class, 'Length must be');

test('accepts SQL::raw() as default for TEXT', function () {
    $sql = SQL::raw('NOW()');
    $col = Column::Text(default: $sql);
    expect($col['default'])->toBe($sql);
});

test('throws when TEXT default is not string', function () {
    Column::Text(default: 123);
})->throws(InvalidArgumentException::class);

// ───────────────────────────────────────────────
//  Validators: JSON and UUID
// ───────────────────────────────────────────────

test('accepts valid JSON string', function () {
    $col = Column::Json(default: '{"a":1}');
    expect($col['default'])->toBe('{"a":1}');
});

test('throws on invalid JSON string', function () {
    Column::Json(default: '{invalid}');
})->throws(InvalidArgumentException::class);

test('accepts valid UUID', function () {
    $uuid = '550e8400-e29b-41d4-a716-446655440000';
    $col = Column::Uuid(default: $uuid);
    expect($col['default'])->toBe($uuid);
});

test('throws on invalid UUID format', function () {
    Column::Uuid(default: 'not-a-uuid');
})->throws(InvalidArgumentException::class);

// ───────────────────────────────────────────────
//  Validators: ENUM and SET
// ───────────────────────────────────────────────

test('accepts valid ENUM default', function () {
    $col = Column::Enum(values: ['a', 'b'], default: 'b');
    expect($col['default'])->toBe('b');
});

test('throws when ENUM default not in values', function () {
    Column::Enum(values: ['x', 'y'], default: 'z');
})->throws(InvalidArgumentException::class);

test('throws when ENUM values array empty', function () {
    Column::Enum(values: []);
})->throws(InvalidArgumentException::class);

test('accepts valid SET default', function () {
    $col = Column::Set(values: ['x', 'y'], default: 'x,y');
    expect($col['default'])->toBe('x,y');
});

test('throws when SET default invalid', function () {
    Column::Set(values: ['x', 'y'], default: 'z');
})->throws(InvalidArgumentException::class);

// ───────────────────────────────────────────────
//  Validators: Time and Date types
// ───────────────────────────────────────────────

test('accepts valid TIME format', function () {
    $col = Column::Time(default: '12:30:59');
    expect($col['default'])->toBe('12:30:59');
});

test('throws when TIME format invalid', function () {
    Column::Time(default: '25:99:99');
})->throws(InvalidArgumentException::class);

test('accepts YEAR within range', function () {
    $col = Column::Year(default: 2024);
    expect($col['default'])->toBe(2024);
});

test('throws when YEAR out of range', function () {
    Column::Year(default: 3000);
})->throws(InvalidArgumentException::class);

// ───────────────────────────────────────────────
//  ForeignKey helper
// ───────────────────────────────────────────────

test('creates valid foreign key definition', function () {
    $fk = Column::ForeignKey('id', 'users', 'CASCADE', 'SET NULL');
    expect($fk['foreign_key'])
        ->toHaveKeys(['references', 'table', 'on_delete', 'on_update'])
        ->and($fk['foreign_key']['on_delete'])->toBe('CASCADE')
        ->and($fk['foreign_key']['on_update'])->toBe('SET NULL');
});

test('throws when references or table empty', function () {
    Column::ForeignKey('', 'users');
})->throws(InvalidArgumentException::class);

test('throws for invalid onDelete action', function () {
    Column::ForeignKey('id', 'users', 'INVALID');
})->throws(InvalidArgumentException::class);

test('accepts underscore format in onDelete', function () {
    $fk = Column::ForeignKey('id', 'users', 'SET_NULL', 'NO_ACTION');
    expect($fk['foreign_key']['on_delete'])->toBe('SET NULL');
});

// ───────────────────────────────────────────────
//  Misc edge cases
// ───────────────────────────────────────────────

test('accepts SQL::raw() defaults across validators', function () {
    $sql = SQL::raw('DEFAULT');
    expect(Column::Integer(default: $sql)['default'])->toBe($sql)
        ->and(Column::Decimal(default: $sql)['default'])->toBe($sql)
        ->and(Column::Text(default: $sql)['default'])->toBe($sql)
        ->and(Column::DateTime(default: $sql)['default'])->toBe($sql)
        ->and(Column::Geometry(default: $sql)['default'])->toBe($sql);
});

// ───────────────────────────────────────────────
//  MySQL Driver tests
// ───────────────────────────────────────────────

test('resolves native type for MySQL', function () {
    $ref = new ReflectionClass(Rubik::class);
    $prop = $ref->getProperty('driver');
    $prop->setAccessible(true);
    $prop->setValue(null, Driver::MYSQL);

    $col = Column::Float();
    expect($col['type'])->toBe('FLOAT');
});

test('resolves native type for SQLite', function () {
    $ref = new ReflectionClass(Rubik::class);
    $prop = $ref->getProperty('driver');
    $prop->setAccessible(true);
    $prop->setValue(null, Driver::SQLITE);

    $col = Column::Varchar();
    expect($col['type'])->toBe('TEXT');
});

// ───────────────────────────────────────────────
//  MySQL driver type coverage
// ───────────────────────────────────────────────

test('MySQL: supports BINARY(16)', function () {
    $ref = new ReflectionClass(Rubik::class);
    $prop = $ref->getProperty('driver');
    $prop->setAccessible(true);
    $prop->setValue(null, Driver::MYSQL);

    $col = Column::Binary(length: 16);
    expect($col['type'])->toBe('BINARY')
        ->and($col['length'])->toBe(16);
});

test('MySQL: supports VARBINARY(255)', function () {
    $ref = new ReflectionClass(Rubik::class);
    $prop = $ref->getProperty('driver');
    $prop->setAccessible(true);
    $prop->setValue(null, Driver::MYSQL);

    $col = Column::Varbinary(length: 255);
    expect($col['type'])->toBe('VARBINARY')
        ->and($col['length'])->toBe(255);
});


test('MySQL: maps BOOLEAN to TINYINT(1)', function () {
    $ref = new ReflectionClass(Rubik::class);
    $prop = $ref->getProperty('driver');
    $prop->setAccessible(true);
    $prop->setValue(null, Driver::MYSQL);

    $col = Column::Boolean(default: true);
    expect($col['type'])->toBe('TINYINT(1)')
        ->and($col['default'])->toBeTrue();
});

test('MySQL: maps UUID to CHAR(36)', function () {
    $ref = new ReflectionClass(Rubik::class);
    $prop = $ref->getProperty('driver');
    $prop->setAccessible(true);
    $prop->setValue(null, Driver::MYSQL);

    $col = Column::Uuid(default: '550e8400-e29b-41d4-a716-446655440000');
    expect($col['type'])->toBe('CHAR(36)');
});

test('MySQL: maps JSON to JSON', function () {
    $ref = new ReflectionClass(Rubik::class);
    $prop = $ref->getProperty('driver');
    $prop->setAccessible(true);
    $prop->setValue(null, Driver::MYSQL);

    $col = Column::Json(default: '{"a":1}');
    expect($col['type'])->toBe('JSON');
});

test('MySQL: supports DECIMAL(10,2)', function () {
    $ref = new ReflectionClass(Rubik::class);
    $prop = $ref->getProperty('driver');
    $prop->setAccessible(true);
    $prop->setValue(null, Driver::MYSQL);

    $col = Column::Decimal(precision: 10, scale: 2);
    expect($col['type'])->toBe('DECIMAL')
        ->and($col['precision'])->toBe(10)
        ->and($col['scale'])->toBe(2);
});

test('MySQL: ForeignKey supports ON DELETE CASCADE', function () {
    $fk = Column::ForeignKey('user_id', 'users', 'CASCADE', 'SET NULL');
    expect($fk['foreign_key']['on_delete'])->toBe('CASCADE')
        ->and($fk['foreign_key']['on_update'])->toBe('SET NULL');
});

// ───────────────────────────────────────────────
//  Extra validators for uncovered types
// ───────────────────────────────────────────────

test('validates NUMERIC with numeric default', function () {
    $col = Column::Numeric(default: 10.5);
    expect($col['default'])->toBe(10.5);
});

test('throws for invalid NUMERIC scale', function () {
    Column::Numeric(precision: 5, scale: 10);
})->throws(InvalidArgumentException::class);

test('validates BIT with integer default', function () {
    $col = Column::Bit(length: 3, default: 5);
    expect($col['default'])->toBe(5);
});

test('throws for invalid BIT length', function () {
    Column::Bit(length: 70);
})->throws(InvalidArgumentException::class);

test('validates BOOLEAN true/false and SQL::raw', function () {
    $sql = SQL::raw('TRUE');
    expect(Column::Boolean(default: true)['default'])->toBeTrue()
        ->and(Column::Boolean(default: $sql)['default'])->toBe($sql);
});

test('throws for invalid BOOLEAN value', function () {
    Column::Boolean(default: 'maybe');
})->throws(InvalidArgumentException::class);

test('validates DATE with string default', function () {
    $col = Column::Date(default: '2024-10-15');
    expect($col['default'])->toBe('2024-10-15');
});

test('throws for invalid DATE default type', function () {
    Column::Date(default: 123);
})->throws(InvalidArgumentException::class);

test('validates BLOB and BINARY types', function () {
    $sql = SQL::raw("x'00FF'");
    expect(Column::Blob(default: $sql)['default'])->toBe($sql)
        ->and(Column::Binary(length: 10, default: 'abcdef')['default'])->toBe('abcdef');
});

test('throws for invalid BINARY length', function () {
    Column::Binary(length: 0);
})->throws(InvalidArgumentException::class);
