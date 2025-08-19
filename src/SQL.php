<?php

namespace AdaiasMagdiel\Rubik;


/**
 * Value object representing a **raw SQL fragment** to be injected verbatim.
 *
 * Useful for defaults and expressions that **must not** be quoted/escaped by your SQL builder,
 * such as `CURRENT_TIMESTAMP`, `NOW()`, `gen_random_uuid()`, or database-specific functions.
 *
 * ⚠️ **SECURITY**: Never pass user-provided/untrusted input here. Use only **constant**
 * expressions you control. This type deliberately bypasses quoting/escaping.
 *
 * Examples:
 *
 * ```php
 * use AdaiasMagdiel\Rubik\SQL;
 *
 * // In a column definition (literal DEFAULT):
 * ['created_at' => ['type' => 'timestamptz', 'default' => SQL::raw('CURRENT_TIMESTAMP')]]
 *
 * // In controlled INSERT/UPDATE values:
 * $builder->set('updated_at', SQL::raw('NOW()'));
 * ```
 *
 * @psalm-immutable
 * @phpstan-immutable
 * @final
 */
final class SQL
{
    /**
     * SQL fragment to be emitted verbatim.
     *
     * @var string
     */
    private string $expr;

    /**
     * @param string $expr SQL expression to be used **as-is** (no quoting/escaping),
     *                     e.g. "CURRENT_TIMESTAMP", "NOW()", "gen_random_uuid()".
     */
    public function __construct(string $expr)
    {
        $this->expr = $expr;
    }

    /**
     * Returns the raw SQL fragment.
     *
     * @return string The SQL exactly as provided.
     *
     * @example
     *  echo (string) SQL::raw('CURRENT_TIMESTAMP'); // CURRENT_TIMESTAMP
     */
    public function __toString(): string
    {
        return $this->expr;
    }

    /**
     * Convenience factory for creating a raw SQL fragment.
     *
     * Prefer this method for readability at call sites.
     *
     * @param string $expr SQL expression to embed without quoting/escaping.
     * @return static Instance representing the raw fragment.
     *
     * @example
     *  $default = SQL::raw('gen_random_uuid()');
     *  // Your builder should detect SQL instances and avoid quoting them.
     */
    public static function raw(string $expr): static
    {
        return new static($expr);
    }
}
