<?php

use AdaiasMagdiel\Rubik\Column;
use AdaiasMagdiel\Rubik\Enum\Driver;
use AdaiasMagdiel\Rubik\Model;
use AdaiasMagdiel\Rubik\Rubik;
use AdaiasMagdiel\Rubik\SQL;

// Define all test models at the top to avoid defining classes inside closures

class BasicUser extends Model
{
	public static string $table = 'users';

	public static function fields(): array
	{
		return [
			'id' => Column::Int(autoincrement: true, primaryKey: true),
			'name' => Column::Varchar(notNull: true),
			'email' => Column::Varchar(unique: true, notNull: true),
			'age' => Column::Int(default: 18),
			'is_active' => Column::Boolean(default: true),
			'created_at' => Column::Datetime(default: SQL::raw('CURRENT_TIMESTAMP')),
		];
	}
}

class NoPrimaryKeyModel extends Model
{
	public static string $table = 'no_pk';

	public static function fields(): array
	{
		return [
			'name' => Column::Text(),
		];
	}
}

class CustomTableModel extends Model
{
	public static string $table = 'custom_users';

	public static function fields(): array
	{
		return [
			'user_id' => Column::Int(autoincrement: true, primaryKey: true),
		];
	}
}

class EnumModel extends Model
{
	public static string $table = 'enums';

	public static function fields(): array
	{
		return [
			'id' => Column::Int(autoincrement: true, primaryKey: true),
			'status' => Column::Enum(['active', 'inactive'], default: 'active'),
		];
	}
}

class InvalidEnumModel extends Model
{
	public static string $table = 'invalid_enums';

	public static function fields(): array
	{
		return [
			'id' => Column::Int(autoincrement: true, primaryKey: true),
			'status' => Column::Enum([]), // Empty enum to trigger exception
		];
	}
}

class TinyintModel extends Model
{
	public static string $table = 'tinyints';

	public static function fields(): array
	{
		return [
			'id' => Column::Int(autoincrement: true, primaryKey: true),
			'value' => Column::Tinyint(unsigned: true, default: 0),
		];
	}
}

// Setup: Connect to in-memory SQLite before each test
beforeEach(function () {
	Rubik::connect(Driver::SQLITE, path: ':memory:');
});

// Teardown: Disconnect after each test to reset state
afterEach(function () {
	Rubik::disconnect();
});

// SchemaTrait Tests

test('can get table name from class name if not explicitly set', function () {
	expect(BasicUser::getTableName())->toBe('users');
});

test('can get explicitly set table name', function () {
	expect(CustomTableModel::getTableName())->toBe('custom_users');
});

test('can get primary key', function () {
	expect(BasicUser::primaryKey())->toBe('id');
});

test('throws exception if no primary key defined', function () {
	NoPrimaryKeyModel::primaryKey();
})->throws(RuntimeException::class, 'No primary key defined for model.');

test('can create table', function () {
	expect(BasicUser::createTable())->toBeTrue();

	// Verify table exists by querying it
	$pdo = Rubik::getConn();
	$stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
	expect($stmt->fetch(PDO::FETCH_ASSOC)['name'] ?? null)->toBe('users');
});

test('can create table if not exists without error', function () {
	BasicUser::createTable();
	expect(BasicUser::createTable(ifNotExists: true))->toBeTrue();
});

test('throws exception on invalid field configuration during createTable', function () {
	InvalidEnumModel::createTable();
})->throws(InvalidArgumentException::class);

test('can truncate table', function () {
	BasicUser::createTable();
	$user = new BasicUser();
	$user->name = 'John';
	$user->email = 'john@example.com';
	$user->save();

	expect(BasicUser::truncateTable())->toBeTrue();

	// Verify no records
	expect(BasicUser::all())->toBeEmpty();
});

test('can drop table', function () {
	BasicUser::createTable();
	expect(BasicUser::dropTable())->toBeTrue();

	// Verify table does not exist
	$pdo = Rubik::getConn();
	$stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
	expect($stmt->fetch())->toBeFalse();
});

test('can drop table if exists without error', function () {
	expect(BasicUser::dropTable(ifExists: true))->toBeTrue();
});

// CrudTrait Tests

test('can save new model (insert)', function () {
	BasicUser::createTable();

	$user = new BasicUser();
	$user->name = 'John Doe';
	$user->email = 'john@example.com';
	$user->age = 25;
	$user->is_active = false;

	expect($user->save())->toBeTrue();
	expect((int)$user->id)->toBeInt()->toBe(1);
	expect($user->created_at)->toBeString();
});

