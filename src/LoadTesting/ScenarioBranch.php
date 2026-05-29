<?php

declare(strict_types=1);

namespace Eleload\LoadTesting;

/**
 * A conditional branch attached to a ScenarioStep.
 * When the step's response satisfies $condition, $then steps are executed;
 * otherwise $else steps are executed.
 */
final class ScenarioBranch
{
    /**
     * @param list<ScenarioStep> $then  Steps to run when condition is true
     * @param list<ScenarioStep> $else  Steps to run when condition is false
     */
    public function __construct(
        public readonly ScenarioCondition $condition,
        public readonly array $then = [],
        public readonly array $else = []
    ) {
    }
}
