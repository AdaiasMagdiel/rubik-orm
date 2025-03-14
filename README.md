# Rubik ORM

Rubik ORM is a lightweight Object-Relational Mapping (ORM) designed specifically for SQLite databases. Inspired by the simplicity and structure of a Rubik's Cube, Rubik aims to provide an intuitive and straightforward way to interact with SQLite while supporting both Active Record and Query Builder patterns for CRUD operations.

---

## Description

Rubik ORM is built to be **simple yet complete**, offering essential features for SQLite database interactions. It allows developers to:

- **CRUD Operations**: Create, Read, Update, and Delete records using intuitive methods.
- **Active Record Pattern**: Define models with database mappings and perform operations directly on instances.
- **Query Builder**: Construct complex SQL queries fluently with methods like `where`, `select`, `limit`, and `whereIn`.
- **SQLite-Specific Optimization**: Built for SQLite's ecosystem, ensuring compatibility and performance.

---

## Installation

Install via Composer:

```bash
composer require adaiasmagdiel/rubik
```

---

## Usage

The documentation is currently under development, but the core functionality is already operational. For now, you can:

1. **Define Models**: Extend the base model class and define table names and primary keys.
2. **Active Record**: Use methods like `save()`, `delete()`, and `find()` directly on model instances.
3. **Query Builder**: Build queries using methods like `where()`, `select()`, and `exec()`.

Check the code in the repository for implementation details.

---

## License

Rubik ORM is licensed under the **GNU General Public License v3.0 (GPL-3.0)**.  
See the [LICENSE](LICENSE) and [COPYRIGHT](COPYRIGHT) files for more details.