test('can save with ignore on duplicate', function () {
	BasicUser::createTable();

	$user1 = new BasicUser();
	$user1->name = 'John';
	$user1->email = 'john@example.com';
	$user1->save();

	$user2 = new BasicUser();
	$user2->name = 'John';
	$user2->email = 'john@example.com'; // Duplicate unique email

	expect($user2->save(ignore: true))->toBeTrue();
	expect(isset($user2->id))->toBeFalse(); // Not inserted, no id set
});

test('can update existing model', function () {
	BasicUser::createTable();

	$user = new BasicUser();
	$user->name = 'John';
	$user->email = 'john@example.com';
	$user->save();

	$user->name = 'Jane';
	expect($user->update())->toBeTrue();

	$updatedUser = BasicUser::find(1);
	expect($updatedUser->name)->toBe('Jane');
});

test('update returns true if no changes', function () {
	BasicUser::createTable();

	$user = new BasicUser();
	$user->name = 'John';
	$user->email = 'john@example.com';
	$user->save();

	expect($user->update())->toBeTrue(); // No changes
});

test('throws exception on update without primary key', function () {
	BasicUser::createTable();

	$user = new BasicUser();
	$user->name = 'John';
	$user->email = 'john@example.com';
	$user->update(); // No PK set
})->throws(RuntimeException::class, 'Cannot update record without primary key.');

test('can delete model', function () {
	BasicUser::createTable();

	$user = new BasicUser();
	$user->name = 'John';
	$user->email = 'john@example.com';
	$user->save();

	expect($user->delete())->toBeTrue();
	expect(BasicUser::find(1))->toBeNull();
});

test('throws exception on delete without primary key', function () {
	BasicUser::createTable();

	$user = new BasicUser();
	$user->delete();
})->throws(RuntimeException::class, 'Cannot delete record without primary key.');

test('can insert many records', function () {
	BasicUser::createTable();

	$records = [
		['name' => 'John', 'email' => 'john@example.com', 'age' => 25, 'is_active' => true, 'created_at' => '2023-01-01'],
		['name' => 'Jane', 'email' => 'jane@example.com', 'age' => 30, 'is_active' => true, 'created_at' => '2023-01-01'],
	];

	expect(BasicUser::insertMany($records))->toBeTrue();
	expect(BasicUser::all())->toHaveCount(2);
});

test('insertMany returns false if no records', function () {
	BasicUser::createTable();
	expect(BasicUser::insertMany([]))->toBeFalse();
});

test('insertMany handles null values for missing fields', function () {
	BasicUser::createTable();

	$records = [
		['name' => 'John', 'email' => 'john@example.com'],
	];

	expect(BasicUser::insertMany($records))->toBeTrue();
	$user = BasicUser::find(1);
	expect($user)->not->toBeNull();
	expect($user->age)->toBe(18);
	expect($user->is_active)->toBe(1);
	expect($user->created_at)->toBeString()->not->toBeEmpty();
});

// QueryTrait Tests

test('can find by id', function () {
	BasicUser::createTable();

	$user = new BasicUser();
	$user->name = 'John';
	$user->email = 'john@example.com';
	$user->save();

	$found = BasicUser::find(1);
	expect($found)->toBeInstanceOf(BasicUser::class);
	expect($found->name)->toBe('John');
});

test('find returns null if not found', function () {
	BasicUser::createTable();
	expect(BasicUser::find(999))->toBeNull();
});

test('can get first record', function () {
	BasicUser::createTable();

	$user1 = new BasicUser();
	$user1->name = 'John';
	$user1->email = 'john@example.com';
	$user1->save();

	$user2 = new BasicUser();
	$user2->name = 'Jane';
	$user2->email = 'jane@example.com';
	$user2->save();

	$first = BasicUser::first();
	expect($first->id)->toBe(1);
	expect($first->name)->toBe('John');
});

test('first returns null if no records', function () {
	BasicUser::createTable();
	expect(BasicUser::first())->toBeNull();
});

test('can get all records', function () {
	BasicUser::createTable();

	BasicUser::insertMany([
		['name' => 'John', 'email' => 'john@example.com'],
		['name' => 'Jane', 'email' => 'jane@example.com'],
	]);

	$all = BasicUser::all();
	expect($all)->toBeArray()->toHaveCount(2);
	expect($all[0])->toBeInstanceOf(BasicUser::class);
});

test('all returns empty array if no records', function () {
	BasicUser::createTable();
	expect(BasicUser::all())->toBeEmpty();
});

