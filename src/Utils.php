<?php

namespace AdaiasMagdiel\Rubik;


/**
 * Value object that marks a SQL fragment as "raw" (to be injected verbatim).
 *
 * Use this to deliberately bypass quoting/escaping when composing SQL,
 * e.g. for DEFAULT clauses like CURRENT_TIMESTAMP, NOW(), UUID() or database-specific functions.
 *
 * ⚠️ SECURITY: Never pass user-provided/untrusted input to this class.
 * It is intended only for safe, constant SQL expressions you control.
 *
 * @psalm-immutable
 * @phpstan-immutable
 * @final
 */
final class RawSQL
{
    /**
     * @param string $expr SQL expression to be used verbatim
     *                     (e.g. "CURRENT_TIMESTAMP", "NOW()", "gen_random_uuid()").
     */
    public function __construct(private string $expr) {}

    /**
     * Returns the raw SQL expression as a string.
     *
     * @return string The verbatim SQL fragment.
     *
     * @example
     *  echo (string) new RawSQL('CURRENT_TIMESTAMP'); // CURRENT_TIMESTAMP
     */
    public function __toString(): string
    {
        return $this->expr;
    }
}

/**
 * Convenience helper to create a RawSQL instance.
 *
 * This makes call sites more readable when composing SQL:
 *
 * @example
 *  $default = rawSQL('CURRENT_TIMESTAMP');
 *  // ... later: "DEFAULT " . $default
 *
 * @param string $expr SQL expression to embed as-is (no quoting/escaping applied).
 * @return RawSQL
 */
function rawSQL(string $expr): RawSQL
{
    return new RawSQL($expr);
}
