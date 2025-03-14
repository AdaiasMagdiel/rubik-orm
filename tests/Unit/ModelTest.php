<?php

use AdaiasMagdiel\Rubik\Model;
use AdaiasMagdiel\Rubik\Rubik;

Rubik::connect(":memory:");

class Book extends Model
{
    public static string $table = "books";

    public static function fields(): array
    {
        return [
            "id" => Model::Int(autoincrement: true, pk: true),
            "author" => Model::Text(notNull: true),
            "title" => Model::Text(notNull: true, unique: true),
        ];
    }
}

describe('Model CRUD Operations', function () {
    beforeEach(function () {
        // Reset database state between tests
        Rubik::getConn()->exec("DROP TABLE IF EXISTS books");
        Book::createTable();
    });

    test('should create new table when called for the first time', function () {
        Rubik::getConn()->exec("DROP TABLE IF EXISTS books");
        $result = Book::createTable();
        expect($result)->toBeTrue();
    });

    test('should throw exception when attempting to create existing table without ignore flag', function () {
        Book::createTable();
    })->throws(Exception::class);

    test('should not throw exception when creating existing table with ignore flag', function () {
        $result = Book::createTable(ignore: true);
        expect($result)->toBeTrue();
    });

    test('should persist new valid book to database', function () {
        $book = new Book();
        $book->author = "J.R.R. Tolkien";
        $book->title = "The Lord of the Rings";

        $result = $book->save();

        expect($result)->toBeTrue()
            ->and($book)->toBeInstanceOf(Book::class)
            ->and($book->id)->toBe("1");
    });

    test('should retrieve existing book by primary key', function () {
        $original = new Book();
        $original->author = "George Orwell";
        $original->title = "1984";
        $original->save();

        $found = Book::find(1);

        expect($found)->toBeInstanceOf(Book::class)
            ->and($found->author)->toBe("George Orwell")
            ->and($found->title)->toBe("1984");
    });

    test('should throw exception when violating unique constraint on title field', function () {
        $book1 = new Book();
        $book1->author = "Author A";
        $book1->title = "Unique Title";
        $book1->save();

        $book2 = new Book();
        $book2->author = "Author B";
        $book2->title = "Unique Title";

        $book2->save();
    })->throws(Exception::class);

    test('should update existing book and persist changes', function () {
        $original = new Book();
        $original->author = "Original Author";
        $original->title = "Original Title";
        $original->save();

        $updated = Book::find(1);
        $updated->title = "Updated Title";
        $result = $updated->update();

        expect($result)->toBeTrue();

        $freshCopy = Book::find(1);
        expect($freshCopy->title)->toBe("Updated Title")
            ->and($freshCopy->author)->toBe("Original Author");
    });

    test('should delete existing book and remove from database', function () {
        $book = new Book();
        $book->author = "To Be Deleted";
        $book->title = "Ephemeral Title";
        $book->save();

        $deletionResult = $book->delete();
        expect($deletionResult)->toBeTrue();

        $found = Book::find(1);
        expect($found)->toBeNull();
    });
});

describe('Query Operations', function () {
    beforeEach(function () {
        Rubik::getConn()->exec("DROP TABLE IF EXISTS books");
        Book::createTable();

        // Seed test data
        $books = [
            ['author' => 'Author 1', 'title' => 'Book Alpha'],
            ['author' => 'Author 2', 'title' => 'Book Beta'],
            ['author' => 'Author 3', 'title' => 'Book Gamma']
        ];

        foreach ($books as $book) {
            $b = new Book();
            $b->author = $book['author'];
            $b->title = $book['title'];
            $b->save();
        }
    });

    test('should find book by specific field value using exact match', function () {
        $result = Book::findOneBy('title', 'Book Beta');

        expect($result)->toBeInstanceOf(Book::class)
            ->and($result->author)->toBe('Author 2')
            ->and($result->title)->toBe('Book Beta');
    });

    test('should retrieve multiple records using LIKE operator', function () {
        $results = Book::findAllBy('title', 'Book%', 'LIKE');

        expect($results)->toBeArray()
            ->toHaveCount(3)
            ->each->toBeInstanceOf(Book::class);

        expect($results[0]->title)->toBe('Book Alpha');
        expect($results[1]->title)->toBe('Book Beta');
        expect($results[2]->title)->toBe('Book Gamma');
    });
});