test('can paginate records', function () {
	BasicUser::createTable();

	$records = [];
	for ($i = 1; $i <= 10; $i++) {
		$records[] = ['name' => "User $i", 'email' => "user$i@example.com"];
	}
	expect(BasicUser::insertMany($records))->toBeTrue();
	expect(BasicUser::all())->toHaveCount(10); // Verify insertion

	$page1 = BasicUser::paginate(1, 5);
	expect($page1['data'])->toHaveCount(5);
	expect($page1['data'][0]->name)->toBe('User 1');

	$page2 = BasicUser::paginate(2, 5);
	expect($page2['data'])->toHaveCount(5);
	expect($page2['data'][0]->name)->toBe('User 6');

	$page3 = BasicUser::paginate(3, 5);
	expect($page3['data'])->toBeEmpty();
});

test('paginate handles invalid page or perPage gracefully', function () {
	BasicUser::createTable();
	expect(fn() => BasicUser::paginate(0, 10))->toThrow(InvalidArgumentException::class, 'Page must be at least 1');
	expect(fn() => BasicUser::paginate(1, 0))->toThrow(InvalidArgumentException::class, 'PerPage must be at least 1');
});

test('can use query builder for complex queries', function () {
	BasicUser::createTable();

	BasicUser::insertMany([
		['name' => 'John', 'email' => 'john@example.com', 'age' => 25],
		['name' => 'Jane', 'email' => 'jane@example.com', 'age' => 30],
		['name' => 'Bob', 'email' => 'bob@example.com', 'age' => 25],
	]);

	$results = BasicUser::query()
		->where('age', 25)
		->orWhere('name', 'Jane')
		->orderBy('name', 'ASC')
		->all();

	expect($results)->toHaveCount(3);
	expect($results[0]->name)->toBe('Bob');
	expect($results[1]->name)->toBe('Jane');
	expect($results[2]->name)->toBe('John');
});

// SerializationTrait Tests

test('can convert to array', function () {
	BasicUser::createTable();

	$user = new BasicUser();
	$user->name = 'John';
	$user->email = 'john@example.com';
	expect($user->save())->toBeTrue();

	$array = $user->toArray();
	expect($array)->toBeArray();
	expect((int)$array['id'])->toBe(1);
	expect($array['name'])->toBe('John');
	expect($array['email'])->toBe('john@example.com');
	expect($array['age'])->toBe(18);
	expect($array['is_active'])->toBe(1);
	expect($array['created_at'])->toBeString()->not->toBeEmpty();
});

test('can json serialize', function () {
	BasicUser::createTable();

	$user = new BasicUser();
	$user->name = 'John';
	$user->email = 'john@example.com';
	expect($user->save())->toBeTrue();

	$json = json_encode($user);
	expect($json)->toBeJson();

	$decoded = json_decode($json, true);
	expect((int)$decoded['id'])->toBe(1);
	expect($decoded['name'])->toBe('John');
	expect($decoded['is_active'])->toBe(1);
});

// Edge Cases

test('cannot set non-existent field', function () {
	$user = new BasicUser();
	$user->non_existent = 'value';
	expect($user->__get('non_existent'))->toBeNull();
});

test('get returns null for non-existent field', function () {
	$user = new BasicUser();
	expect($user->non_existent)->toBeNull();
});

test('save inserts even if pk manually set if not conflicting', function () {
	BasicUser::createTable();

	$user = new BasicUser();
	$user->id = 100;
	$user->name = 'John';
	$user->email = 'john@example.com';
	expect($user->save())->toBeTrue();
	expect((int)$user->id)->toBe(100);

	$found = BasicUser::find(100);
	expect($found)->not->toBeNull();
	expect($found->name)->toBe('John');
});

test('save updates if pk set and record exists', function () {
	BasicUser::createTable();

	$user = new BasicUser();
	$user->name = 'John';
	$user->email = 'john@example.com';
	$user->save(); // Insert, id=1

	$user->name = 'Jane';
	expect($user->save())->toBeTrue(); // Update
	expect(BasicUser::find(1)->name)->toBe('Jane');
});

test('enum field does not enforce values on insert in SQLite', function () {
	EnumModel::createTable();

	$model = new EnumModel();
	$model->status = 'active';
	expect($model->save())->toBeTrue();

	$model = new EnumModel();
	$model->status = 'invalid'; // No error in SQLite
	expect($model->save())->toBeTrue();
});

test('tinyint does not validate values on set', function () {
	TinyintModel::createTable();

	$model = new TinyintModel();
	$model->value = 255;
	expect($model->save())->toBeTrue();

	$model = new TinyintModel();
	$model->value = 256; // No validation on set, saves in SQLite
	expect($model->save())->toBeTrue();
});

test('tinyint validates default in column definition', function () {
	class InvalidTinyintModel extends Model
	{
		public static function fields(): array
		{
			return [
				'id' => Column::Int(autoincrement: true, primaryKey: true),
				'value' => Column::Tinyint(unsigned: true, default: 256), // Out of range
			];
		}
	}

	InvalidTinyintModel::fields();
})->throws(InvalidArgumentException::class);
