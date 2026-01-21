# Architecture & Deep Reflection

This section provides a deep dive into the design decisions, trade-offs, and internal mechanics of the Rubik ORM.

## 1. The Schema Definition Strategy (`Column` Meta-programming)

Rubik employs a **Code-First** approach where the schema is the source of truth for validations.

### The Mechanism

The `Column` class utilizes the `__callStatic` magic method combined with a constant `TYPE_META` array. This allows for:

1.  **Driver Abstraction:** A `Column::Boolean()` is automatically translated to `INTEGER` for SQLite and `TINYINT(1)` for MySQL at runtime.
2.  **Input Validation:** Before the schema reaches the database, arguments (like `length`, `precision`) are validated via static validators (e.g., `validateDecimal`).
3.  **Fluency:** It avoids verbose class instantiations (e.g., `new Column('varchar', ...)`), preferring `Column::Varchar(...)`.

### Reflection

This design centralizes cross-database compatibility logic. Instead of having separate `MySqlBuilder` and `SqliteBuilder` classes for schema creation, the `SchemaTrait` acts as a unified translator based on the current `Driver` enum.

## 2. Singleton Connection Pattern

The `Rubik` class acts as a global singleton manager for the `PDO` instance.

```php
private static ?PDO $pdo = null;
```

**Trade-off Analysis:**

- **Pro:** Extremely simple API. `Rubik::connect()` allows models to access the database anywhere without dependency injection containers.
- **Con:** It creates global state. This makes testing slightly harder (requires explicit teardown) and prevents connecting to multiple databases simultaneously within the same request lifecycle (Multi-tenancy via multiple DBs is not natively supported).

## 3. The Query Builder & Sanitization

The `Query` class is responsible for building SQL. It maintains internal state (`$select`, `$where`, `$bindings`) and compiles them into a string only upon execution.

### Security Model

Rubik strictly separates **Identifiers** from **Values**.

- **Identifiers** (Table/Column names) are sanitized via `Rubik::quoteIdentifier()`. This handles wrapping names in backticks (MySQL) or double quotes (SQLite).
- **Values** are **never** injected directly into the SQL string unless wrapped in an `SQL` Value Object. They are passed as PDO parameters.

This dual-layer approach effectively mitigates SQL Injection risks while allowing flexibility via `SQL::raw()`.

## 4. Active Record & Traits Composition

The `Model` class is abstract and relatively empty. It derives its power almost entirely from Traits:

- `CrudTrait`: Persistence logic.
- `SchemaTrait`: DDL generation.
- `QueryTrait`: Static proxies.

**Reflective Benefit:** This composition over inheritance allows the core `Model` class to remain readable. It physically separates the concern of _defining_ data (`SchemaTrait`) from _saving_ data (`CrudTrait`).

## 5. Relationship Resolution

The `Relation` abstract class and its children (`HasMany`, etc.) act as specialized Query Builders.

When `with('posts')` is called:

1.  **Hydration:** The main query fetches parent models.
2.  **Collection:** Keys (e.g., User IDs) are collected from the parents.
3.  **Batch Query:** The relationship executes `WHERE user_id IN (...)`.
4.  **Dictionary Matching:** Results are mapped back to parents in memory.

Rubik manually handles this matching in `Query::eagerLoadRelations`, keeping the logic framework-agnostic.
