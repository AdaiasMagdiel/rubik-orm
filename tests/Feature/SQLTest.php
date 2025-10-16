<?php

use AdaiasMagdiel\Rubik\SQL;

test('stores and returns SQL expression exactly as provided', function () {
    $expr = 'CURRENT_TIMESTAMP';
    $sql = new SQL($expr);

    expect((string) $sql)->toBe($expr);
});

test('factory method raw() returns instance of SQL', function () {
    $expr = 'NOW()';
    $sql = SQL::raw($expr);

    expect($sql)->toBeInstanceOf(SQL::class)
        ->and((string) $sql)->toBe($expr);
});

test('different instances with same expression are equal in string value but not identical', function () {
    $a = new SQL('uuid_generate_v4()');
    $b = SQL::raw('uuid_generate_v4()');

    expect((string) $a)->toBe((string) $b);

    expect($a)->not()->toBe($b);
});

test('empty string expression is accepted and returned unchanged', function () {
    $sql = new SQL('');
    expect((string) $sql)->toBe('');
});

test('expressions with special characters are preserved literally', function () {
    $expr = "CONCAT('a', \"b\", `c`, \$d)";
    $sql = SQL::raw($expr);

    expect((string) $sql)->toBe($expr);
});

test('SQL is effectively immutable', function () {
    $sql = new SQL('NOW()');

    $ref = new ReflectionClass(SQL::class);
    $prop = $ref->getProperty('expr');
    $prop->setAccessible(true);

    $original = $prop->getValue($sql);
    $prop->setValue($sql, 'CHANGED');

    expect(method_exists($sql, '__toString'))->toBeTrue()
        ->and($sql)->toBeInstanceOf(SQL::class)
        ->and((string) $sql)->toBe('CHANGED');

    $publicMethods = array_map(fn($m) => $m->getName(), $ref->getMethods());
    expect($publicMethods)->not()->toContain('setExpr');
});
