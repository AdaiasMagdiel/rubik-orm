<?php

use AdaiasMagdiel\Rubik\Enum\Driver;
use AdaiasMagdiel\Rubik\Model;
use AdaiasMagdiel\Rubik\Rubik;

beforeAll(function () {
    Rubik::connect(driver: Driver::SQLITE, path: ':memory:');

    $pdo = Rubik::getConn();

    // USERS (parent model)
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
    $pdo->exec("INSERT INTO users (id, name) VALUES (1, 'Alice'), (2, 'Bob')");

    // PROFILES (belongsTo)
    $pdo->exec('CREATE TABLE profiles (id INTEGER PRIMARY KEY, user_id INTEGER, bio TEXT)');
    $pdo->exec("INSERT INTO profiles (id, user_id, bio) VALUES (1, 1, 'dev'), (2, 2, 'designer')");

    // POSTS (hasMany)
    $pdo->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY, user_id INTEGER, title TEXT)');
    $pdo->exec("INSERT INTO posts (id, user_id, title) VALUES (1, 1, 'Hello'), (2, 1, 'World'), (3, 2, 'Foo')");

    // PIVOT + ROLES (belongsToMany)
    $pdo->exec('CREATE TABLE roles (id INTEGER PRIMARY KEY, name TEXT)');
    $pdo->exec("INSERT INTO roles (id, name) VALUES (1, 'admin'), (2, 'editor')");
    $pdo->exec('CREATE TABLE role_user (user_id INTEGER, role_id INTEGER)');
    $pdo->exec("INSERT INTO role_user (user_id, role_id) VALUES (1, 1), (1, 2), (2, 2)");
});

afterAll(fn() => Rubik::disconnect());

/**
 * Stub models for relationship testing
 */
class UserModelR extends Model
{
    protected static string $table = 'users';

    protected static function fields(): array
    {
        return [
            'id'   => ['type' => 'INTEGER', 'primary_key' => true],
            'name' => ['type' => 'TEXT'],
        ];
    }

    protected static function relationships(): array
    {
        return [
            'profile' => [
                'type'       => 'hasOne',
                'related'    => ProfileModelR::class,
                'foreignKey' => 'user_id',
                'localKey'   => 'id',
            ],
            'posts' => [
                'type'       => 'hasMany',
                'related'    => PostModelR::class,
                'foreignKey' => 'user_id',
                'localKey'   => 'id',
            ],
            'roles' => [
                'type'             => 'belongsToMany',
                'related'          => RoleModelR::class,
                'pivotTable'       => 'role_user',
                'foreignKey'       => 'user_id',
                'relatedKey'       => 'role_id',
                'localKey'         => 'id',
                'relatedOwnerKey'  => 'id',
            ],
        ];
    }
}

class ProfileModelR extends Model
{
    protected static string $table = 'profiles';

    protected static function fields(): array
    {
        return [
            'id'      => ['type' => 'INTEGER', 'primary_key' => true],
            'user_id' => ['type' => 'INTEGER'],
            'bio'     => ['type' => 'TEXT'],
        ];
    }

    protected static function relationships(): array
    {
        return [
            'user' => [
                'type'       => 'belongsTo',
                'related'    => UserModelR::class,
                'foreignKey' => 'user_id',
                'ownerKey'   => 'id',
            ],
        ];
    }
}

class PostModelR extends Model
{
    protected static string $table = 'posts';

    protected static function fields(): array
    {
        return [
            'id'      => ['type' => 'INTEGER', 'primary_key' => true],
            'user_id' => ['type' => 'INTEGER'],
            'title'   => ['type' => 'TEXT'],
        ];
    }
}

class RoleModelR extends Model
{
    protected static string $table = 'roles';

    protected static function fields(): array
    {
        return [
            'id'   => ['type' => 'INTEGER', 'primary_key' => true],
            'name' => ['type' => 'TEXT'],
        ];
    }
}

describe('Model relationships', function () {

    test('hasOne relationship resolves correctly and caches result', function () {
        $user = new UserModelR();
        $user->__set('id', 1);
        $profile = $user->profile;

        expect($profile)->toBeInstanceOf(ProfileModelR::class)
            ->and($profile->bio)->toBe('dev');

        // cached result should be identical instance
        $cached = $user->profile;
        expect($cached)->toBe($profile);
    });

    test('hasMany relationship returns array of related models', function () {
        $user = new UserModelR();
        $user->__set('id', 1);
        $posts = $user->posts;

        expect($posts)->toBeArray()->and(count($posts))->toBe(2);
        expect($posts[0])->toBeInstanceOf(PostModelR::class);
    });

    test('belongsTo relationship returns single parent model', function () {
        $profile = new ProfileModelR();
        $profile->__set('user_id', 1);
        $user = $profile->user;

        expect($user)->toBeInstanceOf(UserModelR::class)
            ->and($user->name)->toBe('Alice');
    });

    test('belongsToMany relationship returns multiple related models', function () {
        $user = new UserModelR();
        $user->__set('id', 1);
        $roles = $user->roles;

        expect($roles)->toBeArray()->and(count($roles))->toBe(2);
        expect($roles[0])->toBeInstanceOf(RoleModelR::class);
    });

    test('unknown field or relationship returns null', function () {
        $user = new UserModelR();
        expect($user->nonexistent)->toBeNull();
    });

    test('invalid relationship type throws exception', function () {
        $ref = new ReflectionClass(UserModelR::class);
        $method = $ref->getMethod('relationships');
        $method->setAccessible(true);

        // Inject fake relationship type
        $relationships = $method->invoke(null);
        $relationships['invalid'] = [
            'type'    => 'magicLink',
            'related' => UserModel::class,
        ];

        // Mock UserModel with invalid relationship
        $mock = new class extends UserModel {
            protected static function relationships(): array
            {
                return [
                    'broken' => [
                        'type'    => 'invalidType',
                        'related' => UserModel::class,
                    ],
                ];
            }
        };

        expect(fn() => $mock->broken)->toThrow(InvalidArgumentException::class, 'Unknown relationship type');
    });

    test('__set and __get handle primary key and casting correctly', function () {
        $u = new UserModel();
        $u->__set('id', '5');
        $u->__set('name', 'Tester');

        expect($u->id)->toBeInt()->and($u->name)->toBe('Tester');
    });
});
