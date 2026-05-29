<?php

declare(strict_types=1);

namespace Eleload\LoadTesting;

use InvalidArgumentException;

/**
 * A condition evaluated against a completed HTTP response.
 *
 * Supported fields: "status" (HTTP status code), "body" (response body as string).
 * Supported operators: ==, !=, <, >, contains, regex_match.
 */
final class ScenarioCondition
{
    public const FIELD_STATUS = 'status';
    public const FIELD_BODY   = 'body';

    public const OP_EQ          = '==';
    public const OP_NEQ         = '!=';
    public const OP_LT          = '<';
    public const OP_GT          = '>';
    public const OP_CONTAINS    = 'contains';
    public const OP_REGEX_MATCH = 'regex_match';

    public const ALLOWED_FIELDS = [self::FIELD_STATUS, self::FIELD_BODY];
    public const ALLOWED_OPS    = [
        self::OP_EQ,
        self::OP_NEQ,
        self::OP_LT,
        self::OP_GT,
        self::OP_CONTAINS,
        self::OP_REGEX_MATCH,
    ];

    public function __construct(
        /** @var 'status'|'body' */
        public readonly string $field,
        /** @var '=='|'!='|'<'|'>'|'contains'|'regex_match' */
        public readonly string $op,
        public readonly string|int $value
    ) {
        if (!in_array($field, self::ALLOWED_FIELDS, true)) {
            throw new InvalidArgumentException(
                "Condition field '{$field}' is not allowed. Use: " . implode(', ', self::ALLOWED_FIELDS)
            );
        }

        if (!in_array($op, self::ALLOWED_OPS, true)) {
            throw new InvalidArgumentException(
                "Condition operator '{$op}' is not allowed. Use: " . implode(', ', self::ALLOWED_OPS)
            );
        }
    }

    /**
     * Evaluate the condition against a completed HTTP response.
     */
    public function evaluate(int $statusCode, string $body): bool
    {
        $subject = $this->field === self::FIELD_STATUS ? $statusCode : $body;
        $value   = $this->value;

        return match ($this->op) {
            self::OP_EQ          => $subject == $value,
            self::OP_NEQ         => $subject != $value,
            self::OP_LT          => $subject < $value,
            self::OP_GT          => $subject > $value,
            self::OP_CONTAINS    => is_string($subject) && str_contains($subject, (string) $value),
            self::OP_REGEX_MATCH => is_string($subject) && @preg_match('/' . $value . '/', $subject) === 1,
        };
    }
}
